<?php

use queue2civicrm\unsubscribe\OptInQueueConsumer;

/**
 * @group Queue2Civicrm
 */
class OptInTest extends BaseWmfDrupalPhpUnitTestCase {

  /**
   * @var OptInQueueConsumer
   */
  protected $consumer;

  /**
   * @var int
   */
  protected $contactId;

  /**
   * @var string
   */
  protected $email;

  /**
   * @var string;
   */
  protected $optInCustomFieldName;

  /**
   * @var \CiviFixtures
   */
  protected $fixtures;

  public function setUp() {
    parent::setUp();
    $this->fixtures = CiviFixtures::create();
    $this->contactId = $this->fixtures->contact_id;
    $this->email = 'testOptIn' . mt_rand(1000, 10000000) . '@example.net';

    $this->consumer = new OptInQueueConsumer(
      'opt-in'
    );

    $id = CRM_Core_BAO_CustomField::getCustomFieldID(
      'opt_in', 'Communications'
    );
    $this->optInCustomFieldName = "custom_{$id}";
  }

  public function tearDown() {
    parent::tearDown();
    $this->fixtures = null;
  }

  protected function getMessage() {
    return [
      'email' => $this->email,
      'process' => 'opt_in',
    ];
  }

  protected function getContactMessage() {
    return [
      'email' => $this->email,
      'first_name' => 'Christine',
      'last_name' => 'Test',
      'street_address' => '1 Test Street',
      'city'=> 'Testland',
      'postal_code' => '13126',
      'country' => 'US',
    ];
  }

  protected function createEmail($params = []) {
    $params += [
      'email' => $this->email,
      'contact_id' => $this->contactId,
    ];
    civicrm_api3('Email', 'create', $params);
  }

  protected function getContact() {
    return civicrm_api3('Contact', 'getSingle', [
      'id' => $this->contactId,
      'return' => [$this->optInCustomFieldName],
    ]);
  }

  public function testValidMessage() {
    $this->createEmail(['is_primary' => '1']);
    $this->consumer->processMessage($this->getMessage());
    $contact = $this->getContact();
    $this->assertEquals('1', $contact[$this->optInCustomFieldName]);
  }

  public function testNonExistantEmail() {
    $this->consumer->processMessage($this->getContactMessage());
    $contact = $this->getContact();
    $this->assertEquals('', $contact[$this->optInCustomFieldName]);

    //check that the contact was created
    $newContactCheck = $this->callApiSuccessGetSingle('Contact', ['email' => $this->email]);
    $this->contactId = $newContactCheck['id'];
    $custom = $this->getContact();

    //check that there is a new contact id
    $this->assertNotEquals($contact['id'],$newContactCheck['id']);

    //check that the opt_in field was set
    $this->assertEquals('1', $custom[$this->optInCustomFieldName]);
  }

  public function testNonPrimaryEmail() {
    $this->createEmail([
      'email' => 'aDifferentEmail@example.net',
      'is_primary' => 1
    ]);
    $this->createEmail([
      'is_primary' => 0
    ]);
    $this->consumer->processMessage($this->getMessage());
    $contact = $this->getContact();
    $this->assertEquals('', $contact[$this->optInCustomFieldName]);
  }

  /**
   * @expectedException WmfException
   */
  public function testMalformedMessage() {
    $msg = [
      'hither' => 'thither',
    ];
    $this->consumer->processMessage($msg);
  }

}
