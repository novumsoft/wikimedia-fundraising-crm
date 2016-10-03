<?php

/**
 * @group Pipeline
 * @group WmfCivicrm
 */
class MergeTest extends BaseWmfDrupalPhpUnitTestCase {

  /**
   * Id of the contact created in the setup function.
   *
   * @var int
   */
  protected $contactID;

  /**
   * Id of the contact created in the setup function.
   *
   * @var int
   */
  protected $contactID2;

  public function setUp() {
    parent::setUp();
    civicrm_initialize();
    $this->imitateAdminUser();
    $this->doDuckHunt();
    // Run through the merge first to make sure there aren't pre-existing contacts in the DB
    // that will ruin the tests.
    $this->callAPISuccess('Job', 'process_batch_merge', array('mode' => 'safe'));

    $this->contactID = $this->breedDuck(array(wmf_civicrm_get_custom_field_name('do_not_solicit') => 0));
    $this->contactID2 = $this->breedDuck(array(wmf_civicrm_get_custom_field_name('do_not_solicit') => 1));
  }

  public function tearDown() {
    $this->callAPISuccess('Contribution', 'get', array(
      'contact_id' => array('IN' => array($this->contactID, $this->contactID2)),
      'api.Contribution.delete' => 1,
    ));
    $this->callAPISuccess('Contact', 'delete', array('id' => $this->contactID));
    $this->callAPISuccess('Contact', 'delete', array('id' => $this->contactID2));
    parent::tearDown();
  }

  /**
   * Test that the merge hook causes our custom fields to not be treated as conflicts.
   *
   * We also need to check the custom data fields afterwards.
   */
  public function testMergeHook() {
    $this->callAPISuccess('Contribution', 'create', array(
      'contact_id' => $this->contactID,
      'financial_type_id' => 'Cash',
      'total_amount' => 10,
      'currency' => 'USD',
      // Should cause 'is_2014 to be true.
      'receive_date' => '2014-08-04',
      wmf_civicrm_get_custom_field_name('original_currency') => 'NZD',
      wmf_civicrm_get_custom_field_name('original_amount') => 8,
    ));
    $this->callAPISuccess('Contribution', 'create', array(
      'contact_id' => $this->contactID2,
      'financial_type_id' => 'Cash',
      'total_amount' => 5,
      'currency' => 'USD',
      // Should cause 'is_2012_donor to be true.
      'receive_date' => '2013-01-04',
    ));
    $this->callAPISuccess('Contribution', 'create', array(
      'contact_id' => $this->contactID2,
      'financial_type_id' => 'Cash',
      'total_amount' => 9,
      'currency' => 'NZD',
      // Should cause 'is_2015_donor to be true.
      'receive_date' => '2016-04-04',
    ));
    $contact = $this->callAPISuccess('Contact', 'get', array(
      'id' => $this->contactID,
      'sequential' => 1,
      'return' => array(wmf_civicrm_get_custom_field_name('lifetime_usd_total'), wmf_civicrm_get_custom_field_name('do_not_solicit')),
    ));
    $this->assertEquals(10, $contact['values'][0][wmf_civicrm_get_custom_field_name('lifetime_usd_total')]);
    $result = $this->callAPISuccess('Job', 'process_batch_merge', array(
      'criteria' => array('contact' => array('id' => array('IN' => array($this->contactID, $this->contactID2)))),
    ));
    $this->assertEquals(1, count($result['values']['merged']));
    $contact = $this->callAPISuccess('Contact', 'get', array(
      'id' => $this->contactID,
      'sequential' => 1,
      'return' => array(
        wmf_civicrm_get_custom_field_name('lifetime_usd_total'),
        wmf_civicrm_get_custom_field_name('do_not_solicit'),
        wmf_civicrm_get_custom_field_name('last_donation_amount'),
        wmf_civicrm_get_custom_field_name('last_donation_currency'),
        wmf_civicrm_get_custom_field_name('last_donation_usd'),
        wmf_civicrm_get_custom_field_name('last_donation_date'),
        wmf_civicrm_get_custom_field_name('is_2011_donor'),
        wmf_civicrm_get_custom_field_name('is_2012_donor'),
        wmf_civicrm_get_custom_field_name('is_2013_donor'),
        wmf_civicrm_get_custom_field_name('is_2014_donor'),
        wmf_civicrm_get_custom_field_name('is_2015_donor'),
        wmf_civicrm_get_custom_field_name('is_2016_donor'),
      ),
    ));
    $this->assertEquals(24, $contact['values'][0][wmf_civicrm_get_custom_field_name('lifetime_usd_total')]);
    $this->assertEquals(1, $contact['values'][0][wmf_civicrm_get_custom_field_name('do_not_solicit')]);
    $this->assertEquals(0, $contact['values'][0][wmf_civicrm_get_custom_field_name('is_2011_donor')]);
    $this->assertEquals(1, $contact['values'][0][wmf_civicrm_get_custom_field_name('is_2012_donor')]);
    $this->assertEquals(0, $contact['values'][0][wmf_civicrm_get_custom_field_name('is_2013_donor')]);
    $this->assertEquals(1, $contact['values'][0][wmf_civicrm_get_custom_field_name('is_2014_donor')]);
    $this->assertEquals(1, $contact['values'][0][wmf_civicrm_get_custom_field_name('is_2015_donor')]);
    $this->assertEquals(0, $contact['values'][0][wmf_civicrm_get_custom_field_name('is_2016_donor')]);
    $this->assertEquals(9, $contact['values'][0][wmf_civicrm_get_custom_field_name('last_donation_amount')]);
    $this->assertEquals(9, $contact['values'][0][wmf_civicrm_get_custom_field_name('last_donation_usd')]);
    $this->assertEquals('2016-04-04 00:00:00', $contact['values'][0][wmf_civicrm_get_custom_field_name('last_donation_date')]);
    $this->assertEquals('NZD', $contact['values'][0][wmf_civicrm_get_custom_field_name('last_donation_currency')]);

    // Now lets check the one to be deleted has a do_not_solicit = 0.
    $this->callAPISuccess('Contact', 'create', array(
      'contact_type' => 'Individual',
      'first_name' => 'Donald',
      'last_name' => 'Duck',
      'email' => 'the_don@duckland.com',
      wmf_civicrm_get_custom_field_name('do_not_solicit') => 0,
    ));
    $result = $this->callAPISuccess('Job', 'process_batch_merge', array(
      'criteria' => array('contact' => array('id' => $this->contactID)),
    ));
    $this->assertEquals(1, count($result['values']['merged']));
    $contact = $this->callAPISuccess('Contact', 'get', array(
      'id' => $this->contactID,
      'sequential' => 1,
      'return' => array(wmf_civicrm_get_custom_field_name('lifetime_usd_total'), wmf_civicrm_get_custom_field_name('do_not_solicit')),
    ));
    $this->assertEquals(1, $contact['values'][0][wmf_civicrm_get_custom_field_name('do_not_solicit')]);
  }

  /**
   * Test altering the address decision by hook.
   *
   * I feel I did something a bit sneaky here. I actually wrote both the test and
   * the hook against the core repo and committed in in this test.
   *
   * I figured that made core more robust & helped future proof us.
   *
   * https://github.com/civicrm/civicrm-core/blob/master/tests/phpunit/api/v3/JobTest.php#L584
   * https://github.com/civicrm/civicrm-core/blob/master/tests/phpunit/api/v3/JobTest.php#L643
   *
   * However, I'm replicating the test into our repo to test it still works distilled
   * into our hook.
   *
   * Tested scenarios: Note these apply to addresses, phones & emails.
   *
   *  1) (Fill data) both contacts have the same primary with the same location (Home). The first has an additional address (Mailing).
   *      Outcome: common primary is retained as the Home address & additional Mailing address is retained on the merged contact.
   *      Notes: our behaviour is the same as core.
   *  2) (Fill data) (reverse of 1) both contacts have the same primary with the same location (Home).
   *      The second has an additional  address (Mailing).
   *      Outcome: common primary is retained & additional Mailing address is retained on the merged contact.
   *      Notes: our behaviour is the same as core.
   *  3) (Fill data)  only one contact has an address (Home) - first contact.
   *     Outcome: address retained
   *     Notes: our behaviour is the same as core.
   *  4) (Fill data) (reverse of 3) only one contact has an address (Home) - second contact.
   *     Outcome: address retained
   *     Notes: our behaviour is the same as core.
   *  5) (Resolve Data) Contacts have different primary addresses with different
   *     location types. ie. first has a primary Home address. Second has a primary
   *     Mailing address. Addresses Differ.
   *     Outcome: keep both addresses. Use the address of the later donor as the primary.
   *     Notes: differs from core behaviour which would keep the address of the contact
   *     with the lower contact_id as the primary
   *  6) (Resolve Data) (reverse of 5) Contacts have different primary addresses with different
   *     location types. ie. first has a primary Mailing address.  Second has a primary
   *     Home address. Addresses Differ.
   *     Outcome: keep both addresses. Use the address of the later donor as the primary.
   *     Notes: differs from core behaviour which would keep the address of the contact
   *     with the lower contact_id as the primary
   *  7) (Resolve Data) Contacts have the same Home address. For the first the Home
   *     address is primary. For the second a (different) mailing address is.
   *     Outcome: both addresses kept. The one that is primary for the later donor is primary.
   *     Notes: same as 5 & 6 but with an additional address. Differs from core which
   *     would set primary to match to lower contact_id.
   *  8) (Resolve Data) (reverse of 7) Contacts have the same Mailing address. For the first
   *     the Mailing address is primary. For the second a (different) home address is.
   *     Outcome: both addresses kept. The one that is primary for the later donor is primary.
   *     Notes: same as 5 & 6 but with an additional address. Differs from core which
   *     would set primary to match to lower contact_id.
   *  9) (Resolve Data) Contacts have the same primary address but for the first
   *     contact is is Home whereas for the second is is Mailing.
   *     Outcome: keep the address. Use the Mailing location of the later donor (the second).
   *     Notes: differs from core behaviour which would keep 2 copies of the address with
   *     2 locations.
   * 10) (Resolve Data) (reverse of 9) Contacts have the same primary address but for the first
   *     contact is is Mailing whereas for the second is is Home.
   *     Outcome: keep the address. Use the Home location of the later donor (the second).
   *     Notes: differs from core behaviour which would keep 2 copies of the address with
   *     2 locations.
   * 11) (Throw conflict) Contacts have conflicting home address. Total giving = $500.
   *     Outcome: conflict - do not merge.
   *     Notes: This is like core, but for us less than 500 will merge.
   * 12) (Resolve Data)  Contacts have conflicting home address. Total giving < $500.
   *     Outcome: merge - only keep home address of latest donor.
   *     Notes: differs from core.
   * 13) (Throw conflict) Contacts have conflicting home address and matching primary (Mailing). Total giving = $500.
   *     Outcome: conflict - do not merge.
   *     Notes: This is like core, but for us less than 500 will merge.
   * 14) (Resolve Data)  Contacts have conflicting home address. Total giving < $500.
   *     Outcome: merge - only keep home address of latest donor. Keep Mailing.
   *     Notes: differs from core.
   *
   * @dataProvider getMergeLocationData
   *
   * @param array $dataSet
   */
  public function testBatchMergesAddressesHook($dataSet) {
    $this->contributionCreate(array('contact_id' => $this->contactID, 'receive_date' => '2010-01-01', 'invoice_id' => 1, 'trxn_id' => 1));
    $this->contributionCreate(array('contact_id' => $this->contactID2, 'receive_date' => '2012-01-01', 'invoice_id' => 2, 'trxn_id' => 2));
    if ($dataSet['is_major_gifts']) {
      $this->contributionCreate(array('contact_id' => $this->contactID2, 'receive_date' => '2012-01-01', 'total_amount' => 300));
    }
    foreach ($dataSet['contact_1'] as $address) {
      $this->callAPISuccess($dataSet['entity'], 'create', array_merge(array('contact_id' => $this->contactID), $address));
    }
    foreach ($dataSet['contact_2'] as $address) {
      $this->callAPISuccess($dataSet['entity'], 'create', array_merge(array('contact_id' => $this->contactID2), $address));
    }

    $result = $this->callAPISuccess('Job', 'process_batch_merge', array('mode' => 'safe'));

    $this->assertEquals($dataSet['skipped'], count($result['values']['skipped']));
    $this->assertEquals($dataSet['merged'], count($result['values']['merged']));
    $addresses = $this->callAPISuccess($dataSet['entity'], 'get', array('contact_id' => $this->contactID, 'sequential' => 1));
    $this->assertEquals(count($dataSet['expected_hook']), $addresses['count']);
    $locationTypes = $this->callAPISuccess($dataSet['entity'], 'getoptions', array('field' => 'location_type_id'));
    foreach ($dataSet['expected_hook'] as $index => $expectedAddress) {
      foreach ($expectedAddress as $key => $value) {
        if ($key == 'location_type_id') {
          $this->assertEquals($locationTypes['values'][$addresses['values'][$index][$key]], $value);
        }
        else {
          $this->assertEquals($value, $addresses['values'][$index][$key], $dataSet['entity'] . ': Unexpected value for ' . $key . (!empty($dataSet['description']) ? " on dataset {$dataSet['description']}" : ''));
        }
      }
    }
  }

  /**
   * Do address tests with contact ids reversed.
   *
   * Since the higher ID merges into the lower ID we were seeing accidental successes despite an error.
   *
   * This reversal of the previous test set forces it to work on logic not id co-incidence.
   *
   * @dataProvider getMergeLocationData
   *
   * @param array $dataSet
   */
  public function testBatchMergesAddressesHookLowerIDMoreRecentDonor($dataSet) {
    // here the lower contact ID has the higher receive_date as opposed to the previous test.
    $this->contributionCreate(array('contact_id' => $this->contactID2, 'receive_date' => '2010-01-01', 'invoice_id' => 1, 'trxn_id' => 1));
    $this->contributionCreate(array('contact_id' => $this->contactID, 'receive_date' => '2012-01-01', 'invoice_id' => 2, 'trxn_id' => 2));
    if ($dataSet['is_major_gifts']) {
      $this->contributionCreate(array('contact_id' => $this->contactID, 'receive_date' => '2012-01-01', 'total_amount' => 300));
    }
    foreach ($dataSet['contact_1'] as $address) {
      $this->callAPISuccess($dataSet['entity'], 'create', array_merge(array('contact_id' => $this->contactID2), $address));
    }
    foreach ($dataSet['contact_2'] as $address) {
      $this->callAPISuccess($dataSet['entity'], 'create', array_merge(array('contact_id' => $this->contactID), $address));
    }

    $result = $this->callAPISuccess('Job', 'process_batch_merge', array('mode' => 'safe'));

    $this->assertEquals($dataSet['skipped'], count($result['values']['skipped']));
    $this->assertEquals($dataSet['merged'], count($result['values']['merged']));
    if ($dataSet['merged']) {
      // higher contact merged into this so we are interested in this contact.
      $keptContact = $this->contactID;
    }
    else {
      // ie. no merge has taken place, so we just going to check our contact2 is unchanged.
      $keptContact = $this->contactID2;
    }
    $addresses = $this->callAPISuccess($dataSet['entity'], 'get', array(
      'contact_id' => $keptContact,
      'sequential' => 1,
    ));

    if (!empty($dataSet['fix_required_for_reverse'])) {
      return;
    }
    $this->assertEquals(count($dataSet['expected_hook']), $addresses['count']);
    $locationTypes = $this->callAPISuccess($dataSet['entity'], 'getoptions', array('field' => 'location_type_id'));
    foreach ($dataSet['expected_hook'] as $index => $expectedAddress) {
      foreach ($addresses['values'] as $index => $address) {
        // compared to the previous test the addresses are in a different order (for some datasets.
        // so, first find the matching address and then check it fully matches.
        // by unsetting afterwards we should find them all gone by the end.
        if (!empty($address['street_address']) && $address['street_address'] == $expectedAddress['street_address']
        || !empty($address['phone']) && $address['phone'] == $expectedAddress['phone']
        || !empty($address['email']) && $address['email'] == $expectedAddress['email']
        ) {
          foreach ($expectedAddress as $key => $value) {
            if ($key == 'location_type_id') {
              $this->assertEquals($locationTypes['values'][$addresses['values'][$index][$key]], $value);
            }
            else {
              $this->assertEquals($value, $addresses['values'][$index][$key], $dataSet['entity'] . ': Unexpected value for ' . $key . (!empty($dataSet['description']) ? " on dataset {$dataSet['description']}" : ''));
            }
          }
          unset($addresses['values'][$index]);
          continue;
        }
      }
    }
    $this->assertEmpty($addresses['values']);
  }

  /**
   * Test that a conflict on 'on_hold' is handled.
   */
  public function testBatchMergeConflictOnHold() {
    $emailDuck1 = $this->callAPISuccess('Email', 'get', array('contact_id' => $this->contactID, 'return' => 'id'));
    $emailDuck2 = $this->callAPISuccess('Email', 'get', array('contact_id' => $this->contactID2, 'return' => 'id'));

    $this->callAPISuccess('Email', 'create', array('id' => $emailDuck1['id'], 'on_hold' => 1));
    $result = $this->callAPISuccess('Job', 'process_batch_merge', array('mode' => 'safe'));
    $this->assertEquals(1, count($result['values']['skipped']));
    $this->assertEquals(0, count($result['values']['merged']));

    $this->callAPISuccess('Email', 'create', array('id' => $emailDuck1['id'], 'on_hold' => 0));
    $this->callAPISuccess('Email', 'create', array('id' => $emailDuck2['id'], 'on_hold' => 1));
    $result = $this->callAPISuccess('Job', 'process_batch_merge', array('mode' => 'safe'));
    $this->assertEquals(1, count($result['values']['skipped']));
    $this->assertEquals(0, count($result['values']['merged']));

    $this->callAPISuccess('Email', 'create', array('id' => $emailDuck1['id'], 'on_hold' => 1));
    $result = $this->callAPISuccess('Job', 'process_batch_merge', array('mode' => 'safe'));
    $this->assertEquals(0, count($result['values']['skipped']));
    $this->assertEquals(1, count($result['values']['merged']));
  }

  /**
   * Test that a conflict on communication preferences is handled.
   */
  public function testBatchMergeConflictCommunicationPreferences() {
    $this->callAPISuccess('Contact', 'create', array('id' => $this->contactID, 'do_not_email' => FALSE, 'is_opt_out' => TRUE));
    $this->callAPISuccess('Contact', 'create', array('id' => $this->contactID2, 'do_not_email' => TRUE, 'is_opt_out' => FALSE));

    $result = $this->callAPISuccess('Job', 'process_batch_merge', array('mode' => 'safe'));
    $this->assertEquals(0, count($result['values']['skipped']));
    $this->assertEquals(1, count($result['values']['merged']));

    $contact = $this->callAPISuccess('Contact', 'get', array('id' => $this->contactID, 'sequential' => 1));
    $this->assertEquals(1, $contact['values'][0]['is_opt_out']);
    $this->assertEquals(1, $contact['values'][0]['do_not_email']);
  }

  /**
   * Test that source conflicts are ignored.
   *
   * We don't care enough about source it seems to do much with it.
   *
   * Bug T146946
   */
  public function testBatchMergeConflictSource() {
    $this->breedDuck(array('id' => $this->contactID, 'source' => 'egg'));
    $this->breedDuck(array('id' => $this->contactID2, 'source' => 'chicken'));
    $result = $this->callAPISuccess('Job', 'process_batch_merge', array('mode' => 'safe'));
    $this->assertEquals(0, count($result['values']['skipped']));
    $this->assertEquals(1, count($result['values']['merged']));
  }

  /**
   * Test that a conflict on casing in first names is handled.
   *
   * We do a best effort on this to get the more correct on assuming that 1 capital letter in a
   * name is most likely to be deliberate. We prioritise less capital letters over more, except that
   * all lower case is at the end of the queue.
   *
   * This won't necessarily give us the best results for 'La Plante' vs 'la Plante' but we should bear in mind
   * - both variants have been entered by the user at some point so they have not 'chosen' one.
   * - having 2 variants of the spelling of a name with more than one upper case letter in our
   * db is an edge case.
   */
  public function testBatchMergeConflictNameCasing() {
    $this->callAPISuccess('Contact', 'create', array('id' => $this->contactID, 'first_name' => 'donald', 'last_name' => 'Duck'));
    $this->callAPISuccess('Contact', 'create', array('id' => $this->contactID2, 'first_name' => 'Donald', 'last_name' => 'duck'));
    $this->breedDuck(array('first_name' => 'DONALD', 'last_name' => 'DUCK'));
    $this->breedDuck(array('first_name' => 'DonalD', 'last_name' => 'DUck'));
    $result = $this->callAPISuccess('Job', 'process_batch_merge', array('mode' => 'safe'));
    $this->assertEquals(0, count($result['values']['skipped']));
    $this->assertEquals(3, count($result['values']['merged']));

    $contact = $this->callAPISuccess('Contact', 'get', array('id' => $this->contactID, 'sequential' => 1));
    $this->assertEquals('Donald', $contact['values'][0]['first_name']);
    $this->assertEquals('Duck', $contact['values'][0]['last_name']);
  }

  /**
   * Get address combinations for the merge test.
   *
   * @return array
   */
  public function getMergeLocationData() {
    $address1 = array('street_address' => 'Buckingham Palace', 'city' => 'London');
    $address2 = array('street_address' => 'The Doghouse', 'supplemental_address_1' => 'under the blanket');
    $address3 = array('street_address' => 'Downton Abbey');
    $data = $this->getMergeLocations($address1, $address2, $address3, 'Address');
    $data = array_merge($data, $this->getMergeLocations(
      array('phone' => '12345', 'phone_type_id' => 1),
      array('phone' => '678910', 'phone_type_id' => 1),
      array('phone' => '999888', 'phone_type_id' => 1),
      'Phone')
    );
    $data = array_merge($data, $this->getMergeLocations(array('phone' => '12345'), array('phone' => '678910'), array('phone' => '678999'), 'Phone'));
    $data = array_merge($data, $this->getMergeLocations(
      array('email' => 'mini@me.com'),
      array('email' => 'mini@me.org'),
      array('email' => 'mini@me.co.nz'),
      'Email',
      array(array(
        'email' => 'the_don@duckland.com',
        'location_type_id' => 'Work',
    ))));
    return $data;

  }

  /**
   * Get the location data set.
   *
   * @param array $locationParams1
   * @param array $locationParams2
   * @param string $entity
   * @param array $additionalExpected
   *
   * @return array
   */
  public function getMergeLocations($locationParams1, $locationParams2, $locationParams3, $entity, $additionalExpected = array()) {
    $data = array(
      'matching_primary' => array(
        'matching_primary' => array(
          'merged' => 1,
          'skipped' => 0,
          'is_major_gifts' => 1,
          'description' => 'Same behaviour with & without the hook, matching primary AND other address maintained',
          'entity' => $entity,
          'contact_1' => array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams1),
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 0,
            ), $locationParams2),
          ),
          'contact_2' => array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams1),
          ),
          'expected_hook' => array_merge($additionalExpected, array(
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 0,
            ), $locationParams2),
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams1),
          )),
        ),
      ),
      'matching_primary_reverse' => array(
        'matching_primary_reverse' => array(
          'merged' => 1,
          'skipped' => 0,
          'is_major_gifts' => 1,
          'description' => 'Same behaviour with & without the hook, matching primary AND other address maintained',
          'entity' => $entity,
          'contact_1' => array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams1),
          ),
          'contact_2' => array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams1),
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 0,
            ), $locationParams2),
          ),
          'expected_hook' => array_merge($additionalExpected, array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams1),
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 0,
            ), $locationParams2),
          )),
        ),
      ),
      'only_one_has_address' => array(
        'only_one_has_address' => array(
          'merged' => 1,
          'skipped' => 0,
          'is_major_gifts' => 1,
          'description' => 'Same behaviour with & without the hook, address is maintained',
          'entity' => $entity,
          'contact_1' => array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams1),
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 0,
            ), $locationParams2),
          ),
          'contact_2' => array(),
          'expected_hook' => array_merge($additionalExpected, array(
            array_merge(array(
              'location_type_id' => 'Home',
              // When dealing with email we don't have a clean slate - the existing
              // primary will be primary.
              'is_primary' => ($entity == 'Email' ? 0 : 1),
            ), $locationParams1),
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 0,
            ), $locationParams2),
          )),
        ),
      ),
      'only_one_has_address_reverse' => array(
        'only_one_has_address_reverse' => array(
          'merged' => 1,
          'skipped' => 0,
          'is_major_gifts' => 1,
          'description' => 'Same behaviour with & without the hook, address is maintained',
          'entity' => $entity,
          'contact_1' => array(),
          'contact_2' => array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams1),
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 0,
            ), $locationParams2),
          ),
          'expected_hook' => array_merge($additionalExpected, array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams1),
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 0,
            ), $locationParams2),
          )),
        ),
      ),
      'different_primaries_with_different_location_type' => array(
        'different_primaries_with_different_location_type' => array(
          'merged' => 1,
          'skipped' => 0,
          'is_major_gifts' => 1,
          'description' => 'Primaries are different with different location. Keep both addresses. Set primary to be that of more recent donor',
          'entity' => $entity,
          'contact_1' => array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams1),
          ),
          'contact_2' => array(
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 1,
            ), $locationParams2),
          ),
          'expected_hook' => array_merge($additionalExpected, array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 0,
            ), $locationParams1),
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 1,
            ), $locationParams2),
          )),
        ),
      ),
      'different_primaries_with_different_location_type_reverse' => array(
        'different_primaries_with_different_location_type_reverse' => array(
          'merged' => 1,
          'skipped' => 0,
          'is_major_gifts' => 1,
          'entity' => $entity,
          'contact_1' => array(
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 1,
            ), $locationParams2),
          ),
          'contact_2' => array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams1),
          ),
          'expected_hook' => array_merge($additionalExpected, array(
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 0,
            ), $locationParams2),
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams1),
          )),
        ),
      ),
      'different_primaries_location_match_only_one_address' => array(
        'different_primaries_location_match_only_one_address' => array(
          'merged' => 1,
          'skipped' => 0,
          'is_major_gifts' => 1,
          'entity' => $entity,
          'contact_1' => array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams1),
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 0,
            ), $locationParams2),
          ),
          'contact_2' => array(
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 1,
            ), $locationParams2),

          ),
          'expected_hook' => array_merge($additionalExpected, array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 0,
            ), $locationParams1),
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 1,
            ), $locationParams2),
          )),
        ),
      ),
      'different_primaries_location_match_only_one_address_reverse' => array(
        'different_primaries_location_match_only_one_address_reverse' => array(
          'merged' => 1,
          'skipped' => 0,
          'is_major_gifts' => 1,
          'entity' => $entity,
          'contact_1' => array(
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 1,
            ), $locationParams2),
          ),
          'contact_2' => array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams1),
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 0,
            ), $locationParams2),
          ),
          'expected_hook' => array_merge($additionalExpected, array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams1),
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 0,
            ), $locationParams2),
          )),
        ),
      ),
      'same_primaries_different_location' => array(
        'same_primaries_different_location' => array(
          'fix_required_for_reverse' => 1,
          'comment' => 'core is not identifying this as an address conflict in reverse order'
          . ' this is not an issue at the moment as it only happens in reverse from the'
          . 'form merge - where we do not intervene',
          'merged' => 1,
          'skipped' => 0,
          'is_major_gifts' => 1,
          'entity' => $entity,
          'contact_1' => array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams1),
          ),
          'contact_2' => array(
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 1,
            ), $locationParams1),

          ),
          'expected_hook' => array_merge($additionalExpected, array(
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 1,
            ), $locationParams1),
          )),
        ),
      ),
      'same_primaries_different_location_reverse' => array(
        'same_primaries_different_location_reverse' => array(
          'fix_required_for_reverse' => 1,
          'comment' => 'core is not identifying this as an address conflict in reverse order'
            . ' this is not an issue at the moment as it only happens in reverse from the'
            . 'form merge - where we do not intervene',
          'merged' => 1,
          'skipped' => 0,
          'is_major_gifts' => 1,
          'entity' => $entity,
          'contact_1' => array(
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 1,
            ), $locationParams1),
          ),
          'contact_2' => array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams1),
          ),
          'expected_hook' => array_merge($additionalExpected, array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams1),
          )),
        ),
      ),
      'conflicting_home_address_major_gifts' => array(
        'conflicting_home_address_major_gifts' => array(
          'merged' => 0,
          'skipped' => 1,
          'is_major_gifts' => 1,
          'entity' => $entity,
          'contact_1' => array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams1),
          ),
          'contact_2' => array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams2),
          ),
          'expected_hook' => array_merge($additionalExpected, array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams1),
          )),
        ),
      ),
      'conflicting_home_address_not_major_gifts' => array(
        'conflicting_home_address_not_major_gifts' => array(
          'fix_required_for_reverse' => 1,
          'comment' => 'our code needs an update as both are being kept'
            . ' this is not an issue at the moment as it only happens in reverse from the'
            . 'form merge - where we do not intervene',
          'merged' => 1,
          'skipped' => 0,
          'is_major_gifts' => 0,
          'entity' => $entity,
          'contact_1' => array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams1),
          ),
          'contact_2' => array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams2),
          ),
          'expected_hook' => array_merge($additionalExpected, array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams2),
          )),
        ),
      ),
      'conflicting_home_address_one_more_major_gifts' => array(
        'conflicting_home_address_one_more_major_gifts' => array(
          'merged' => 0,
          'skipped' => 1,
          'is_major_gifts' => 1,
          'entity' => $entity,
          'contact_1' => array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 0,
            ), $locationParams1),
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 1,
            ), $locationParams2),
          ),
          'contact_2' => array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 0,
            ), $locationParams3),
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 1,
            ), $locationParams2),
          ),
          'expected_hook' => array_merge($additionalExpected, array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 0,
            ), $locationParams1),
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 1,
            ), $locationParams2),
          )),
        ),
      ),
      'conflicting_home_address__one_more_not_major_gifts' => array(
        'conflicting_home_address__one_more_not_major_gifts' => array(
          'fix_required_for_reverse' => 1,
          'comment' => 'our code needs an update as an extra 1 is being kept'
            . ' this is not an issue at the moment as it only happens in reverse from the'
            . 'form merge - where we do not intervene',
          'merged' => 1,
          'skipped' => 0,
          'is_major_gifts' => 0,
          'entity' => $entity,
          'contact_1' => array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 0,
            ), $locationParams1),
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 1,
            ), $locationParams2),
          ),
          'contact_2' => array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 0,
            ), $locationParams3),
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 1,
            ), $locationParams2),
          ),
          'expected_hook' => array_merge($additionalExpected, array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 0,
            ), $locationParams3),
            array_merge(array(
              'location_type_id' => 'Mailing',
              'is_primary' => 1,
            ), $locationParams2),
          )),
        ),
      ),
      'duplicate_home_address_on_one_contact' => array(
        'duplicate_home_address_on_one_contact' => array(
          'fix_required_for_reverse' => 1,
          'comment' => 'our code needs an update as an extra 1 is being kept'
            . ' this is not an issue at the moment as it only happens in reverse from the'
            . 'form merge - where we do not intervene',
          'merged' => 1,
          'skipped' => 0,
          'is_major_gifts' => 0,
          'entity' => $entity,
          'contact_1' => array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 0,
            ), $locationParams1),
          ),
          'contact_2' => array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams1),
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 0,
            ), $locationParams1),
          ),
          'expected_hook' => array_merge($additionalExpected, array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams1),
          )),
        ),
      ),
      'duplicate_home_address_on_one_contact_second_primary' => array(
        'duplicate_home_address_on_one_contact_second_primary' => array(
          'fix_required_for_reverse' => 1,
          'comment' => 'our code needs an update as an extra 1 is being kept'
            . ' this is not an issue at the moment as it only happens in reverse from the'
            . 'form merge - where we do not intervene',
          'merged' => 1,
          'skipped' => 0,
          'is_major_gifts' => 0,
          'entity' => $entity,
          'contact_1' => array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 0,
            ), $locationParams1),
          ),
          'contact_2' => array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 0,
            ), $locationParams1),
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams1),
          ),
          'expected_hook' => array_merge($additionalExpected, array(
            array_merge(array(
              'location_type_id' => 'Home',
              'is_primary' => 1,
            ), $locationParams1),
          )),
        ),
      ),

    );
    return $data;
  }


  /**
   * Clean up previous runs.
   *
   * Also get rid of the nest.
   */
  protected function doDuckHunt() {
    CRM_Core_DAO::executeQuery('DELETE FROM civicrm_contact WHERE display_name = "Donald Duck"');
    CRM_Core_DAO::executeQuery('DELETE FROM civicrm_prevnext_cache');
  }

  /**
   * Create contribution.
   *
   * @param array $params
   *   Array of parameters.
   *
   * @return int
   *   id of created contribution
   */
  public function contributionCreate($params) {

    $params = array_merge(array(
      'receive_date' => date('Ymd'),
      'total_amount' => 100.00,
      'fee_amount' => 5.00,
      'net_ammount' => 95.00,
      'financial_type_id' => 1,
      'payment_instrument_id' => 1,
      'non_deductible_amount' => 10.00,
      'contribution_status_id' => 1,
    ), $params);

    $result = $this->callAPISuccess('contribution', 'create', $params);
    return $result['id'];
  }

  /**
   * Create a test duck.
   *
   * @param array $extraParams
   *   Any overrides to be added to the create call.
   *
   * @return int
   */
  public function breedDuck($extraParams = array()) {
    $contact = $this->callAPISuccess('Contact', 'create', array_merge(array(
      'contact_type' => 'Individual',
      'first_name' => 'Donald',
      'last_name' => 'Duck',
      'api.email.create' => array(
        'email' => 'the_don@duckland.com',
        'location_type_id' => 'Work'
      ),
    ), $extraParams));
    return $contact['id'];
  }

}
