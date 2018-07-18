<?php

use Civi\Test\EndToEndInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * FIXME - Add test description.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group e2e
 */
class OmnimailBaseTestClass extends \PHPUnit_Framework_TestCase implements EndToEndInterface, TransactionalInterface {

  use \Civi\Test\Api3TestTrait;


  /**
   * IDs of contacts created for the test.
   *
   * @var array
   */
  protected $contactIDs = array();

  public function setUp() {
    civicrm_initialize();
    if (!isset($GLOBALS['_PEAR_default_error_mode'])) {
      // This is simply to protect against e-notices if globals have been reset by phpunit.
      $GLOBALS['_PEAR_default_error_mode'] = NULL;
      $GLOBALS['_PEAR_default_error_options'] = NULL;
    }
    parent::setUp();
    $null = NULL;
    Civi::service('settings_manager')->flush();
    \Civi::$statics['_omnimail_settings'] = array();
  }

  public function tearDown() {
    foreach ($this->contactIDs as $contactID) {
      $this->callAPISuccess('Contact', 'delete', ['id' => $contactID, 'skip_undelete' => 1]);
    }
    parent::tearDown();
  }

  /**
   * Get mock guzzle client object.
   *
   * @param $body
   * @param bool $authenticateFirst
   * @return \GuzzleHttp\Client
   */
  public function getMockRequest($body = array(), $authenticateFirst = TRUE) {

    $responses = array();
    if ($authenticateFirst) {
      $responses[] = new Response(200, [], file_get_contents(__DIR__ . '/Responses/AuthenticateResponse.txt'));
    }
    foreach ($body as $responseBody) {
      $responses[] = new Response(200, [], $responseBody);
    }
    $mock = new MockHandler($responses);
    $handler = HandlerStack::create($mock);
    return new Client(array('handler' => $handler));
  }


  /**
   * Set up the mock client to imitate a success result.
   *
   * @param string $job
   *
   * @return \GuzzleHttp\Client
   */
  protected function setupSuccessfulDownloadClient($job = 'omnimail_omnigroupmembers_load') {
    $responses = array(
      file_get_contents(__DIR__ . '/Responses/RawRecipientDataExportResponse.txt'),
      file_get_contents(__DIR__ . '/Responses/JobStatusCompleteResponse.txt'),
    );
    //Raw Recipient Data Export Jul 02 2017 21-46-49 PM 758.zip
    copy(__DIR__ . '/Responses/Raw Recipient Data Export Jul 03 2017 00-47-42 AM 1295.csv', sys_get_temp_dir() . '/Raw Recipient Data Export Jul 03 2017 00-47-42 AM 1295.csv');
    fopen(sys_get_temp_dir() . '/Raw Recipient Data Export Jul 03 2017 00-47-42 AM 1295.csv.complete', 'c');
    $this->createSetting(array('job' => $job, 'mailing_provider' => 'Silverpop', 'last_timestamp' => '1487890800'));
    $client = $this->getMockRequest($responses);
    return $client;
  }

  /**
   * Create a CiviCRM setting with some extra debugging if it fails.
   *
   * @param array $values
   */
  protected function createSetting($values) {
    foreach (array('last_timestamp', 'progress_end_timestamp') as $dateField) {
      if (!empty($values[$dateField])) {
        $values[$dateField] = gmdate('YmdHis', $values[$dateField]);
      }
    }
    try {
      civicrm_api3('OmnimailJobProgress', 'create', $values);
    }
    catch (CiviCRM_API3_Exception $e) {
      $this->fail(print_r($values, 1), $e->getMessage() . $e->getTraceAsString() . print_r($e->getExtraParams(), TRUE));
    }
  }

  /**
   * Get job settings with dates rendered to UTC string.
   *
   * @param array $params
   *
   * @return array
   */
  public function getUtcDateFormattedJobSettings($params = array('mail_provider' => 'Silverpop')) {
     $settings = $this->getJobSettings($params);
     $dateFields = array('last_timestamp', 'progress_end_timestamp');
     foreach ($dateFields as $dateField) {
       if (!empty($settings[$dateField])) {
         $settings[$dateField] = date('Y-m-d H:i:s', $settings[$dateField]);
       }
     }
     return $settings;
  }

  public function createMailingProviderData() {
    $this->callAPISuccess('Campaign', 'create', array('name' => 'xyz', 'title' => 'Cool Campaign'));
    $this->callAPISuccess('Mailing', 'create', array('campaign_id' => 'xyz', 'hash' => 'xyz', 'name' => 'Mail'));
    $this->callAPISuccess('MailingProviderData', 'create',  array(
      'contact_id' => $this->contactIDs[0],
      'email' => 'charlie@example.com',
      'event_type' => 'Opt Out',
      'mailing_identifier' => 'xyz',
      'recipient_action_datetime' => '2017-02-02',
      'contact_identifier' => 'a',
    ));
    $this->callAPISuccess('MailingProviderData', 'create',  array(
      'contact_id' => $this->contactIDs[2],
      'event_type' => 'Open',
      'mailing_identifier' => 'xyz',
      'recipient_action_datetime' => '2017-03-03',
      'contact_identifier' => 'b',
    ));
    $this->callAPISuccess('MailingProviderData', 'create',  array(
      'contact_id' => $this->contactIDs[3],
      'event_type' => 'Suppressed',
      'mailing_identifier' => 'xyuuuz',
      'recipient_action_datetime' => '2017-04-04',
      'contact_identifier' => 'c',
    ));
  }

  protected function makeScientists() {
    $contact = $this->callAPISuccess('Contact', 'create', array(
      'first_name' => 'Charles',
      'last_name' => 'Darwin',
      'contact_type' => 'Individual'
    ));
    $this->contactIDs[] = $contact['id'];
    $contact = $this->callAPISuccess('Contact', 'create', array(
      'first_name' => 'Charlie',
      'last_name' => 'Darwin',
      'contact_type' => 'Individual',
      'api.email.create' => array(
        'is_bulkmail' => 1,
        'email' => 'charlie@example.com'
      )
    ));
    $this->contactIDs[] = $contact['id'];

    $contact = $this->callAPISuccess('Contact', 'create', array(
      'first_name' => 'Marie',
      'last_name' => 'Currie',
      'contact_type' => 'Individual'
    ));
    $this->contactIDs[] = $contact['id'];
    $contact = $this->callAPISuccess('Contact', 'create', array(
      'first_name' => 'Isaac',
      'last_name' => 'Newton',
      'contact_type' => 'Individual'
    ));
    $this->contactIDs[] = $contact['id'];
  }

}
