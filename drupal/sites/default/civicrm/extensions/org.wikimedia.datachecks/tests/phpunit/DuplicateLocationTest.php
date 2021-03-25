<?php

use CRM_Datachecks_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

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
 * @group headless
 */
class DuplicateLocationTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {
  use \Civi\Test\Api3TestTrait;

  protected $addressParams = [
    'street_address' => '123 ABC st', 'city' => 'LeaningVille', 'location_type_id' => 'Home'
  ];

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://docs.civicrm.org/dev/en/latest/testing/phpunit/#civitest
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp(): void {
    civicrm_initialize();
    parent::setUp();
  }

  public function tearDown(): void {
    parent::tearDown();
    $this->callAPISuccess('Data', 'fix', ['check' => 'DuplicateLocation']);
  }

  /**
   * Test that a duplicate address of same location is resolved through deletion where they match.
   */
  public function testCheckAndFixDuplicateIdenticalAddress() {
    $contact = $this->callAPISuccess('Contact', 'create', ['contact_type' => 'Individual', 'first_name' => 'Adam', 'last_name' => 'Ant']);
    $this->addressParams['contact_id'] = $contact['id'];
    $this->callAPISuccess('Address', 'create', $this->addressParams);
    $this->callAPISuccess('Address', 'create', $this->addressParams);
    $check = $this->callAPISuccess('Data', 'check', ['check' => 'DuplicateLocation']);
    $this->assertEquals(['contact' => [$contact['id']]], $check['values']['DuplicateLocation']['address']['example']);

    $this->callAPISuccess('Data', 'fix', ['check' => 'DuplicateLocation']);
    $check = $this->callAPISuccess('Data', 'check', ['check' => 'DuplicateLocation']);
    $this->assertTrue(empty($check['values']['DuplicateLocation']['address']));

    $address = $this->callAPISuccess('Address', 'getsingle', ['contact_id' => $contact['id']]);
    $this->assertEquals(1, $address['is_primary']);
  }

  /**
   * Test that a duplicate address of same location is resolved through location Type change where they match.
   */
  public function testCheckAndFixDuplicateDifferentAddress() {
    $contact = $this->callAPISuccess('Contact', 'create', ['contact_type' => 'Individual', 'first_name' => 'Adam', 'last_name' => 'Ant']);
    $this->addressParams['contact_id'] = $contact['id'];
    $this->callAPISuccess('Address', 'create', $this->addressParams);
    $this->callAPISuccess('Address', 'create', array_merge($this->addressParams, ['supplemental_address_2' => 'gremlin']));

    $this->callAPISuccess('Data', 'fix', ['check' => 'DuplicateLocation']);
    $check = $this->callAPISuccess('Data', 'check', ['check' => 'DuplicateLocation']);
    $this->assertTrue(empty($check['values']['DuplicateLocation']['address']));

    $addresses = $this->callAPISuccess('Address', 'get', ['contact_id' => $contact['id'], 'sequential' => 1])['values'];
    $this->assertTrue($addresses[0]['location_type_id'] !== $addresses[1]['location_type_id']);
  }

  /**
   * Test correct duplicate calculation for phones.
   *
   * Unlike other location entities phones are not unique by location type but rather
   * location type + phone_type_id
   */
  public function testCheckPhoneDuplicateCheck() {
    $contact = $this->callAPISuccess('Contact', 'create', ['contact_type' => 'Individual', 'first_name' => 'Adam', 'last_name' => 'Ant']);
    $contact2 = $this->callAPISuccess('Contact', 'create', ['contact_type' => 'Individual', 'first_name' => 'Adam', 'last_name' => 'Ant']);
    $this->callAPISuccess('Phone', 'create', [
      'phone' => 12345,
      'location_type_id' => 'Home',
      'phone_type_id' => 'Mobile',
      'contact_id' => $contact['id'],
    ]);
    $this->callAPISuccess('Phone', 'create', [
      'phone' => 12345,
      'location_type_id' => 'Home',
      'phone_type_id' => 'Mobile',
      'contact_id' => $contact['id'],
    ]);
    $this->callAPISuccess('Phone', 'create', [
      'phone' => 12345,
      'location_type_id' => 'Home',
      'phone_type_id' => 'Fax',
      'contact_id' => $contact['id'],
    ]);
    $this->callAPISuccess('Phone', 'create', [
      'phone' => 12345,
      'location_type_id' => 'Home',
      'phone_type_id' => 'Mobile',
      'contact_id' => $contact2['id'],
    ]);
    $this->callAPISuccess('Phone', 'create', [
      'phone' => 12345,
      'location_type_id' => 'Home',
      'phone_type_id' => 'Fax',
      'contact_id' => $contact2['id'],
    ]);

    // Contact one has a genuine duplicate, 2 doesn't.
    $check = $this->callAPISuccess('Data', 'check', ['check' => 'DuplicateLocation']);
    $this->assertEquals(['contact' => [$contact['id']]], $check['values']['DuplicateLocation']['phone']['example']);

    $this->callAPISuccess('Data', 'fix', ['check' => 'DuplicateLocation']);
    $check = $this->callAPISuccess('Data', 'check', ['check' => 'DuplicateLocation']);
    $this->assertTrue(empty($check['values']['DuplicateLocation']['phone']));

    foreach ([$contact, $contact2] as $currentContact) {
      $phones = $this->callAPISuccess('Phone', 'get', [
        'contact_id' => $currentContact['id'],
        'sequential' => 1,
      ]);
      $this->assertEquals(2, $phones['count']);
      $this->assertTrue($phones['values'][0]['phone_type_id'] !== $phones['values'][1]['phone_type_id']);
      $this->assertTrue($phones['values'][0]['location_type_id'] === $phones['values'][1]['location_type_id']);
    }
  }

}
