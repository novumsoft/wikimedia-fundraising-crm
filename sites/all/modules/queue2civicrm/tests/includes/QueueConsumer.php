<?php

class QueueConsumer {
    protected $queue_name = 'civiCRM_test';
    protected $url = 'tcp://localhost:61613';

    function __construct() {
		$this->recip_email = variable_get('wmf_test_settings_email', '');

        variable_set( 'queue2civicrm_subscription', "/queue/{$this->queue_name}" );
        variable_set( 'queue2civicrm_url', $this->url );
        variable_set( 'queue2civicrm_failmail', $this->recip_email );
    }

    function tearDown(){
      parent::tearDown();
    }

    //determine that we are in fact able to read and write to activeMQ
    function testStompPushPop() {
        $this->emptyQueue();
        //queue2civicrm_insertmq_form_submit($form, &$form_state) $form_state['values'] appears to be where all the $key=>$value form pairs live.
        ////Just fake it out. :p queue2civicrm_generate_message() will do nicely.
        $message = Message::generateRandom();
        //I think we want gateway_txn_id and contribution_tracking_id to match much the same way we did before.
        $message['gateway_txn_id'] = "civiTest";
        $message['contribution_tracking_id'] = $message['gateway_txn_id'];
        $message['queue'] = variable_get('queue2civicrm_subscription', '/queue/oopsie');
        $message = array('values' => $message);

        $ret = queue2civicrm_insertmq_form_submit(array(), $message);
        $message_return = $this->getItemFromQueue();
        $this->assertTrue(is_object($message_return), "No message was returned");
        $body = json_decode($message_return->body, true);
        foreach($message['values'] as $key=>$value){
            $this->assertTrue($body[$key] == $value, $body[$key] . " != $value");
        }
    }

    function testConnect(){
        $this->assertDrushLogEmpty(true);
        variable_set('queue2civicrm_url', 'tcp://bananas:123');
        $con = wmf_common_stomp_connection(true);
        $this->assertTrue($con === false, "Connection did not fail appropriately.");
        //check for the drush errors...
        $this->assertDrushLogEmpty(false);
        $this->assertCheckDrushLog('STOMP_BAD_CONNECTION', true, "Appropriate Drush error was not thrown.");

        //put everything back to normaltestCurrencyConversion
        $this->assertDeleteDrushLog();
        variable_set('queue2civicrm_url', 'tcp://localhost:61613'); //@fixme: This should be grabbing from an ini or something.
        $con = wmf_common_stomp_connection(true);

        $this->assertTrue($con !== false, "Connection failed, and should have worked the second time 'round.");
    }

    function testRequiredFields(){
        $this->assertDeleteDrushLog();

        //Should be required:
        //first, last, email, amount, currency, payment type, gateway transaction ID
        $required = array(
            'email' => $this->recip_email,
            'gross' => '7.77',
            'original_currency' => 'USD',
            'gateway' => 'something',
            'gateway_txn_id' => '11235' . time()
        );
        queue2civicrm_import( $required );
        $this->assertDrushLogEmpty(true);

        foreach ($required as $key=>$value){
            $msg = $required;
            unset($msg[$key]);
            queue2civicrm_import( $msg );
            $this->assertDrushLogEmpty(false);
            $this->assertCheckDrushLog('CIVI_REQ_FIELD', true, "Missing required $key does not trigger an error.");
            $this->assertDeleteDrushLog();
        }

        $test_name = array(
            'first_name' => 'Testy',
            'middle_name' => 'T.',
            'last_name' => 'Testaberger',
            'gross' => '8.88',
            'gateway_txn_id' => '12358' . time()
        );

        $msg = array_merge($required, $test_name);
        queue2civicrm_import( $msg );
        $this->assertDrushLogEmpty(true);

    }

    function testCurrencyConversion(){
        $test_currency_conversion = array(
            'email' => $this->recip_email,
            'gross' => '7.77',
            'original_currency' => 'EUR',
            'gateway' => 'something',
            'gateway_txn_id' => '11235',
            'contribution_tracking_id' => '' //don't actually need these in the DB, as we're just testing the currency conversions.
        );
        $msg = wmf_civicrm_verify_message_and_stage($test_currency_conversion);
        $this->assertTrue($test_currency_conversion['gross'] == $msg['original_gross'], "Original Gross in converted message does not match actual original gross.");
        // commenting out below assertion - not a foolproof assertion ~awjrichards
        //$this->assertTrue($test_currency_conversion['gross'] != $msg['gross'], "Gross is identical: No conversion was done (unless " . $test_currency_conversion['original_currency'] . " = USD for a minute");

        $test_currency_conversion['original_currency'] = 'USD';
        $msg = wmf_civicrm_verify_message_and_stage($test_currency_conversion);
        $this->assertTrue($test_currency_conversion['gross'] == $msg['original_gross'], "Original Gross in converted message does not match actual original gross.");
        $this->assertTrue($test_currency_conversion['gross'] == $msg['gross'], "USD to USD Gross is not identical!");
    }

    function testGetTopError(){
        $this->assertDeleteDrushLog();
        $error = _queue2civicrm_get_top_new_drush_error();
        //should return false
        $this->assertFalse($error, "There are no drush errors to return, but we got '$error'");

        //now throw three errors, and make sure the most severe is returned.
        drush_set_error("IMPORT_TAG", "Test Error Message #1");
        drush_set_error("CIVI_CONFIG", "Test Error Message #2");
        drush_set_error("IMPORT_CONTACT", "Test Error Message #3");
        $error = _queue2civicrm_get_top_new_drush_error();

        //looking for the CIVI_CONFIG error
        $this->assertTrue($error['err_code'] === 'CIVI_CONFIG', "New top error should be CIVI_CONFIG; returned " . $error['err_code']);
        $this->assertTrue($error['err_text'] === "Messages:\n  Test Error Message #2", "Expected message not returned: " . $error['err_text']);

        //now stack some slightly less important errors and see if we get exactly the new ones.
        drush_set_error("IMPORT_CONTACT", "Test Error Message #4");
        drush_set_error("IMPORT_CONTACT", "Test Error Message #5");
        drush_set_error("IMPORT_CONTACT", "Test Error Message #6");
        $error = _queue2civicrm_get_top_new_drush_error();

        $this->assertTrue($error['err_code'] === 'IMPORT_CONTACT', "New top error should be IMPORT_CONTACT; returned " . $error['err_code']);
        $this->assertTrue($error['err_text'] === "Messages:\n  Test Error Message #4\n  Test Error Message #5\n  Test Error Message #6", "Expected message not returned: " . $error['err_text']);

        queue2civicrm_failmail($error, "This is a test message!", true);
        queue2civicrm_failmail($error, "This is another test message!", false);
    }

    function testBatchProcess(){
        //clear and add test messages to the testing queue.
        $this->emptyQueue();

        $messages_in = array();
        for ($i=0; $i<10; ++$i){
            $message = Message::generateRandom();
            unset($message['contribution_tracking_id']);
            $message['gateway'] = 'CiviTest' . $i;
            $message['gateway_txn_id'] = time();
            $message['queue'] = variable_get('queue2civicrm_subscription', '/queue/oopsie');
            //create some havoc
            if($i == 3){
                unset($message['email']); //this should throw a nice error and email and things.
            }
            $messages_in[] = $message;
            $message = array('values' => $message);
            $ret = queue2civicrm_insertmq_form_submit(array(), $message);
        }

        $this->assertDeleteDrushLog();

        queue2civicrm_batch_process();

        //check the final drush log for all the relevant entries
        $this->assertDrushLogEmpty(false);
        $this->assertCheckDrushLog('CIVI_REQ_FIELD', true, "There should be an error regarding the missing email address.");

    }

    function getItemFromQueue(){
      $con = wmf_common_stomp_connection();
      $this->assertTrue(is_object($con), "Could not establish stomp connection");
      $subscription_queue = variable_get('queue2civicrm_subscription', '/queue/test');
      if ($con) {
        $con->subscribe($subscription_queue, array('ack' => 'client'));

        $msg = $con->readFrame();

        // Skip processing if no message to process.
        if ($msg !== FALSE) {
          watchdog('queue2civicrm', 'Read frame:<pre>' . check_plain(print_r($msg, TRUE)) . '</pre>');
          set_time_limit(60);
          try {
            $con->ack($msg);
            return $msg;
          }
          catch (Exception $e) {
            watchdog('queue2civicrm', 'Could not process frame from queue.', array(), WATCHDOG_ERROR);
          }
        }
        else {
          watchdog('queue2civicrm', 'Nothing to process.');
        }
        $con->unsubscribe( $subscription_queue );
      }
      return FALSE;
    }

    function emptyQueue(){
        while (is_object($this->getItemFromQueue())){
            //uh. Yeah. That. Weirdest while loop EVAR.
        }
    }

    function assertDeleteDrushLog(){
        $error_log =& drush_get_context('DRUSH_ERROR_LOG', array());
        $error_log = array();  //gwa ha ha ha
        $error = drush_get_error_log();
        $this->assertTrue(empty($error), "Drush error log should now be empty" . print_r($error, true));
    }

    function assertCheckDrushLog($drush_error_type, $exists, $assertFailMessage){
        $error = drush_get_error_log();
        $this->assertTrue(array_key_exists($drush_error_type, $error) === $exists, $assertFailMessage . "\nLooking for $drush_error_type\n" . print_r($error, true));
    }

    function assertDrushLogEmpty($state){
        $error = drush_get_error_log();
        $message = "Drush log should " . (($state)?"":"not ") . "be empty\n" . print_r($error, true);
        $this->assertTrue(empty($error) === $state, $message);
    }

    function assertEmailIsSet(){
        if ($this->recip_email == ''){
            $this->fail("Recipient email for testing is not configured. Please configure this value in the wmf_test_settings module.");
            return false;
        } else {
            return true;
        }
    }

    /**
     * Test methods in Queue2civicrmTrxnCounter and associated wrapper functions
     */
    function testQueue2CivicrmTrxnCounter() {
      $trxn_counter = Queue2civicrmTrxnCounter::instance();
      $trxn_counter->foo = 'bar';
      $this->assertIdentical( $trxn_counter, Queue2civicrmTrxnCounter::instance(),
		 'Queue2civicrmTrxnCounter::instance() not returning identical objects.');
      
      // make sure adding and fetching counts work
      Queue2civicrmTrxnCounter::instance()->increment( 'lions' );
      $lions_count = $trxn_counter->get_count_total( 'lions' );
      $this->assertEqual( $lions_count, 1, 'Gateway count test failed, expected 1, got ' . $lions_count );
      Queue2civicrmTrxnCounter::instance()->increment( 'lions', 3 );
      $lions_count = $trxn_counter->get_count_total( 'lions' );
      $this->assertEqual( $lions_count, 4, 'Gateway count test failed, expected 4, got ' . $lions_count );
      $overall_count = $trxn_counter->get_count_total();
      $this->assertEqual( $overall_count, 4, 'Overall gateway count test failed.  Expected 4, got ' . $overall_count );

      // make sure that our overall counts are right and that we didn't get a count for 'foo' gateway
      Queue2civicrmTrxnCounter::instance()->increment( 'bears' );
      Queue2civicrmTrxnCounter::instance()->increment( 'foo' );
      $this->assertFalse( in_array( 'foo', array_keys( $trxn_counter->get_trxn_counts())), 'Was able to set an invalid gateway.' );
      $overall_count = $trxn_counter->get_count_total();
      $this->assertEqual( $overall_count, 5, 'Overall gateway count test failed.  Expected 5, got ' . $overall_count );

      // make sure gateways are properly being set.
	  $allGateways = 'lions, bears, foo';
      $gateways = implode( ", ", array_keys( $trxn_counter->get_trxn_counts()));
      $this->assertEqual( $allGateways, $gateways,
	    'Gateways are not properly being set in Queue2civicrmTrxnCounter. Expected "' . $allGateways . '", got "' . $gateways . '".' );
    }

}

?>
