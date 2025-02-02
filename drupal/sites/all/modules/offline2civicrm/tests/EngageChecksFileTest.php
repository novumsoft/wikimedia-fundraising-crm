<?php

use Civi\Api4\Address;
use Civi\Api4\Contact;

/**
 * @group Import
 * @group Offline2Civicrm
 */
class EngageChecksFileTest extends BaseChecksFileTest {

  protected $sourceFileUri = '';

  public function setUp(): void {
    $this->ensureAnonymousContactExists();
    parent::setUp();
    require_once __DIR__ . '/includes/EngageChecksFileProbe.php';
  }

  public function testParseRow_Individual(): void {
    $data = [
      'Batch' => '1234',
      'Contribution Type' => 'Engage',
      'Total Amount' => '50',
      'Source' => 'USD 50.00',
      'Postmark Date' => '',
      'Received Date' => '4/1/14',
      'Payment Instrument' => 'Check',
      'Check Number' => '2020',
      'Restrictions' => 'Unrestricted - General',
      'Gift Source' => 'Community Gift',
      'Direct Mail Appeal' => 'White Mail',
      'Prefix' => 'Mrs.',
      'First Name' => 'Sub',
      'Last Name' => 'Tell',
      'Suffix' => '',
      'Street Address' => '1000 Markdown Markov',
      'Additional Address 1' => '',
      'Additional Address 2' => '',
      'City' => 'Best St. Louis',
      'State' => 'MA',
      'Postal Code' => '2468',
      'Country' => '',
      'Phone' => '(123) 456-0000',
      'Email' => '',
      'Thank You Letter Date' => '5/1/14',
      'AC Flag' => 'Y',
    ];
    $expected_normal = [
      'check_number' => '2020',
      'city' => 'Best St. Louis',
      'contact_source' => 'check',
      'contact_type' => 'Individual',
      'contribution_source' => 'USD 50.00',
      'contribution_type' => 'Engage',
      'country' => 'US',
      'currency' => 'USD',
      'date' => 1396310400,
      'direct_mail_appeal' => 'White Mail',
      'first_name' => 'Sub',
      'gateway' => 'engage',
      'gateway_txn_id' => 'e59ed825ea04516fb2abf1c130d47525',
      'gift_source' => 'Community Gift',
      'gross' => '50',
      'import_batch_number' => '1234',
      'last_name' => 'Tell',
      'name_prefix' => 'Mrs.',
      'payment_method' => 'Check',
      'postal_code' => '02468',
      'raw_contribution_type' => 'Engage',
      'restrictions' => 'Unrestricted - General',
      'state_province' => 'MA',
      'street_address' => '1000 Markdown Markov',
      'thankyou_date' => 1398902400,
      'contact_id' => NULL,
    ];

    $importer = new EngageChecksFileProbe();
    $output = $importer->_parseRow($data);

    $this->stripSourceData($output);
    $this->assertEquals($expected_normal, $output);
  }

  /**
   * Test for parse row function.
   */
  public function testParseRow_Organization(): void {
    $data = [
      'Batch' => '1235',
      'Contribution Type' => 'Engage',
      'Total Amount' => '51.23',
      'Source' => 'USD 51.23',
      'Postmark Date' => '',
      'Received Date' => '4/1/14',
      'Payment Instrument' => 'Check',
      'Check Number' => '202000001',
      'Restrictions' => 'Restricted-Foundation',
      'Gift Source' => 'Foundation Gift',
      'Direct Mail Appeal' => 'White Mail',
      'Organization Name' => 'One Pacific Entitlement',
      'Street Address' => '1000 Markdown Markov',
      'Additional Address 1' => '',
      'Additional Address 2' => '',
      'City' => 'Best St. Louis',
      'State' => 'MA',
      'Postal Code' => '123-LAX',
      'Country' => 'FR',
      'Phone' => '+357 (123) 456-0000',
      'Email' => '',
      'Thank You Letter Date' => '5/1/14',
      'AC Flag' => '',
    ];
    $expected_normal = [
      'check_number' => '202000001',
      'city' => 'Best St. Louis',
      'contact_source' => 'check',
      'contact_type' => 'Organization',
      'contribution_source' => 'USD 51.23',
      'contribution_type' => 'Engage',
      'country' => 'FR',
      'currency' => 'USD',
      'date' => 1396310400,
      'direct_mail_appeal' => 'White Mail',
      'gateway' => 'engage',
      'gateway_txn_id' => '6dbb8d844c7509076e2a275fb76d0130',
      'gift_source' => 'Foundation Gift',
      'gross' => 51.23,
      'import_batch_number' => '1235',
      'organization_name' => 'One Pacific Entitlement',
      'payment_method' => 'Check',
      'postal_code' => '123-LAX',
      'raw_contribution_type' => 'Engage',
      'restrictions' => 'Restricted-Foundation',
      'state_province' => 'MA',
      'street_address' => '1000 Markdown Markov',
      'thankyou_date' => 1398902400,
      'contact_id' => NULL,
    ];

    $importer = new EngageChecksFileProbe();
    $output = $importer->_parseRow($data);

    $this->stripSourceData($output);
    $this->assertEquals($expected_normal, $output);
  }

  /**
   * @throws \CRM_Core_Exception
   * @throws \League\Csv\Exception
   * @throws \Civi\WMFException\WMFException
   */
  public function testImporterFormatsPostal(): void {
    $fileUri = $this->setupFile('engage_postal.csv');

    $importer = new EngageChecksFile($fileUri);
    $importer->import();
    $contact = $this->callAPISuccess('Contact', 'get', [
      'email' => 'rsimpson4@unblog.fr',
      'sequential' => 1,
    ]);
    $this->assertEquals('07065', $contact['values'][0]['postal_code']);
    $this->assertEquals(5, strlen($contact['values'][0]['postal_code']));
  }

  /**
   * Test that an address is made on address where email is empty.
   *
   * @throws \API_Exception
   * @throws \CRM_Core_Exception
   * @throws \Civi\WMFException\WMFException
   * @throws \League\Csv\Exception
   */
  public function testImportAddressMatch(): void {
    $contact = Contact::create(FALSE)->setValues([
      'contact_type' => 'Individual',
      'first_name' => 'Very',
      'last_name' => 'Unique'
    ])->addChain('address', Address::create(FALSE)->setValues([
      'street_address' => '22 Maple Lane',
      'city' => 'Houston',
      'postal_code' => '07654',
      'location_type_id' => 1,
      'contact_id' => '$id',
    ]))->execute()->first();

    $fileUri = $this->setupFile('engage_address_match.csv');
    $importer = new EngageChecksFile($fileUri);
    $importer->import();
    // Check the contribution was added to our existing contact.
    $contributions = $this->callAPISuccess('Contribution', 'get', ['contact_id' => $contact['id']]);
    $this->assertEquals(1, $contributions['count']);
  }

  /**
   * Test that import matches existing contact (Minnie) on single match (email
   * present).
   *
   * The address is different and should result in an UPDATE on email match.
   *
   * Also check the anonymous contribution is matched to the existing anonymous
   * user.
   *
   * @throws \CRM_Core_Exception
   * @throws \League\Csv\Exception
   * @throws \Civi\WMFException\WMFException
   */
  public function testImportSucceedIndividualSingleContactExistsEmailMatch(): void {
    $minnie = $this->callAPISuccess('Contact', 'create', [
      'first_name' => 'Minnie',
      'last_name' => 'Mouse',
      'contact_type' => 'Individual',
      'email' => 'minnie@example.com',
      'api.address.create' => [
        'postal_code' => 98210,
        'street_address' => '35 Mousey Lane',
        'location_type_id' => 'Home',
      ],
    ]);

    $this->importCheckFile();

    $contributions = $this->callAPISuccess('Contribution', 'get', ['contact_id' => $minnie['id']]);
    $this->assertEquals(1, $contributions['count']);
    $address = $this->callAPISuccessGetSingle('Address', ['contact_id' => $minnie['id']]);
    $this->assertEquals('35 Squeaky Way', $address['street_address']);
    $minnie = $this->callAPISuccessGetSingle('Contact', ['id' => $minnie['id']]);
    $this->assertEquals(1, $minnie['do_not_email']);
    $this->assertEquals(1, $minnie['do_not_sms']);
    $this->assertEquals(1, $minnie['do_not_phone']);
    $this->assertEquals(1, $minnie['is_opt_out']);

    // Check anonymous contact too.
    $anonymousContact = $anonymousContact = $this->callAPISuccessGetSingle('Contact', ['email' => 'fakeemail@wikimedia.org']);
    $this->assertEquals('Anonymous', $anonymousContact['first_name']);
    $this->assertEquals('Anonymous', $anonymousContact['last_name']);
    $this->callAPISuccessGetSingle('Contribution', ['contact_id' => $anonymousContact['id'], 'trxn_id' => 'ENGAGE 1F46761510A95FC3FFE271B928231E55']);
  }

  /**
   * Test that import matches existing contact (Daisy) on single match on
   * address.
   *
   * We are looking for a match based on ALL of the following
   * - first_name
   * - last_name
   * - street_address
   * - city
   * - postal_code
   *
   * In this test there is only 1 & we choose that.
   *
   * @throws \CRM_Core_Exception
   * @throws \League\Csv\Exception
   * @throws \Civi\WMFException\WMFException
   */
  public function testImportSucceedIndividualSingleContactExistsAddressMatch(): void {

    $daisy = $this->callAPISuccess('Contact', 'create', [
      'first_name' => 'Daisy',
      'last_name' => 'Duck',
      'contact_type' => 'Individual',
      'api.address.create' => [
        'city' => 'Duckville',
        'postal_code' => '10210',
        'street_address' => '1 15th Avenue.',
        'location_type_id' => 'Home',
      ],
    ]);

    $this->importCheckFile();

    $contributions = $this->callAPISuccess('Contribution', 'get', ['contact_id' => $daisy['id']]);
    $this->assertEquals(1, $contributions['count']);
  }

  /**
   * Test that import doesn't match existing deleted contact with same info
   *
   * @throws \CRM_Core_Exception
   * @throws \League\Csv\Exception
   * @throws \Civi\WMFException\WMFException
   */
  public function testImportSucceedIndividualSingleContactExistsDeleted(): void {

    $daisy = $this->callAPISuccess('Contact', 'create', [
      'first_name' => 'Daisy',
      'last_name' => 'Duck',
      'contact_type' => 'Individual',
      'is_deleted' => 1,
      'api.address.create' => [
        'city' => 'Duckville',
        'postal_code' => '10210',
        'street_address' => '1 15th Avenue.',
        'location_type_id' => 'Home',
      ],
    ]);

    $this->importCheckFile();

    $contributions = $this->callAPISuccess('Contribution', 'get', ['contact_id' => $daisy['id']]);
    $this->assertEquals(0, $contributions['count']);
    $newDaisy = $this->callAPISuccess('Contact', 'get', [
      'first_name' => 'Daisy',
      'last_name' => 'Duck',
      'contact_type' => 'Individual',
      'is_deleted' => 0,
      'options' => [
        'sort' => 'id DESC',
        'limit' => 1,
      ],
    ]);
    // Should have created a new contact to attach the contribution to
    $this->assertGreaterThan($daisy['id'], $newDaisy['id']);
    $newContribs = $this->callAPISuccess('Contribution', 'get', ['contact_id' => $newDaisy['id']]);
    $this->assertEquals(1, $newContribs['count']);

  }

  /**
   * Test that import matches existing contact (Daisy) on multiple match on
   * address.
   *
   *  We have 4 Daisys. We should choose the one with the most recent
   * contribution
   *
   * We are looking for a match based on ALL of the following
   * - first_name
   * - last_name
   * - street_address
   * - city
   * - postal_code
   *
   * We choose the most recent.
   *
   * @throws \CRM_Core_Exception
   * @throws \League\Csv\Exception
   * @throws \Civi\WMFException\WMFException
   */
  public function testImportSucceedIndividualMultipleContactExistsAddressMatchOnBestDaisy(): void {
    $daisy = [];
    for ($i = 0; $i < 4; $i++) {
      $daisy[$i] = $this->callAPISuccess('Contact', 'create', [
        'first_name' => 'Daisy',
        'last_name' => 'Duck',
        'contact_type' => 'Individual',
        'api.address.create' => [
          'city' => 'Duckville',
          'postal_code' => '10210',
          'street_address' => '1 15th Avenue',
          'location_type_id' => 'Home',
        ],
      ]);
      // The second is the most recent.
      $dates = [0 => '2015-09-09', 1 => '2017-12-12', 2 => NULL, 3 => '2016-10-10'];
      if ($dates[$i]) {
        $this->callAPISuccess('Contribution', 'create', [
          'contact_id' => $daisy[$i]['id'],
          'financial_type_id' => 'Donation',
          'receive_date' => $dates[$i],
          'total_amount' => 700,
        ]);
      }
    }

    $this->importCheckFile();

    $this->callAPISuccessGetSingle('Contribution', [
      'contact_id' => $daisy[1]['id'],
      'trxn_id' => 'ENGAGE 505C30160D9BD138D57A6ACE5151E0CD',
    ]);
  }

  /**
   * Test that import matches existing contact (Daisy) on multiple match on
   * address.
   *
   * In this case the address matches a non primary address.
   *
   *  We have 4 Daisys. We should choose the one with the most recent
   * contribution
   *
   * We are looking for a match based on ALL of the following
   * - first_name
   * - last_name
   * - street_address
   * - city
   * - postal_code
   *
   * We choose the most recent.
   *
   * @throws \CRM_Core_Exception
   * @throws \League\Csv\Exception
   * @throws \Civi\WMFException\WMFException
   */
  public function testImportSucceedIndividualMultipleContactExistsNonPrimaryAddressMatchOnBestDaisy(): void {
    $daisy = [];
    for ($i = 0; $i < 4; $i++) {
      $daisy[$i] = $this->callAPISuccess('Contact', 'create', [
        'first_name' => 'Daisy',
        'last_name' => 'Duck',
        'contact_type' => 'Individual',
        'api.address.create' => [
          'city' => 'Duckville',
          'postal_code' => '10210',
          'street_address' => '1 15th Avenue',
          'location_type_id' => 'Home',
        ],
        'api.address.create.2' => [
          'city' => 'Waddles Rest',
          'postal_code' => '10210',
          'street_address' => '1 15th Avenue',
          'location_type_id' => 'Home',
          'is_primary' => 1,
        ],
      ]);
      // The second is the most recent.
      $dates = [0 => '2015-09-09', 1 => '2017-12-12', 2 => NULL, 3 => '2016-10-10'];
      if ($dates[$i]) {
        $this->callAPISuccess('Contribution', 'create', [
          'contact_id' => $daisy[$i]['id'],
          'financial_type_id' => 'Donation',
          'receive_date' => $dates[$i],
          'total_amount' => 700,
        ]);
      }
    }

    $this->importCheckFile();

    $this->callAPISuccessGetSingle('Contribution', [
      'contact_id' => $daisy[1]['id'],
      'trxn_id' => 'ENGAGE 505C30160D9BD138D57A6ACE5151E0CD',
    ]);
  }

  /**
   * Test that import matches existing contact (Villains Ltd) on multiple match
   * on address.
   *
   * We are looking for a match based on ALL of the following
   * - organization_name
   * - street_address
   * - city
   * - postal_code
   *
   * We choose the most recent donor
   *
   * @throws \CRM_Core_Exception
   * @throws \League\Csv\Exception
   * @throws \Civi\WMFException\WMFException
   */
  public function testImportSucceedOrganizationMultipleContactExistsAddressMatchOnBestVillain(): void {
    $this->sourceFileUri = __DIR__ . "/data/engage_org_import.csv";

    $villains = [];
    for ($i = 0; $i < 4; $i++) {
      $villains[$i] = $this->callAPISuccess('Contact', 'create', [
        'organization_name' => 'Villains Ltd',
        'contact_type' => 'Organization',
        'api.address.create' => [
          'city' => 'Henchman City',
          'postal_code' => '90210',
          'street_address' => 'PO Box 666',
          'location_type_id' => 'Home',
        ],
      ]);
      // The second is the most recent.
      $dates = [0 => '2015-09-09', 1 => '2017-12-12', 2 => NULL, 3 => '2016-10-10'];
      if ($dates[$i]) {
        $this->callAPISuccess('Contribution', 'create', [
          'contact_id' => $villains[$i]['id'],
          'financial_type_id' => 'Donation',
          'receive_date' => $dates[$i],
          'total_amount' => 700,
        ]);
      }
    }

    $this->importCheckFile();

    $this->callAPISuccessGetSingle('Contribution', [
      'contact_id' => $villains[1]['id'],
      'trxn_id' => 'ENGAGE B525137FE24A217918BE1B3AFF5AA25B',
    ]);
    $villain = Contact::get(FALSE)->addWhere('id', '=', $villains[1]['id'])->setSelect([
      'Organization_Contact.Name',
      'Organization_Contact.Email',
      'Organization_Contact.Phone',
      'Organization_Contact.Title',
    ])->execute()->first();
    $this->assertEquals('Bob', $villain['Organization_Contact.Name']);
    $this->assertEquals('wiseone@example.com', $villain['Organization_Contact.Email']);
    $this->assertEquals('991', $villain['Organization_Contact.Phone']);
    $this->assertEquals('The wise', $villain['Organization_Contact.Title']);
  }

  /**
   * Basic import of individual contact.
   */
  /**
   * @throws \API_Exception
   * @throws \CRM_Core_Exception
   * @throws \League\Csv\Exception
   * @throws \Civi\WMFException\WMFException
   */
  public function testImportIndividual(): void {
    $fileUri = $this->setupFile('engage_individual.csv');
    $importer = new EngageChecksFile($fileUri);
    $importer->import();
    $contact = Contact::get()
      ->setCheckPermissions(FALSE)
      ->addWhere('first_name', '=', 'Rambo')
      ->addWhere('last_name', '=', 'Mouse')
      ->addJoin('Contribution AS contribution')
      ->addSelect('Partner.Partner', 'contribution.total_amount', 'contribution.fee_amount', 'contribution.net_amount', 'contribution.trxn_id')->execute()->first();

    $this->assertEquals(0.5, $contact['contribution.fee_amount']);
    $this->assertEquals(23.5, $contact['contribution.net_amount']);
    $this->assertEquals(24.0, $contact['contribution.total_amount']);
    $this->assertEquals('ENGAGE 123', $contact['contribution.trxn_id']);
    $this->assertEquals('Walt', $contact['Partner.Partner']);
  }

  /**
   * Test that import matches existing contact (Minnie) on multiple match
   * (email present).
   *
   * We have 4 Minnies. We should choose the one with the most recent
   * contribution
   *
   * We should blank out any portion of the address we do not have.
   *
   * @throws \CRM_Core_Exception
   * @throws \League\Csv\Exception
   * @throws \Civi\WMFException\WMFException
   */
  public function testImportSucceedIndividualMultipleContactExistsEmailMatchOnBestMinnie(): void {
    $minnies = $this->createContactSet([
      'first_name' => 'Minnie',
      'last_name' => 'Mouse',
      'contact_type' => 'Individual',
      'email' => 'minnie@example.com',
      'api.address.create' => [
        'postal_code' => 98210,
        'street_address' => '35 Mousey Lane',
        'location_type_id' => 'Home',
        'city' => 'Mouseville',
      ],
    ]);

    $this->importCheckFile();

    // Check Minnie 1 has the contribution.
    $this->callAPISuccessGetSingle('Contribution', [
        'trxn_id' => 'ENGAGE 2FF5DCA37146BF766F8658855EA5471F',
        'contact_id' => $minnies[1]['id'],
      ]
    );

    $address = $this->callAPISuccessGetSingle('Address', ['contact_id' => $minnies[1]['id']]);
    $this->assertEquals('35 Squeaky Way', $address['street_address']);
    $this->assertTrue(empty($address['city']));
  }

  /**
   * Test that import matches existing contact (Good Guys Inc.) on single match
   * (email present).
   *
   * The address is different and should result in an UPDATE on email match.
   *
   * @throws \CRM_Core_Exception
   * @throws \League\Csv\Exception
   * @throws \Civi\WMFException\WMFException
   */
  public function testImportSucceedOrganizationSingleContactExistsEmailMatch(): void {
    $goodie = $this->callAPISuccess('Contact', 'create', [
      'organization_name' => 'Good Guys Inc.',
      'contact_type' => 'Organization',
      'email' => 'goodies@example.com',
      'api.address.create' => [
        'postal_code' => 98210,
        'street_address' => '35 Goodies Lane',
        'location_type_id' => 'Home',
      ],
    ]);
    $this->sourceFileUri = __DIR__ . "/data/engage_org_import.csv";
    $this->importCheckFile();

    $contributions = $this->callAPISuccess('Contribution', 'get', ['contact_id' => $goodie['id']]);
    $this->assertEquals(1, $contributions['count']);
    $address = $this->callAPISuccessGetSingle('Address', ['contact_id' => $goodie['id']]);
    $this->assertEquals('100 95th St 51th Floor', $address['street_address']);
  }

  /**
   * Test that import matches existing contact (Good Guys Inc.) in a multiple
   * match (email not primary).
   *
   * @throws \CRM_Core_Exception
   * @throws \League\Csv\Exception
   * @throws \Civi\WMFException\WMFException
   * @throws \API_Exception
   */
  public function testImportSucceedOrganizationMultipleContactsExistsEmailMatchNonPrimary(): void {
    $goodies = $this->createContactSet([
      'organization_name' => 'Good Guys Inc.',
      'contact_type' => 'Organization',
      'email' => 'goodish@example.com',
      'api.address.create' => [
        'postal_code' => 98210,
        'street_address' => '35 Goodies Lane',
        'location_type_id' => 'Home',
      ],
      'api.email.create' => [
        'email' => 'goodies@example.com',
        'location_type_id' => 'Work',
      ],
    ]);
    $goodyID = $goodies[1]['id'];

    $goody = $this->callAPISuccessGetSingle('Contact', [
      'id' => $goodyID,
      'return' => 'email',
    ]);
    // Note that goodish is primary
    $this->assertEquals('goodish@example.com', $goody['email']);

    $this->sourceFileUri = __DIR__ . "/data/engage_org_import.csv";
    $this->importCheckFile();

    $this->callAPISuccessGetSingle('Contribution', [
      'contact_id' => $goodyID,
      'trxn_id' => 'ENGAGE 26A0CCB4CDD020E6CFA16BFCC8A135FC',
      'return' => 'id',
    ]);

    $goodyMail = $this->callAPISuccess('Email', 'get', [
      'contact_id' => $goodyID,
      'return' => ['email', 'is_primary'],
      'options' => ['sort' => 'is_primary DESC'],
      'sequential' => TRUE,
    ])['values'];
    // is primary flag should have been updated
    $this->assertEquals('goodies@example.com', $goodyMail[0]['email']);
    $this->assertEquals(1, $goodyMail[0]['is_primary']);
    $this->assertEquals('goodish@example.com', $goodyMail[1]['email']);
    $this->assertEquals(0, $goodyMail[1]['is_primary']);

    // Also check custom fields
    $contact = Civi\Api4\Contact::get(FALSE)
      ->addWhere('organization_name', '=', 'Villains Ltd')
      ->setSelect(['Organization_Contact.Phone', 'Organization_Contact.Email', 'Organization_Contact.Name', 'Organization_Contact.Title'])
      ->execute()->first();
    $this->assertEquals([
      'Organization_Contact.Phone' => 991,
      'Organization_Contact.Email' => 'wiseone@example.com',
      'Organization_Contact.Title' => 'The wise',
      'Organization_Contact.Name' => 'Bob',
      'id' => $contact['id'],
    ], $contact);
  }

  /**
   * Test valid output files are created when an error streak is encountered.
   *
   * An error streak is 10 or more invalid rows in a row.
   *
   * @throws \CRM_Core_Exception
   * @throws \League\Csv\Exception
   * @throws \Civi\WMFException\WMFException
   */
  public function testImporterErrorStreak(): void {
    $fileUri = $this->setupFile('engage_multiple_errors.csv');

    $importer = new EngageChecksFile($fileUri);
    $importer->import();
    $this->assertFalse($importer->isSuccess());
    $messages = $importer->getMessages();
    $this->assertEquals("Import aborted due to 10 consecutive errors, last error was at row 12: 'Invalid Name' is not a valid option for field custom_", substr($messages[0], 0, 125));
  }

  /**
   * Test our error reporting.
   *
   * @throws \CRM_Core_Exception
   * @throws \League\Csv\Exception
   * @throws \Civi\WMFException\WMFException
   */
  public function testImporterCreatesOutputFiles(): void {
    $this->sourceFileUri = __DIR__ . '/../tests/data/engage_reduced.csv';
    $fileUri = $this->setupFile('engage_reduced.csv');

    $importer = new EngageChecksFile($fileUri);
    $messages = $importer->import();
    $this->callAPISuccessGetSingle('Contribution', ['financial_type_id' => 'Endowment Gift', 'total_amount' => 26, 'receive_date' => '2014-07-29']);
    global $user;
    $this->assertEquals(
      [
        0 => 'Successful import!',
        'Result' => '14 out of 18 rows were imported.',
        'not imported' => '4 not imported rows logged to <a href=\'/import_output/' . substr(str_replace('.csv', '_all_missed.' . $user->uid, $fileUri), 12) . "'> file</a>.",
        'Duplicate' => '1 Duplicate row logged to <a href=\'/import_output/' . substr(str_replace('.csv', '_skipped.' . $user->uid, $fileUri), 12) . "'> file</a>.",
        'Error' => '3 Error rows logged to <a href=\'/import_output/' . substr(str_replace('.csv', '_errors.' . $user->uid, $fileUri), 12) . "'> file</a>.",
        'Rows where new contacts were created' => '14 Rows where new contacts were created rows logged to <a href=\'/import_output/' . substr(str_replace('.csv', '_all_not_matched.' . $user->uid, $fileUri), 12) . "'> file</a>.",
      ]
      , $messages);

    $errorsURI = str_replace('.csv', '_errors.' . $user->uid . '.csv', $fileUri);
    $this->assertFileExists($errorsURI);
    $errors = file($errorsURI);

    // Header row
    $this->assertEquals('Error,Banner,Campaign,Medium,Batch,"Contribution Type","Total Amount",Source,"Postmark Date","Received Date","Payment Instrument","Check Number",Restrictions,"Gift Source","Direct Mail Appeal","Organization Name","Street Address",City,Country,"Postal Code",Email,State,"Thank You Letter Date","AC Flag",Notes,"Do Not Email","Do Not Phone","Do Not Mail","Do Not SMS","Is Opt Out"', trim($errors[0]));
    unset($errors[0]);

    $this->assertCount(3, $errors);
    $this->assertEquals('"\'Unrstricted - General\' is not a valid option for field ' . wmf_civicrm_get_custom_field_name('Fund') . '",B15_0601_enlvroskLVROSK_dsk_lg_nag_sd.no-LP.cc,C15_mlWW_mob_lw_FR,sitenotice,10563,Engage,24,"USD 24.00",5/9/2015,5/9/2015,Cash,1,"Unrstricted - General","Corporate Gift","Carl TEST Perry",Roombo,"53 International Circle",Nowe,Poland,,cperry0@salon.com,,12/21/2014,,,,,,,
', $errors[1]);

    $skippedURI = str_replace('.csv', '_skipped.' . $user->uid . '.csv', $fileUri);
    $this->assertFileExists($skippedURI);
    $skipped = file($skippedURI);
    // 1 + 1 header row
    $this->assertCount(2, $skipped);

    $allURI = str_replace('.csv', '_all_missed.' . $user->uid . '.csv', $fileUri);
    $this->assertFileExists($allURI);
    $all = file($allURI);
    // 1 header row, 1 skipped, 3 errors.
    $this->assertCount(5, $all);

  }

  /**
   * Clean up transactions from previous test runs.
   *
   * If you run this several times locally it will fail on duplicate
   * transactions if we don't clean them up first.
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\WMFException\WMFException
   */
  public function purgePreviousData(): void {
    $disneyFolk = $this->callAPISuccess('Contact', 'get', [
      'last_name' => ['IN' => ['Mouse', 'Duck', 'Dog', 'Anonymous']],
    ])['values'];
    $herosAndVillains = $this->callAPISuccess('Contact', 'get', [
      'organization_name' => ['IN' => ['Evil Corp', 'Good Guys Inc.', 'Villains Ltd']],
    ]);
    $fantasyFolk = array_merge(array_keys($disneyFolk), array_keys($herosAndVillains['values']));
    if (!empty($fantasyFolk)) {
      $this->callAPISuccess('Contribution', 'get', [
        'api.Contribution.delete' => 1,
        'contact_id' => ['IN' => $fantasyFolk],
      ]);
      foreach ($fantasyFolk as $id) {
        if ($id !== $this->anonymousContactID) {
          $this->callAPISuccess('Contact', 'delete', [
            'skip_undelete' => TRUE,
            'id' => $id,
          ]);
        }
      }
    }

    if ($this->sourceFileUri) {
      try {
        $ids = $this->getGatewayIDs();
      }
      catch (CiviCRM_API3_Exception $e) {
        $ids = [0];
      }
      $this->callAPISuccess('Contribution', 'get', [
        'api.Contribution.delete' => 1,
        wmf_civicrm_get_custom_field_name('gateway_txn_id') => ['IN' => $ids],
        'api.contact.delete' => ['skip_undelete' => 1],
      ]);
    }

    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_contact WHERE organization_name = 'Jaloo'");
  }

  /**
   * Get the gateway IDS from the source file.
   *
   * @throws \Civi\WMFException\WMFException
   */
  public function getGatewayIDs(): array {
    $gatewayIDs = [];
    $data = $this->getParsedData();
    foreach ($data as $record) {
      $gatewayIDs[] = $record['gateway_txn_id'];
    }
    return $gatewayIDs;
  }

  /**
   * Get parsed data from the source file.
   *
   * @return array
   * @throws \Civi\WMFException\WMFException
   */
  public function getParsedData(): array {
    $file = fopen($this->sourceFileUri, 'r');
    $result = [];
    $importer = new EngageChecksFileProbe();
    $headers = [];
    while (($row = fgetcsv($file, 0, ',', '"', '\\')) !== FALSE) {
      if ($row[0] === 'Banner' || $row[0] === 'Batch') {
        // Header row.
        $headers = _load_headers($row);
        continue;
      }
      $data = array_combine(array_keys($headers), array_slice($row, 0, count($headers)));
      $result[] = $importer->_parseRow($data);

    }
    return $result;
  }

  /**
   * Set up the file for import.
   *
   * @param string $inputFileName
   *
   * @return string
   * @throws \CRM_Core_Exception
   * @throws \Civi\WMFException\WMFException
   */
  public function setupFile(string $inputFileName): string {
    $this->purgePreviousData();
    $this->sourceFileUri = __DIR__ . '/../tests/data/' . $inputFileName;
    $this->ensureAnonymousContactExists();

    // copy the file to a temp dir so copies are made in the temp dir.
    // This is where it would be in an import.
    $fileUri = tempnam(sys_get_temp_dir(), 'Engage') . '.csv';
    copy($this->sourceFileUri, $fileUri);
    return $fileUri;
  }

  /**
   * Clean up after test.
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\WMFException\WMFException
   */
  public function tearDown(): void {
    $this->purgePreviousData();
    parent::tearDown();
  }

  /**
   * Do the check file import.
   *
   * @param array $additionalFields
   *
   * @return array
   * @throws \Civi\WMFException\WMFException
   * @throws \League\Csv\Exception
   */
  protected function importCheckFile($additionalFields = []): array {
    $fileName = $this->sourceFileUri ?: __DIR__ . "/data/engage_duplicate_testing.csv";
    $importer = new EngageChecksFile($fileName, $additionalFields);
    $importer->import();
    return $importer->getMessages();
  }

  /**
   * Create a set of similar contacts with different contribution dates.
   *
   * @param array $contactParams
   *
   * @return array
   *   array of created contacts.
   * @throws \CRM_Core_Exception
   */
  protected function createContactSet(array $contactParams): array {
    $contacts = [];
    for ($i = 0; $i < 4; $i++) {
      $contacts[$i] = $this->callAPISuccess('Contact', 'create', $contactParams);
      // The second is the most recent.
      $dates = [
        0 => '2015-09-09',
        1 => '2017-12-12',
        2 => NULL,
        3 => '2016-10-10',
      ];
      if ($dates[$i]) {
        $this->callAPISuccess('Contribution', 'create', [
          'contact_id' => $contacts[$i]['id'],
          'financial_type_id' => 'Donation',
          'receive_date' => $dates[$i],
          'total_amount' => 700,
        ]);
      }
    }
    return $contacts;
  }
}
