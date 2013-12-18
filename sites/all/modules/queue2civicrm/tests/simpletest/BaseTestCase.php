<?php
/**
 * Set up schemas and constants.
 */
class BaseTestCase extends DrupalWebTestCase {
    protected $profile = 'minimal';

    public function setUp() {
        // FIXME: pass module names from subclass setUp
        parent::setUp(
            'dblog', 'exchange_rates', 'queue2civicrm', 'wmf_common', 'wmf_civicrm', 'contribution_tracking', 'wmf_refund_qc', 'recurring', 'recurring_globalcollect', 'offline2civicrm',
            // civi schema is not installed here,
            'civicrm'
        );

        // This is a terrible hack: we are using the "development"
        // civicrm database configured in civicrm.settings.php, and avoid the
        // overhead of creating a new test db by protecting with a transaction.
        // It probably subtly breaks all kinds of simpletest things.
        $this->civicrm_transaction = new CRM_Core_Transaction();

        // FIXME: reference data is hardcoded
        $this->currency = "BAA";
        // FIXME: this will always be a losing race, until value_in_usd table update method is exposed.
        module_invoke( 'exchange_rate', 'cache_set', $this->currency, time(), 2);
        // settlement currency is hardcoded
        module_invoke( 'exchange_rate', 'cache_set', "USD", time(), 1);
    }

    public function tearDown() {
        $mails = $this->drupalGetMails();

        $this->civicrm_transaction->rollback();

        parent::tearDown();
    }

    /**
     * Check that all elements of $inner match in $super, recursively.
     */
    protected function assertSuperset( $super, $inner, $path = array() ) {
        $expected_value = static::array_dereference( $inner, $path );
        if ( is_array( $expected_value ) ) {
            foreach ( array_keys( $expected_value ) as $key ) {
                $inner_path = $path;
                $inner_path[] = $key;
                $this->assertSuperset( $super, $inner, $inner_path );
            }
        } else {
            try {
                $this->assertIdentical(
                    static::array_dereference( $super, $path ),
                    $expected_value,
                    "Match at " . implode( ".", $path ) );
            } catch ( Exception $ex ) {
                $this->assert( false, $ex->getMessage() );
            }
        }
    }

    static function array_dereference( $root, $path ) {
        $cur_path = array();
        while ( count( $path ) ) {
            $key = array_shift( $path );
            $cur_path[] = $key;
            if ( !is_array( $root ) or !array_key_exists( $key, $root ) ) {
                throw new Exception( "Missing value for key " . implode( ".", $cur_path ) );
            }
            $root = $root[$key];
        }
        return $root;
    }
}
