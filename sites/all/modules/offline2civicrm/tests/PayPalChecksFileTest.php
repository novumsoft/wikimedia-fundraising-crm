<?php

class PayPalChecksFileTest extends BaseWmfDrupalPhpUnitTestCase {
    function setUp() {
        parent::setUp();

        require_once __DIR__ . "/includes/PayPalChecksFileProbe.php";
    }

    function testParseRow() {
        $data = array(
            'Contribution Type' => 'Cash',
            'Total Amount' => '$10.00',
            'Source' => 'USD 10.00',
            'Received Date' => '1/27/13',
            'Payment Instrument' => 'EFT',
            'Restrictions' => 'Unrestricted - General',
            'Gift Source' => 'Community Gift',
            'Direct Mail Appeal' => 'MissionFish (PayPal)',
            'Prefix' => '',
            'First Name' => 'Diz and',
            'Last Name' => 'Bird',
            'Suffix' => '',
            'Street Address' => '',
            'Additional Address 1' => '',
            'Additional Address 2' => '',
            'City' => '',
            'State' => '',
            'Postal Code' => '',
            'Country' => '',
            'Phone' => '',
            'Email' => '',
            'No Thank You' => 'no reas',
        );
        $expected_normal = array(
            'contact_source' => 'check',
            'contact_type' => 'Individual',
            'contribution_source' => 'USD 10.00',
            'country' => 'US',
            'date' => 1359273600,
            'direct_mail_appeal' => 'MissionFish (PayPal)',
            'email' => 'nobody@wikimedia.org',
            'first_name' => 'Diz and',
            'gateway' => 'paypal',
            'gift_source' => 'Community Gift',
            'gross' => '$10.00',
            'last_name' => 'Bird',
            'no_thank_you' => 'no reas',
            'payment_method' => 'EFT',
            'raw_contribution_type' => 'Cash',
            'restrictions' => 'Unrestricted - General',
        );

        $importer = new PayPalChecksFileProbe( "no URI" );
        $output = $importer->_parseRow( $data );

        $this->assertEquals( $expected_normal, $output );
    }
}
