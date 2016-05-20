<?php

class Message {
    protected $defaults = array();

    public $body;
    public $headers;

    protected $data;

    function __construct( $values = array() ) {
        $this->data = $this->defaults;
        $this->headers = array();
        $this->set( $values );
    }

    function set( $values ) {
        if ( is_array( $values ) ) {
            $this->data = $values + $this->data;
        }

        $this->body = json_encode( $this->data );
    }

    function setHeaders( $values ) {
        if ( is_array( $values ) ) {
            $this->headers = array_merge( $this->headers, $values );
        }
    }

    function getBody() {
        return $this->data;
    }

    function getHeaders() {
        return $this->headers;
    }

    function loadDefaults( $name ) {
        if ( !$this->defaults ) {
            $path = __DIR__ . "/../data/{$name}.json";
            $this->defaults = json_decode( file_get_contents( $path ), true );
        }
    }

    /**
     * Generates random data for queue and donation insertion testing
     */
    public static function generateRandom() {
        //language codes
        $lang = array( 'en', 'de', 'fr' );

        $currency_codes = array( 'USD', 'GBP', 'EUR', 'ILS' );
        shuffle( $currency_codes );
        $currency = ( mt_rand( 0, 1 ) ) ? 'USD' : $currency_codes[0];

        $message = array(
            'contribution_tracking_id' => '',
            'optout' => mt_rand( 0, 1 ),
            'anonymous' => mt_rand( 0, 1 ),
            'comment' => mt_rand(),
            'utm_source' => mt_rand(),
            'utm_medium' => mt_rand(),
            'utm_campaign' => mt_rand(),
            'language' => $lang[array_rand( $lang )],
            'email' => mt_rand() . '@example.com',
            'first_name' => mt_rand(),
            'middle_name' => mt_rand(),
            'last_name' => mt_rand(),
            'street_address' => mt_rand(),
            'supplemental_address_1' => '',
            'city' => 'San Francisco',
            'state_province' => 'CA',
            'country' => 'USA',
            'countryID' => 'US',
            'postal_code' => mt_rand( 2801, 99999 ),
            'gateway' => 'insert_test',
            'gateway_txn_id' => mt_rand(),
            'response' => mt_rand(),
            'currency' => $currency,
            'original_currency' => $currency_codes[0],
            'original_gross' => mt_rand( 0, 10000 ) / 100,
            'fee' => '0',
            'gross' => mt_rand( 0, 10000 ) / 100,
            'net' => mt_rand( 0, 10000 ) / 100,
            'date' => date( 'r' ), //time(),
        );
        return $message;
    }
}

class TransactionMessage extends Message {
    protected $txn_id_key = 'gateway_txn_id';

    function __construct( $values = array() ) {
        $this->loadDefaults( "donation" );

        parent::__construct( array(
            $this->txn_id_key => mt_rand(),
            'order_id' => mt_rand(),
        ) + $values );

        $this->setHeaders( array(
            "persistent" => 'true',
            // FIXME: this might indicate a key error in our application code.
            "correlation-id" => "{$this->data['gateway']}-{$this->data[$this->txn_id_key]}",
            "JMSCorrelationID" => "{$this->data['gateway']}-{$this->data[$this->txn_id_key]}",
        ) );
    }

    function getGateway() {
        return $this->data['gateway'];
    }

    function getGatewayTxnId() {
        return $this->data[$this->txn_id_key];
    }

    function get( $key ) {
        return $this->data[$key];
    }
}

class RefundMessage extends TransactionMessage {
    function __construct( $values = array() ) {
        $this->loadDefaults( "refund" );

        $this->txn_id_key = 'gateway_refund_id';

        parent::__construct( $values );
    }
}

class RecurringPaymentMessage extends TransactionMessage {
    function __construct( $values = array() ) {
        $this->loadDefaults( "recurring_payment" );

        $this->txn_id_key = 'txn_id';

        parent::__construct( $values );
    }
}

class RecurringSignupMessage extends TransactionMessage {
    function __construct( $values = array() ) {
        $this->loadDefaults( "recurring_signup" );

        parent::__construct( $values );
    }
}
