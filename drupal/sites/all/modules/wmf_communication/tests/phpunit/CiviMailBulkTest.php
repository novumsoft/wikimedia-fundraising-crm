<?php
namespace wmf_communication;

use \CRM_Activity_BAO_ActivityTarget;
use \CRM_Mailing_BAO_Recipients;
use \CRM_Mailing_Event_BAO_Queue;
use \CRM_Mailing_Event_BAO_Delivered;
/**
 * Tests for CiviMail helper classes
 * @group CiviMail
 * @group WmfCommunication
 */
class CiviMailBulkTest extends CiviMailTestBase {

	protected $contacts = array();
	protected $emails = array();
	/**
	 * @var ICiviMailBulkStore
	 */
	protected $bulkMailStore;

	public function setUp() {
		parent::setUp();

		$this->bulkMailStore = new CiviMailBulkStore();

		for ( $i = 1; $i <= 10; $i++ ) {
			$emailAddress = "hondonian$i@hondo.mil";
			$firstName = "Kevin$i";
			$lastName = 'Hondo';

			$contact = $this->getContact( $emailAddress, $firstName, $lastName );

			$this->contacts[] = $contact + array( 'emailAddress' => $emailAddress );
			$this->emails[] = $emailAddress;
		}
	}

	public function tearDown(): void {
    foreach ( $this->contacts as $contact ) {
      $this->callAPISuccess('Email', 'delete', ['id' => $contact['emailID']]);
      $this->callAPISuccess( 'Contact', 'delete', ['id' => $contact['contactID'], 'skip_undelete' => TRUE]);
    }
		parent::tearDown();
	}

	public function testAddSentBulk() {
		$name = 'test_mailing';
		$revision = mt_rand();
		$storedMailing = $this->mailStore->addMailing(
			$this->source,
			$name,
			$this->body,
			$this->subject,
			$revision
		);

		$this->bulkMailStore->addSentBulk( $storedMailing, $this->emails );

		$mailingID = $storedMailing->getMailingID();
		// Should have a single bulk mailing activity created
		$activities = $this->callAPISuccess('Activity', 'get', array(
			'source_record_id' => $mailingID,
			'activity_type_id' => 'Bulk Email',
		));
		$this->assertTrue($activities['count'] === 1);

		foreach ( $this->contacts as $contact ) {
			//recipients table
			$recipients = new CRM_Mailing_BAO_Recipients();
			$recipients->mailing_id = $mailingID;
			$recipients->contact_id = $contact['contactID'];
			$recipients->email_id = $contact['emailID'];
			$this->assertTrue( $recipients->find() && $recipients->fetch() );

			//queue entry
			$queueQuery = "SELECT q.id, q.contact_id
FROM civicrm_mailing_event_queue q
INNER JOIN civicrm_mailing_job j ON q.job_id = j.id
WHERE j.mailing_id = $mailingID";

			$queue = new CRM_Mailing_Event_BAO_Queue();
			$queue->query( $queueQuery );
			$this->assertTrue( $queue->fetch() );

			//delivery event
			$delivered = new CRM_Mailing_Event_BAO_Delivered();
			$delivered->event_queue_id = $queue->id;
			$this->assertTrue( $delivered->find() && $delivered->fetch() );

			//activity target
			$activityTarget = new CRM_Activity_BAO_ActivityTarget();
			$activityTarget->activity_id = $activities['id'];
			$activityTarget->target_contact_id = $contact['contactID'];
			$this->assertTrue( $activityTarget->find() && $activityTarget->fetch() );
		}
	}
}
