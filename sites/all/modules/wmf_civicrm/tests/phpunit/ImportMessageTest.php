<?php

define( 'ImportMessageTest_campaign', 'test mail code here + ' . mt_rand() );

/**
 * @group Import
 * @group Pipeline
 * @group WmfCivicrm
 */
class ImportMessageTest extends BaseWmfDrupalPhpUnitTestCase {
    protected $contact_custom_mangle;
    protected $contribution_id;
    protected $contribution_custom_mangle;
    static protected $fixtures;

  /**
   * These are contribution fields that we do not check for in our comparison.
   *
   * Since we never set these always checking for them adds boilerplate code
   * and potential test breakiness.
   *
   * @var array
   */
    protected $fieldsToIgnore = array(
      'address_id',
      'contact_id',
      'cancel_date',
      'cancel_reason',
      'thankyou_date',
      'amount_level',
      'contribution_recur_id',
      'contribution_page_id',
      'creditnote_id',
      'is_test',
      'id',
      'invoice_id',
      'is_pay_later',
      'campaign_id',
      'tax_amount',
    );

    protected $moneyFields = array(
      'total_amount',
      'source',
      'net_amount',
      'fee_amount',
    );

    public function setUp() {
        civicrm_api3( 'OptionValue', 'create', array(
            'option_group_id' => WMF_CAMPAIGNS_OPTION_GROUP_NAME,
            'label' => ImportMessageTest_campaign,
            'value' => ImportMessageTest_campaign,
        ) );
    }

    public function tearDown() {
        if ( $this->contribution_id ) {
            civicrm_api_classapi()->Contribution->Delete( array(
                'id' => $this->contribution_id,
                'version' => '3',
            ) );
        }
        parent::tearDown();
    }

    /**
     * @dataProvider messageProvider
     */
    public function testMessageInsert( $msg, $expected ) {
        $contribution = wmf_civicrm_contribution_message_import( $msg );
        $this->contribution_id = $contribution['id'];

        // Ignore contact_id if we have no expectation.
        if ( empty( $expected['contribution']['contact_id'] ) ) {
            $this->fieldsToIgnore[] = 'contact_id';
        }

        $this->assertComparable( $expected['contribution'], $contribution );

        if ( !empty( $expected['contribution_custom_values'] ) ) {
            $actual_contribution_custom_values = wmf_civicrm_get_custom_values(
                $contribution['id'],
                array_keys( $expected['contribution_custom_values'] )
            );
            $this->assertEquals( $expected['contribution_custom_values'], $actual_contribution_custom_values );
        }

        if ( !empty( $expected['contact'] ) ) {
            $api = civicrm_api_classapi();
            $api->Contact->Get( array(
                'id' => $contribution['contact_id'],
                'version' => 3,
            ) );
            $contact = (array) $api->values[0];
            $renamedFields = array('prefix' => 1, 'suffix' => 1);
            $this->assertEquals( array_diff_key($expected['contact'], $renamedFields), array_intersect_key( $expected['contact'], $contact ) );
            foreach (array_keys($renamedFields) as $renamedField) {
                $this->assertEquals(civicrm_api3('OptionValue', 'getvalue', array(
                    'value' => $contact[$renamedField . '_id'],
                    'option_group_id' => 'individual_' . $renamedField,
                    'return' => 'name',
                )), $expected['contact'][$renamedField]);
            }

        }

        if ( !empty( $expected['contact_custom_values'] ) ) {
            $actual_contact_custom_values = wmf_civicrm_get_custom_values(
                $contribution['contact_id'],
                array_keys( $expected['contact_custom_values'] )
            );
            $this->assertEquals( $expected['contact_custom_values'], $actual_contact_custom_values );
        }
    }

    public function messageProvider() {
        // Make static so it isn't destroyed until class cleanup.
        self::$fixtures = CiviFixtures::create();

        $contribution_type_cash = wmf_civicrm_get_civi_id( 'contribution_type_id', 'Cash' );
        $payment_instrument_cc = wmf_civicrm_get_civi_id( 'payment_instrument_id', 'Credit Card' );
        $payment_instrument_check = wmf_civicrm_get_civi_id( 'payment_instrument_id', 'Check' );

        $gateway_txn_id = mt_rand();
        $check_number = (string) mt_rand();

        $new_prefix = 'M' . mt_rand();

        return array(
            // Minimal contribution
            array(
                array(
                    'currency' => 'USD',
                    'date' => '2012-05-01 00:00:00',
                    'email' => 'nobody@wikimedia.org',
                    'gateway' => 'test_gateway',
                    'gateway_txn_id' => $gateway_txn_id,
                    'gross' => '1.23',
                    'payment_method' => 'cc',
                ),
                array(
                    'contribution' => array(
                        'address_id' => '',
                        'amount_level' => '',
                        'campaign_id' => '',
                        'cancel_date' => '',
                        'cancel_reason' => '',
                        'check_number' => '',
                        'contribution_page_id' => '',
                        'contribution_recur_id' => '',
                        'contribution_status_id' => '1',
                        'contribution_type_id' => $contribution_type_cash,
                        'currency' => 'USD',
                        'fee_amount' => '0',
                        'invoice_id' => '',
                        'is_pay_later' => '',
                        'is_test' => '',
                        'net_amount' => '1.23',
                        'non_deductible_amount' => '',
                        'payment_instrument_id' => $payment_instrument_cc,
                        'receipt_date' => '',
                        'receive_date' => '20120501000000',
                        'source' => 'USD 1.23',
                        'thankyou_date' => '',
                        'total_amount' => '1.23',
                        'trxn_id' => "TEST_GATEWAY {$gateway_txn_id}",
                        'financial_type_id' => $contribution_type_cash,
                        'creditnote_id' => '',
                        'tax_amount' => '',
                    ),
                ),
            ),

            // Minimal contribution with comma thousand separator.
            array(
                array(
                    'currency' => 'USD',
                    'date' => '2012-05-01 00:00:00',
                    'email' => 'nobody@wikimedia.org',
                    'gateway' => 'test_gateway',
                    'gateway_txn_id' => $gateway_txn_id,
                    'gross' => '1,000.23',
                    'payment_method' => 'cc',
                ),
                array(
                    'contribution' => array(
                        'contribution_status_id' => '1',
                        'contribution_type_id' => $contribution_type_cash,
                        'currency' => 'USD',
                        'fee_amount' => '0',
                        'total_amount' => '1,000.23',
                        'net_amount' => '1,000.23',
                        'non_deductible_amount' => '',
                        'payment_instrument_id' => $payment_instrument_cc,
                        'receipt_date' => '',
                        'receive_date' => '20120501000000',
                        'source' => 'USD 1,000.23',
                        'trxn_id' => "TEST_GATEWAY {$gateway_txn_id}",
                        'financial_type_id' => $contribution_type_cash,
                        'check_number' => '',
                    ),
                ),
            ),

            // Maximal contribution
            array(
                array(
                    'check_number' => $check_number,
                    'currency' => 'USD',
                    'date' => '2012-03-01 00:00:00',
                    'direct_mail_appeal' => ImportMessageTest_campaign,
                    'do_not_email' => 'Y',
                    'do_not_mail' => 'Y',
                    'do_not_phone' => 'Y',
                    'do_not_sms' => 'Y',
                    'do_not_solicit' => 'Y',
                    'email' => 'nobody@wikimedia.org',
                    'first_name' => 'First',
                    'fee' => '0.03',
                    'gateway' => 'test_gateway',
                    'gateway_txn_id' => $gateway_txn_id,
                    'gift_source' => 'Legacy Gift',
                    'gross' => '1.23',
                    'import_batch_number' => '4321',
                    'is_opt_out' => 'Y',
                    'last_name' => 'Last',
                    'middle_name' => 'Middle',
                    'no_thank_you' => 'no forwarding address',
                    'name_prefix' => $new_prefix,
                    'name_suffix' => 'Sr.',
                    'payment_method' => 'check',
                    'stock_description' => 'Long-winded prolegemenon',
                    'thankyou_date' => '2012-04-01',
                ),
                array(
                    'contact' => array(
                        'do_not_email' => '1',
                        'do_not_mail' => '1',
                        'do_not_phone' => '1',
                        'do_not_sms' => '1',
                        'first_name' => 'First',
                        'is_opt_out' => '1',
                        'last_name' => 'Last',
                        'middle_name' => 'Middle',
                        'prefix' => $new_prefix,
                        'suffix' => 'Sr.',
                    ),
                    'contribution' => array(
                        'address_id' => '',
                        'amount_level' => '',
                        'campaign_id' => '',
                        'cancel_date' => '',
                        'cancel_reason' => '',
                        'check_number' => $check_number,
                        'contribution_page_id' => '',
                        'contribution_recur_id' => '',
                        'contribution_status_id' => '1',
                        'contribution_type_id' => $contribution_type_cash,
                        'currency' => 'USD',
                        'fee_amount' => '0.03',
                        'invoice_id' => '',
                        'is_pay_later' => '',
                        'is_test' => '',
                        'net_amount' => '1.2', # :(
                        'non_deductible_amount' => '',
                        'payment_instrument_id' => $payment_instrument_check,
                        'receipt_date' => '',
                        'receive_date' => '20120301000000',
                        'source' => 'USD 1.23',
                        'thankyou_date' => '20120401000000',
                        'total_amount' => '1.23',
                        'trxn_id' => "TEST_GATEWAY {$gateway_txn_id}",
                        'financial_type_id' => $contribution_type_cash,
                        'creditnote_id' => '',
                        'tax_amount' => '',
                    ),
                    'contribution_custom_values' => array(
                        'Appeal' => ImportMessageTest_campaign,
                        'import_batch_number' => '4321',
                        'Campaign' => 'Legacy Gift',
                        'gateway' => 'test_gateway',
                        'gateway_txn_id' => (string) $gateway_txn_id,
                        'no_thank_you' => 'no forwarding address',
                        'Description_of_Stock' => 'Long-winded prolegemenon',
                    ),
                    'contact_custom_values' => array(
                        'do_not_solicit' => '1',
                        'is_2010_donor' => '0',
                        'is_2011_donor' => '1', # Fiscal year
                        'is_2012_donor' => '0',
                        'last_donation_date' => '2012-03-01 00:00:00',
                        'last_donation_usd' => '1.23',
                        'lifetime_usd_total' => '1.23',
                    ),
                ),
            ),

            // Organization contribution
            array(
                array(
                    'contact_type' => 'Organization',
                    'currency' => 'USD',
                    'date' => '2012-03-01 00:00:00',
                    'gateway' => 'test_gateway',
                    'gateway_txn_id' => $gateway_txn_id,
                    'gross' => '1.23',
                    'organization_name' => 'Hedgeco',
                    'org_contact_name' => 'Testname',
                    'org_contact_title' => 'Testtitle',
                    'payment_method' => 'cc',
                ),
                array(
                    'contribution' => array(
                        'address_id' => '',
                        'amount_level' => '',
                        'campaign_id' => '',
                        'cancel_date' => '',
                        'cancel_reason' => '',
                        'check_number' => '',
                        'contribution_page_id' => '',
                        'contribution_recur_id' => '',
                        'contribution_status_id' => '1',
                        'contribution_type_id' => $contribution_type_cash,
                        'currency' => 'USD',
                        'fee_amount' => '0',
                        'invoice_id' => '',
                        'is_pay_later' => '',
                        'is_test' => '',
                        'net_amount' => '1.23',
                        'non_deductible_amount' => '',
                        'payment_instrument_id' => $payment_instrument_cc,
                        'receipt_date' => '',
                        'receive_date' => '20120301000000',
                        'source' => 'USD 1.23',
                        'thankyou_date' => '',
                        'total_amount' => '1.23',
                        'trxn_id' => "TEST_GATEWAY {$gateway_txn_id}",
                        'financial_type_id' => $contribution_type_cash,
                        'creditnote_id' => '',
                        'tax_amount' => '',
                    ),
                    'contact_custom_values' => array(
                        'Name' => 'Testname',
                        'Title' => 'Testtitle',
                    ),
                ),
            ),

            // Subscription payment
            array(
                array(
                    'contact_id' => self::$fixtures->contact_id,
                    'contribution_recur_id' => self::$fixtures->contribution_recur_id,
                    'currency' => 'USD',
                    'date' => '2014-01-01 00:00:00',
                    'effort_id' => 2,
                    'email' => 'nobody@wikimedia.org',
                    'gateway' => 'test_gateway',
                    'gateway_txn_id' => $gateway_txn_id,
                    'gross' => self::$fixtures->recur_amount,
                    'payment_method' => 'cc',
                ),
                array(
                    'contribution' => array(
                        'address_id' => '',
                        'amount_level' => '',
                        'campaign_id' => '',
                        'cancel_date' => '',
                        'cancel_reason' => '',
                        'check_number' => '',
                        'contact_id' => strval( self::$fixtures->contact_id ),
                        'contribution_page_id' => '',
                        'contribution_recur_id' => strval( self::$fixtures->contribution_recur_id ),
                        'contribution_status_id' => '1',
                        'contribution_type_id' => $contribution_type_cash,
                        'currency' => 'USD',
                        'fee_amount' => '0',
                        'invoice_id' => '',
                        'is_pay_later' => '',
                        'is_test' => '',
                        'net_amount' => self::$fixtures->recur_amount,
                        'non_deductible_amount' => '',
                        'payment_instrument_id' => $payment_instrument_cc,
                        'receipt_date' => '',
                        'receive_date' => '20140101000000',
                        'source' => 'USD ' . self::$fixtures->recur_amount,
                        'thankyou_date' => '',
                        'total_amount' => self::$fixtures->recur_amount,
                        'trxn_id' => "TEST_GATEWAY {$gateway_txn_id}",
                        'financial_type_id' => $contribution_type_cash,
                        'creditnote_id' => '',
                        'tax_amount' => '',
                    ),
                ),
            ),
        );
    }

    public function testImportContactGroups() {
        $fixtures = CiviFixtures::create();

        $msg = array(
            'currency' => 'USD',
            'date' => '2012-03-01 00:00:00',
            'gateway' => 'test_gateway',
            'gateway_txn_id' => mt_rand(),
            'gross' => '1.23',
            'payment_method' => 'cc',
            'contact_groups' => $fixtures->contact_group_name,
        );
        $contribution = wmf_civicrm_contribution_message_import( $msg );

        $api = civicrm_api_classapi();
        $api->GroupContact->Get( array(
            'contact_id' => $contribution['contact_id'],

            'version' => 3,
        ) );
        $this->assertEquals( 1, count( $api->values ) );
        $this->assertEquals( $fixtures->contact_group_id, $api->values[0]->group_id );
    }

  /**
   * Assert that 2 arrays are the same in all the ways that matter :-).
   *
   * This has been written for a specific test & will probably take extra work
   * to use more broadly.
   *
   * @param array $array1
   * @param array $array2
   */
    public function assertComparable($array1, $array2) {
      $this->reformatMoneyFields($array1);
      $this->reformatMoneyFields($array2);
      $array1 = $this->filterIgnoredFieldsFromArray($array1);
      $array2 = $this->filterIgnoredFieldsFromArray($array2);
      $this->assertEquals($array1, $array2);

    }

  /**
   * Remove commas from money fields.
   *
   * @param array $array
   */
    public function reformatMoneyFields(&$array) {
      foreach ($array as $field => $value) {
        if (in_array($field, $this->moneyFields)) {
          $array[$field] = str_replace(',', '', $value);
        }
      }
    }

  /**
   * Remove fields we don't care about from the array.
   *
   * @param array $array
   *
   * @return array
   */
    public function filterIgnoredFieldsFromArray($array) {
      return array_diff_key($array, array_flip($this->fieldsToIgnore));
    }

}
