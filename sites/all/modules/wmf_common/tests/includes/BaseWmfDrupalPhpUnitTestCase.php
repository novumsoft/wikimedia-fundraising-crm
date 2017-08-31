<?php

use SmashPig\Core\Context;
use SmashPig\Tests\TestingContext;
use SmashPig\Tests\TestingGlobalConfiguration;

class BaseWmfDrupalPhpUnitTestCase extends PHPUnit_Framework_TestCase {
	protected $startTimestamp;

	public function setUp() {
        parent::setUp();

        // Initialize SmashPig with a fake context object
        $config = TestingGlobalConfiguration::create();
        TestingContext::init( $config );

        if ( !defined( 'DRUPAL_ROOT' ) ) {
            throw new Exception( "Define DRUPAL_ROOT somewhere before running unit tests." );
        }

        global $user, $_exchange_rate_cache;
        $GLOBALS['_PEAR_default_error_mode'] = NULL;
        $GLOBALS['_PEAR_default_error_options'] = NULL;
        $_exchange_rate_cache = array();

        $user = new stdClass();
        $user->name = "foo_who";
        $user->uid = "321";
        $user->roles = array( DRUPAL_AUTHENTICATED_RID => 'authenticated user' );
        $this->startTimestamp = time();
    }

    public function tearDown() {
		Context::set( null ); // Nullify any SmashPig context for the next run
		parent::tearDown();
	}

	/**
	 * Temporarily set foreign exchange rates to known values
	 *
	 * TODO: Should reset after each test.
	 */
	protected function setExchangeRates( $timestamp, $rates ) {
		foreach ( $rates as $currency => $rate ) {
			exchange_rate_cache_set( $currency, $timestamp, $rate );
		}
	}

	/**
	 * Create a temporary directory and return the name
	 * @return string|boolean directory path if creation was successful, or false
	 */
	protected function getTempDir() {
		$tempFile = tempnam( sys_get_temp_dir(), 'wmfDrupalTest_' );
		if ( file_exists( $tempFile ) ) {
			unlink( $tempFile );
		}
		mkdir( $tempFile );
		if ( is_dir( $tempFile ) ) {
			return $tempFile . '/';
		}
		return false;
	}

    /**
     * API wrapper function from core (more or less).
     *
     * so we can ensure they succeed & throw exceptions without littering the test with checks.
     *
     * This is not the full function but it we think it'w worth keeping a copy it should maybe
     * go in the parent.
     *
     * @param string $entity
     * @param string $action
     * @param array $params
     * @param mixed $checkAgainst
     *   Optional value to check result against, implemented for getvalue,.
     *   getcount, getsingle. Note that for getvalue the type is checked rather than the value
     *   for getsingle the array is compared against an array passed in - the id is not compared (for
     *   better or worse )
     *
     * @return array|int
     */
    public function callAPISuccess($entity, $action, $params, $checkAgainst = NULL) {
        $params = array_merge(array(
            'version' => 3,
            'debug' => 1,
        ),
            $params
        );
        try {
            $result = civicrm_api3($entity, $action, $params);
        }
        catch (CiviCRM_API3_Exception $e) {
            $this->assertEquals(0, $e->getMessage() . print_r($e->getExtraParams(), TRUE));
        }
        $this->assertAPISuccess($result, "Failure in api call for $entity $action");
        return $result;
    }

    /**
     * Check that api returned 'is_error' => 0.
     *
     * @param array $apiResult
     *   Api result.
     * @param string $prefix
     *   Extra test to add to message.
     */
    public function assertAPISuccess($apiResult, $prefix = '') {
        if (!empty($prefix)) {
            $prefix .= ': ';
        }
        $errorMessage = empty($apiResult['error_message']) ? '' : " " . $apiResult['error_message'];

        if (!empty($apiResult['debug_information'])) {
            $errorMessage .= "\n " . print_r($apiResult['debug_information'], TRUE);
        }
        if (!empty($apiResult['trace'])) {
            $errorMessage .= "\n" . print_r($apiResult['trace'], TRUE);
        }
        $this->assertEquals(0, $apiResult['is_error'], $prefix . $errorMessage);
    }

  /**
   * Getsingle test function from civicrm core codebase test suite.
   *
   * This function exists to wrap api getsingle function & check the result
   * so we can ensure they succeed & throw exceptions without litterering the test with checks
   *
   * @param string $entity
   * @param array $params
   *
   * @throws Exception
   * @return array|int
   */
  public function callAPISuccessGetSingle($entity, $params) {
    $params += array(
      'version' => 3,
      'debug' => 1,
    );
    $result = civicrm_api($entity, 'getsingle', $params);
    if (!is_array($result) || !empty($result['is_error']) || isset($result['values'])) {
      throw new Exception('Invalid getsingle result' . print_r($result, TRUE));
    }
    return $result;
  }

  /**
   * Emulate a logged in user since certain functions use that.
   * value to store a record in the DB (like activity)
   * CRM-8180
   *
   * @return int
   *   Contact ID of the created user.
   */
  public function imitateAdminUser() {
    $result = $this->callAPISuccess('UFMatch', 'get', array(
      'uf_id' => 1,
      'sequential' => 1,
    ));
    if (empty($result['id'])) {
      $contact = $this->callAPISuccess('Contact', 'create', array(
        'first_name' => 'Super',
        'last_name' => 'Duper',
        'contact_type' => 'Individual',
        'api.UFMatch.create' => array('uf_id' => 1, 'uf_name' => 'Wizard'),
      ));
      $contactID = $contact['id'];
    }
    else {
      $contactID = $result['values'][0]['contact_id'];
    }
    $session = CRM_Core_Session::singleton();
    $session->set('userID', $contactID);
    CRM_Core_Config::singleton()->userPermissionClass = new CRM_Core_Permission_UnitTests();
    CRM_Core_Config::singleton()->userPermissionClass->permissions = array('Edit All Contacts', 'Access CiviCRM', 'Administer CiviCRM');
    return $contactID;
  }

  public function cleanUpContact( $contactId ) {
    $contributions = $this->callAPISuccess('Contribution', 'get', array(
      'contact_id' => $contactId
    ) );
    if ( !empty( $contributions['values'] ) ) {
      foreach ( $contributions['values'] as $id => $details ) {
        $this->callAPISuccess( 'Contribution', 'delete', array(
          'id' => $id
        ) );

        db_delete( 'contribution_tracking' )
          ->condition( 'contribution_id', $id )
          ->execute();
      }
    }
    $this->callAPISuccess('Contact', 'delete', array(
      'id' => $contactId
    ) );
  }

  public function onNotSuccessfulTest( $e ) {
    if ( !PRINT_WATCHDOG_ON_TEST_FAIL ) {
      return;
	}
    $output = "\nWatchdog messages:\n";

    // show watchdog messages since the start of this test
    $rsc = db_select( 'watchdog', 'wd' )
      ->condition( 'timestamp' , $this->startTimestamp, '>=' )
      ->fields( 'wd' )
      ->orderBy( 'wid', 'ASC' )
      ->execute();

    while ( $result = $rsc->fetchAssoc() ) {
      if ( isset ( $result['variables'] ) ) {
        $vars = unserialize( $result['variables'] );
      } else {
        $vars = null;
      }
      $message = strip_tags(
        is_array( $vars )
          ? strtr( $result['message'], $vars )
          : $result['message']
      );
      $output .= "{$result['timestamp']}, lvl {$result['severity']}, {$result['type']}: $message\n";
    }

    if ( method_exists( $e, 'getMessage' ) ) {
      $accessible = \Wikimedia\TestingAccessWrapper::newFromObject( $e );
      $accessible->message = $e->getMessage() . $output;
    } else {
      echo $output;
    }

    throw $e;
  }
}
