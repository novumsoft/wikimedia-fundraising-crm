<?php

use Civi\Api4\Contact;
use Civi\Api4\Contribution;

/**
 * @group Import
 * @group Offline2Civicrm
 */
class FidelityFileTest extends BaseChecksFileTest {

  /**
   * Post test cleanup.
   *
   * @throws \API_Exception
   */
  public function tearDown(): void {
    Contribution::delete(FALSE)->addWhere('trxn_id', 'LIKE', 'Fidelity%')->execute();
    Contact::delete(FALSE)->addWhere('id', '>', $this->maxContactID)->execute();
    parent::tearDown();
  }

  /**
   * Test basic import.
   *
   * @throws \API_Exception
   * @throws \CRM_Core_Exception
   * @throws \League\Csv\Exception
   * @throws \WmfException
   */
  public function testImport(): void {
    $importer = new FidelityFile(__DIR__ . "/data/fidelity.csv");
    $messages = $importer->import();
    $this->assertEquals('All rows were imported', $messages['Result']);
    $contributions = Contribution::get(FALSE)->addWhere('trxn_id', 'LIKE', 'Fidelity%')
      ->setSelect(['contact.first_name', 'contact.last_name', 'Partner.Partner', 'total_amount', 'contact.prefix_id:label', 'contact.organization_name'])
      ->addOrderBy('id')
      ->execute();
    $contribution = $contributions[0];
    $this->assertEquals('50', $contribution['total_amount']);
    $this->assertEquals('Anonymous', $contribution['contact.first_name']);
    $this->assertEquals('Anonymous', $contribution['contact.last_name']);

    $contribution = $contributions[1];
    $this->assertEquals('Patrick', $contribution['contact.first_name']);
    $this->assertEquals('Jones', $contribution['contact.last_name']);
    $this->assertEquals('Mr.', $contribution['contact.prefix_id:label']);

    $contribution = $contributions[2];
    $this->assertEquals('John', $contribution['contact.first_name']);
    $this->assertEquals('Good', $contribution['contact.last_name']);
    $this->assertEquals('Sally Wilde', $contribution['Partner.Partner']);

    $contribution = $contributions[3];
    $this->assertEquals('Great Family Foundation', $contribution['contact.organization_name']);

    $contribution = $contributions[4];
    $this->assertEquals('Jim', $contribution['contact.first_name']);
    $this->assertEquals('White', $contribution['contact.last_name']);

  }

}
