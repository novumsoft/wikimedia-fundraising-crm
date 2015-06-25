<?php

use SmashPig\PaymentProviders\WorldPay\Audit\WorldPayAudit;

class WorldpayAuditProcessor extends BaseAuditProcessor {
    /**
     * Returns the configurable path to the recon files
     * @return string Path to the directory
     */
    protected function get_recon_dir() {
      return variable_get( 'worldpay_audit_recon_files_dir', WP_AUDIT_RECON_FILES_DIR );
    }

    /**
     * Returns the configurable path to the completed recon files
     * @return string Path to the directory
     */
    protected function get_recon_completed_dir() {
      return variable_get('worldpay_audit_recon_completed_dir', WP_AUDIT_RECON_COMPLETED_DIR);
    }

    /**
     * Returns the configurable path to the working log dir
     * @return string Path to the directory
     */
    protected function get_working_log_dir() {
      return variable_get('worldpay_audit_working_log_dir', WP_AUDIT_WORKING_LOG_DIR);
    }

    /**
     * Returns the configurable number of days we want to jump back in to the past,
     * to look for transactions in the payments logs.
     * @return in Number of days
     */
    protected function get_log_days_in_past() {
      return variable_get('worldpay_audit_log_search_past_days', WP_AUDIT_LOG_SEARCH_PAST_DAYS);
    }

    /**
     * The regex to use to determine if a file is a reconciliation file for this
     * gateway.
     * @return string regular expression
     */
    protected function regex_for_recon() {
      //WP recon files look like the following thing:
      //  MA.PISCESSW.#M.RECON.WIKI.D280514
      // or
      //  TranDetVer2_530860_11-26-2014_8'27'08 AM.csv
      return '/\.RECON\.WIKI\.|TranDetVer2/';
    }

    /**
     * The regex to use to determine if a file is a working log for this gateway.
     * @return string regular expression
     */
    protected function regex_for_working_log() {
      return '/\d{8}_worldpay\.working/';
    }

    /**
     * The regex to use to determine if a file is a compressed payments log.
     * @return string regular expression
     */
    protected function regex_for_compressed_log() {
      return '/.gz/';
    }

    /**
     * The regex to use to determine if a file is an uncompressed log for this
     * gateway.
     * @return string regular expression
     */
    protected function regex_for_uncompressed_log() {
      return '/worldpay_\d{8}/';
    }

    /**
     * Given a reconciliation file name, pull the date out of the filename and
     * return it in YYYYMMDD format.
     * @param string $file Name of the recon file (not full path)
     * @return string date in YYYYMMDD format
     */
    protected function get_recon_file_date( $file ){
      // FIXME: this is two-fer-one functionality.  Unmash.

      if ( preg_match( '/\.RECON\.WIKI\./', $file ) ) {
	  //WP recon files look like the following thing:
	  //  MA.PISCESSW.#M.RECON.WIKI.D280514
	  //For that, we'd want to return 20140518
	  $parts = explode('.', $file);
	  $end_piece = $parts[count($parts)-1];
	  $date = '20' . substr( $end_piece, 5, 2) . substr( $end_piece, 3, 2) . substr( $end_piece, 1, 2);
	  return $date;
      } elseif ( preg_match( '/TranDetVer2/', $file ) ) {
	  // These files have the equally crazy format:
	  //   TranDetVer2_60879_6-21-2014_8'27'38 AM.csv
	  $parts = explode( '_', $file );
	  $dateStr = $parts[2];
	  $dateParts = explode( '-', $dateStr );
	  $date = $dateParts[2] . str_pad( $dateParts[0], 2, '0' ) . str_pad( $dateParts[1], 2, '0' );
	  return $date;
      }
      throw new Exception( "Cannot parse date in surprise file {$file}" );
    }

    /**
     * Given the name of a working log file, pull out the date portion.
     * @param string $file Name of the working log file (not full path)
     * @return string date in YYYYMMDD format
     */
    protected function get_working_log_file_date($file) {
      //  '/\d{8}_worldpay\.working/';
      $parts = explode('_', $file);
      return $parts[0];
    }

    /**
     * Get the name of a compressed log file based on the supplied date.
     * @param string $date date in YYYYMMDD format
     * @return string Name of the file we're looking for
     */
    protected function get_compressed_log_file_name($date) {
    //  payments-worldpay-20140413.gz
      return "payments-worldpay-$date.gz";
    }

    /**
     * Get the name of an uncompressed log file based on the supplied date.
     * @param string $date date in YYYYMMDD format
     * @return string Name of the file we're looking for
     */
    protected function get_uncompressed_log_file_name($date) {
    //  payments-worldpay-20140413 - no extension. Weird.
      return "payments-worldpay-$date";
    }

    /**
     * Get the name of a working log file based on the supplied date.
     * @param string $date date in YYYYMMDD format
     * @return string Name of the file we're looking for
     */
    protected function get_working_log_file_name($date) {
      //  '/\d{8}_worldpay\.working/';
      return $date . '_worldpay.working';
    }

    /**
     * In order to create the distilled "working" log files, we need to grep through
     * the uncompressed log file for lines that contain a copy of our original
     * communication with the payment provider. This function returns the string
     * that, when used in a command-line grep statement against a payments log, will
     * pick out all instances of communication with the 3rd party that contain donor
     * information that is not included in the recon file, but which we need to
     * re-fuse with the recon data and save in civicrm.
     * @return string the pattern we're going to grep for in the payments logs
     */
    protected function get_log_distilling_grep_string() {
      return 'Request XML.*TMSTN.*<TransactionType>PT.*<RequestType>S<';
    }

    /**
     * Just parse one recon file.
     * @staticvar null $parserClass The class of the parser we're going to use
     * @param string $file Absolute location of the recon file you want to parse
     * @return mixed An array of recon data, or false
     */
    protected function parse_recon_file($file) {
      $recon_data = array();
      $recon_parser = new WorldPayAudit();

      $data = null;
      try {
	$data = $recon_parser->parseFile($file);
      } catch (Exception $e) {
	wmf_audit_log_error("Something went amiss with the recon parser while processing $file ", 'RECON_PARSE_ERROR');
      }

      //At this point, $data already contains the usable portions of the file.

      if (!empty($data)) {
	foreach ($data as $record) {
	  wmf_audit_echo(wmf_audit_echochar($record));
	}
      }
      if ( count( $data ) ){
		    return $data;
	    }
	    return false;
    }

    /**
     * Checks to see if the transaction already exists in civi
     * @param array $transaction Array of donation data
     * @return boolean true if it's in there, otherwise false
     */
    protected function main_transaction_exists_in_civi($transaction) {
      //go through the transactions and check to see if they're in civi
      if (wmf_civicrm_get_contributions_from_gateway_id('worldpay', $transaction['gateway_txn_id']) === false) {
	return false;
      } else {
	return true;
      }
    }

    /**
     * Checks to see if the refund or chargeback already exists in civi.
     * NOTE: This does not check to see if the parent is present at all, nor should
     * it. Call worldpay_audit_main_transaction_exists_in_civi for that.
     * @param array $transaction Array of donation data
     * @return boolean true if it's in there, otherwise false
     */
    protected function negative_transaction_exists_in_civi($transaction) {
      //go through the transactions and check to see if they're in civi
      if (wmf_civicrm_get_child_contributions_from_gateway_id('worldpay', $transaction['gateway_txn_id']) === false) {
	return false;
      } else {
	return true;
      }
    }

    /**
     * Checks the array to see if the data inside is describing a refund.
     * @param aray $record The transaction we would like to know is a refund or not.
     * @return boolean true if it is, otherwise false
     */
    protected function record_is_refund($record) {
      if (array_key_exists('type', $record) && $record['type'] === 'refund') {
	return true;
      }
      return false;
    }

    /**
     * Checks the array to see if the data inside is describing a chargeback.
     * @param aray $record The transaction we would like to know is a chargeback or
     * not.
     * @return boolean true if it is, otherwise false
     */
    protected function record_is_chargeback($record) {
      //@TODO: there should be more here. But we have no examples yet.
      return false;
    }

    /**
     * Return a date in the format YYYYMMDD for the given record
     * @param array $record A transaction, or partial transaction
     * @return string Date in YYYYMMDD format
     */
    protected function get_record_human_date($record) {
      if (array_key_exists('date', $record)) {
	return date(WMF_DATEFORMAT, $record['date']); //date format defined in wmf_dates
      }

      echo print_r($record, true);
      die(__FUNCTION__ . ': No date present in the record. This seems like a problem.');
    }

    /**
     * In order to match data in working payments logs with the data we're trying to
     * rebuild, we will need to grep for something. This, actually.
     * @param string $order_id The order id (transaction id) of what we're looking for.
     * @return string the pattern to grep for, for this specific transaction
     */
    protected function get_log_line_grep_string($order_id) {
      return "<OrderNumber>$order_id</OrderNumber>";
    }

    /**
     * The name of the outermost node in the log XML we're going to try to make
     * sense of. Usually, this is "XML", but apparently not all the time. Huh.
     * @return string node name
     */
    protected function get_log_line_xml_outermost_node() {
      return 'TMSTN';
    }

    /**
     * This would probably make more sense if this was not the same as the
     * outermost. WP is really... flat.
     * *ahem*
     * If the XML is complete (not truncated by syslog), then the data just comes
     * straight from the child nodes of the parent nodes defined here.
     * @return array Names of the nodes whose children describe donor data.
     */
    protected function get_log_line_xml_parent_nodes() {
      return array(
	'TMSTN'
      );
    }

    /**
     * If the XML happens to be incomplete (truncated by syslog), we avoid total
     * tragedy by searching whatever is left for individual complete data nodes.
     * Yes: This has happened before.
     * @return array Names of the nodes whose children describe donor data.
     */
    protected function get_log_line_xml_data_nodes() {
      return array(
	'OrderNumber',
	'Amount',
	'REMOTE_ADDR',
	'FirstName',
	'LastName',
	'Address1',
	'ZipCode',
	'CountryCode',
	'Email',
      );
    }

    /**
     * Grabs just the order_id out of a $transaction. This makes more sense if the
     * parser doesn't normalize as well as the WP one does, or even at all.
     * @param array $transaction possibly incomplete set of transaction data
     * @return string|false the order_id, or false if we can't figure it out
     */
    protected function get_order_id($transaction) {
      if (is_array($transaction) && array_key_exists('gateway_txn_id', $transaction)) {
	return $transaction['gateway_txn_id'];
      }
      return false;
    }

    /**
     * Normalize refund/chargeback messages before sending
     * @param array $record transaction data
     * @return array The normalized data we want to send
     */
    protected function normalize_negative($record) {
      $send_message = array(
	'gateway_refund_id' => 'RFD' . $record['gateway_txn_id'], //Notes from a previous version: "after intense deliberation, we don't actually care what this is at all."
	'gateway_parent_id' => $record['gateway_txn_id'], //gateway transaction ID
	'gross_currency' => $record['currency'], //currency code
	'gross' => $record['gross'], //amount
	'date' => $record['date'], //timestamp
	'gateway' => 'worldpay', //lcase
    //  'gateway_account' => $record['gateway_account'], //BOO. @TODO: Later.
    //  'payment_method' => $record['payment_method'], //Argh. Not telling you.
    //  'payment_submethod' => $record['payment_submethod'], //Still not telling you.
	'type' => $record['type'], //This actually works here. Weird, right?
      );
      return $send_message;
    }

    /**
     * Used in makemissing mode
     * This should take care of any extra data not sent in the recon file, that will
     * actually make qc choke. Not so necessary with WP, but this will need to
     * happen elsewhere, probably. Just thinking ahead.
     * @param array $record transaction data
     * @return type The normalized data we want to send.
     */
    protected function normalize_partial($record) {
      //@TODO: Still need gateway account to go in here when that happens.
      return $record;
    }

    /**
     * Takes whatever we've found in the xml, and whatever we've found in the recon
     * file, and makes them get along. If that works, it fuses them into a normal
     * message.
     * Presumably, if we're calling this function, we already have reason to believe
     * that the xml and recon data supplied go together. There should always be some
     * internal sanity checking, though. Stuff can get weird.
     * @param array $xml_data Transaction data from payments log xml
     * @param array $recon_data Transaction data from the recon file
     * @return array|false The re-fused and normalized data, or false if something
     * went wrong.
     */
    protected function normalize_and_merge_data($xml_data, $recon_data) {
      if (empty($xml_data) || empty($recon_data)) {
	$message = ": Missing one of the required arrays.\nXML Data: " . print_r($xml_data, true) . "\nRecon Data: " . print_r($recon_data, true);
	wmf_audit_log_error(__FUNCTION__ . $message, 'DATA_WEIRD');
	return false;
      }
      $normal = array();

      //first, normalize the $xml_data
      $nodemap = array(
	'OrderNumber' => 'gateway_txn_id',
	'Amount' => 'gross',
	'REMOTE_ADDR' => 'user_ip',
	'FirstName' => 'first_name',
	'LastName' => 'last_name',
	'Address1' => 'street_address', //N0NE PROVIDED
	'ZipCode' => 'postal_code', //0
	'CountryCode' => 'country',
	'Email' => 'email',
	'contribution_tracking_id' => 'contribution_tracking_id', //passed through, because I already set this manually
      );

      foreach ($xml_data as $key => $value) {
	if (array_key_exists($key, $nodemap)) {
	  $normal[$nodemap[$key]] = $value;
	}
      }

      if (array_key_exists('CurrencyId', $xml_data)) {
	$normal['currency'] = worldpay_audit_get_currency_code_from_stupid_number($xml_data['CurrencyId']);
      }

      $unsets = array(
	'street_address' => 'N0NE PROVIDED',
	'postal_code' => '0',
      );

      foreach ($unsets as $key => $value) {
	if (array_key_exists($key, $normal) && $normal[$key] === $value) {
	  unset($normal[$key]);
	}
      }

      //now, cross-reference what's in $recon_data and complain loudly if something doesn't match.
      //@TODO: see if there's a way we can usefully use [settlement_currency] and [settlement_amount]
      //from the recon file. This is actually super useful, but might require new import code and/or schema change.
      //
      //Check between the two sets... normal => recon
      $cross_check = array(
	'currency' => 'currency',
	'gross' => 'gross',
      );

      foreach ($cross_check as $check1 => $check2) {
	if (array_key_exists($check1, $normal) && array_key_exists($check2, $recon_data)) {
	  if (is_numeric($normal[$check1])) {
	    //I actually hate everything.
	    //Floatval all by itself doesn't do the job, even if I turn the !== into !=.
	    //"Data mismatch between normal gross (5) and recon gross (5)."
	    $normal[$check1] = (string) floatval($normal[$check1]);
	    $recon_data[$check2] = (string) floatval($recon_data[$check2]);
	  }
	  if ($normal[$check1] !== $recon_data[$check2]) {
	    wmf_audit_log_error("Data mismatch between normal $check1 ($normal[$check1]) and recon $check2 ($recon_data[$check2]). Investigation required. " . print_r($recon_data, true), 'DATA_INCONSISTENT');
	    return false;
	  }
	} else {
	  wmf_audit_log_error("Recon data is expecting normal $check1 and recon $check2, but at least one is missing. Investigation required. " . print_r($recon_data, true), 'DATA_INCONSISTENT');
	  return false;
	}
      }

      //just port these
      $believe = array(//because we have no choice
	'gateway', //ha
	'date',
	'payment_method',
	'payment_submethod',
	'gross',
      );

      foreach ($believe as $key) {
	if (array_key_exists($key, $recon_data)) {
	  $normal[$key] = $recon_data[$key];
	} else {
	  wmf_audit_log_error("Recon data is missing expected key $key. " . print_r($recon_data, true), 'DATA_INCOMPLETE');
	}
      }

      return $normal;
    }

    /**
     * HELPER FUNCTIONS
     * ...only this file will ever call them.
     */

    /**
     * Instead of using sane ISO codes for currencies, WP uses a long list of
     * numbers that everybody immediately turns back into the ISO codes at the
     * earliest convenience.
     * This array is backwards, because I straight up copied it from
     * DonationInterface. Not even sorry.
     * @staticvar array $flipped The currency code array in the format we need to do
     * an easy lookup.
     * @param int $number The number that appears in WP payments log XML for the
     * currency code.
     * @return string The ISO currency code
     */
    protected function get_currency_code_from_stupid_number($number) {
      static $flipped = array();
      if (empty($flipped)) {
	$flipped = array_flip(WorldPayAdapter::$CURRENCY_CODES);
      }
      if (array_key_exists($number, $flipped)) {
	return $flipped[$number];
      } else {
	wmf_audit_log_error(__FUNCTION__ . ": No currency found for code $number", 'MISSING_MANDATORY_DATA');
      }
    }
}
