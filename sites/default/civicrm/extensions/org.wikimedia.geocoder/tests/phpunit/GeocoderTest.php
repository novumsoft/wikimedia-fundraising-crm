<?php

require_once __DIR__ . '/BaseTestClass.php';

use CRM_Geocoder_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;
use Http\Adapter\Guzzle6\Client;
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
 * @group headless
 */
class GeocoderTest extends BaseTestClass implements HeadlessInterface, HookInterface, TransactionalInterface {

  protected $ids = [];

  protected $geocoders = [];

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->sqlFile(__DIR__  . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..'
        . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'nz_sample_geoname_table.sql')
      ->apply();
  }

  public function setUp() {
    parent::setUp();
    civicrm_initialize();
    if (!isset($GLOBALS['_PEAR_default_error_mode'])) {
      // This is simply to protect against e-notices if globals have been reset by phpunit.
      $GLOBALS['_PEAR_default_error_mode'] = NULL;
      $GLOBALS['_PEAR_default_error_options'] = NULL;
    }

    $geocoders = civicrm_api3('Geocoder', 'get', [])['values'];
    foreach ($geocoders as $geocoder) {
      $this->geocoders[$geocoder['name']] = $geocoder;
    }

    $this->configureGeoCoders([
      'open_street_maps' => [
        'name' => 'open_street_maps',
        'is_active' => 1,
        'weight' => 1,
      ],
      'us_zip_geocoder' => [
        'name' => 'us_zip_geocoder',
        'is_active' => 1,
        'weight' => 2,
      ],
      'geonames_db_table' => [
        'name' => 'geonames_db_table',
        'is_active' => 1,
        'weight' => 3,
      ],
    ]);

    $contact = $this->callAPISuccess('Contact', 'create', [
      'contact_type' => 'Individual',
      'first_name' => 'Brer',
      'last_name' => 'Rabbit',
    ]);
    $this->ids['contact'][] = $contact['id'];
    $this->callAPISuccess('System', 'flush', []);
  }

  public function tearDown() {
    foreach ($this->ids as $entity => $entityIDs) {
      foreach ($entityIDs as $id) {
        $this->callAPISuccess($entity, 'delete', ['id' => $id]);
      }
    }
    $this->configureGeoCoders($this->geocoders);
    parent::tearDown();
  }

  /**
   * Test open street maps geocodes address.
   */
  public function testOpenStreetMaps() {
    $responses = [new Response(200, [], file_get_contents(__DIR__ . '/Responses/OpenStreetMaps.xml'))];
    $this->getClient($responses);
    $address = $this->callAPISuccess('Address', 'create', [
      'postal_code' => 90210,
      'location_type_id' => 'Home',
      'contact_id' => $this->ids['contact'][0],
      'country_id' => 'US',
    ]);
    $address = $this->callAPISuccessGetSingle('Address', ['id' => $address['id']]);
    // Different systems seem to vary in their precision so let's round.
    $this->assertEquals('34.0781172375', round($address['geo_code_1'], 10));
    $this->assertEquals('-118.352999971', round($address['geo_code_2'], 9));
  }

  /**
   * Test when open street maps fail we fall back on the next one (USZipGeoCoder).
   *
   * Note the lat long are slightly different between the 2 providers & we get timezone.
   */
  public function testOpenStreetMapsFailsFallsbackToUSLookup() {
    $this->setHttpClientToEmptyMock();
    $address = $this->callAPISuccess('Address', 'create', [
      'postal_code' => 90210,
      'location_type_id' => 'Home',
      'contact_id' => $this->ids['contact'][0],
      'country_id' => 'US',
    ]);
    $address = $this->callAPISuccessGetSingle('Address', ['id' => $address['id']]);
    $this->assertEquals('34.088808', $address['geo_code_1']);
    $this->assertEquals('-118.40612', $address['geo_code_2']);
    $this->assertEquals('UTC-8', $address['timezone']);
    $this->assertEquals('Beverly Hills', $address['city']);
    $this->assertEquals(
      $this->callAPISuccessGetValue('StateProvince', [
        'return' => 'id',
        'name' => 'California',
      ]),
      $address['state_province_id']
    );

  }

  /**
   * Test that postal codes are prepended with zeros for minimum length.
   *
   * This only applies to NZ & US at the moment but as we get validation for
   * more countries we can extend.
   */
  public function testShortPostalCode() {
    $this->setHttpClientToEmptyMock();
    $address = $this->callAPISuccess('Address', 'create', [
      'postal_code' => 624,
      'location_type_id' => 'Home',
      'contact_id' => $this->ids['contact'][0],
      'country_id' => 'US',
    ]);
    $address = $this->callAPISuccessGetSingle('Address', ['id' => $address['id']]);
    $this->assertEquals('18.055399', $address['geo_code_1']);
  }

  /**
   * Test geoname table option.
   */
  public function testGeoName(){
    $this->setHttpClientToEmptyMock();
    $drop = FALSE;
    if (!CRM_Core_DAO::singleValueQuery("SHOW TABLES LIKE 'civicrm_geonames_lookup'")) {
      // set up headless doesn't seem to be called in wmf tests ...but I haven't
      // double checked if we can drop if when running tests in isolation.
      CRM_Utils_File::sourceSQLFile(NULL, __DIR__  . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..'
        . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'nz_sample_geoname_table.sql');
      $drop = TRUE;
    }
    $address = $this->callAPISuccess('Address', 'create', [
      'postal_code' => '0951',
      'location_type_id' => 'Home',
      'contact_id' => $this->ids['contact'][0],
      'country_id' => 'NZ',
    ]);
    $address = $this->callAPISuccessGetSingle('Address', ['id' => $address['id']]);
    $this->assertEquals('-36.5121', $address['geo_code_1']);
    $this->assertEquals('174.661', $address['geo_code_2']);
    $this->assertEquals('Puhoi', $address['city']);
    if ($drop) {
      CRM_Core_DAO::executeQuery("DROP TABLE civicrm_geonames_lookup");
    }
  }

  /**
   * Configure geocoders for testing.
   *
   * @param array $coders
   *   Array of coders that should be enabled.
   */
  protected function configureGeoCoders($coders) {
     foreach ($this->geocoders as $geoCoder) {
       if (isset($coders[$geoCoder['name']])) {
         $params = array_merge(['id' => $geoCoder['id']], $coders[$geoCoder['name']]);
       }
       else {
         $params = ['id' => $geoCoder['id'], 'is_active' => 0];
       }
       // @todo api should handle these but for now we will.
       $jsonFields = ['required_fields', 'retained_response_fields', 'datafill_response_fields', 'valid_countries'];
       foreach ($jsonFields as $jsonField) {
         if (!empty($params[$jsonField]) && is_string($jsonField)) {
           $params[$jsonField] = json_decode($params[$jsonField]);
         }
       }

       $this->callAPISuccess('Geocoder', 'create', $params);
     }
  }

  /**
   * @param $responses
   */
  protected function getClient($responses) {
    $mock = new MockHandler($responses);
    $handler = HandlerStack::create($mock);
    CRM_Utils_Geocode_Geocoder::setClient(Client::createWithConfig(['handler' => $handler]));
  }

  protected function setHttpClientToEmptyMock() {
    $responses = [];
    $this->getClient($responses);
  }

}
