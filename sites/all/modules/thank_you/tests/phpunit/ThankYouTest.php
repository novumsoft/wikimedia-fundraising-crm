<?php

use wmf_communication\TestMailer;

/**
 * @group ThankYou
 */
class ThankYouTest extends BaseWmfDrupalPhpUnitTestCase {

	/**
	 * Id of the contribution created in the setup function.
	 *
	 * @var int
	 */
	protected $contribution_id;
	protected $contact_id;
	protected $old_civimail;
	protected $old_civimail_rate;
	protected $message;

	public function setUp() {
		if ( !defined( 'WMF_UNSUB_SALT' ) ) {
			define( 'WMF_UNSUB_SALT', 'abc123' );
		}
		parent::setUp();
		civicrm_initialize();
		TestMailer::setup();
		$this->message = array(
			'city' => 'Somerville',
			'country' => 'US',
			'currency' => 'USD',
			'date' => time(),
			'email' => 'generousdonor@example.org',
			'first_name' => 'Test',
			'last_name' => 'Es',
			'language' => 'en',
			'gateway' => 'test_gateway',
			'gateway_txn_id' => mt_rand(),
			'gross' => '1.23',
			'payment_method' => 'cc',
			'postal_code' => '02144',
			'state_province' => 'MA',
			'street_address' => '1 Davis Square',
		);
		$this->old_civimail = variable_get( 'thank_you_add_civimail_records', 'false' );
		$this->old_civimail_rate = variable_get( 'thank_you_civimail_rate', 1.0 );

		$contribution = wmf_civicrm_contribution_message_import( $this->message );

		$this->contact_id = $contribution['contact_id'];
		$this->contribution_id = $contribution['id'];
	}

	public function tearDown() {
		parent::cleanUpContact( $this->contact_id );
		variable_set( 'thank_you_add_civimail_records', $this->old_civimail );
		variable_get( 'thank_you_civimail_rate', $this->old_civimail_rate );
		parent::tearDown();
	}

	/**
	 * @throws \CiviCRM_API3_Exception
	 */
	public function testGetEntityTagDetail() {
		unset (\Civi::$statics['wmf_civicrm']['tags']);
		$tag1 = $this->ensureTagExists( 'smurfy' );
		$tag2 = $this->ensureTagExists( 'smurfalicious' );

		$this->callAPISuccess(
			'EntityTag',
			'create',
			array(
				'entity_id' => $this->contribution_id,
				'entity_table' => 'civicrm_contribution',
				'tag_id' => 'smurfy'
			)
		);
		$this->callAPISuccess(
			'EntityTag',
			'create',
			array(
				'entity_id' => $this->contribution_id,
				'entity_table' => 'civicrm_contribution',
				'tag_id' => 'smurfalicious'
			)
		);

		$smurfiestTags = wmf_civicrm_get_tag_names( $this->contribution_id );
		$this->assertEquals( array( 'smurfy', 'smurfalicious' ), $smurfiestTags );

		$this->callAPISuccess( 'Tag', 'delete', array( 'id' => $tag1 ) );
		$this->callAPISuccess( 'Tag', 'delete', array( 'id' => $tag2 ) );
	}

	public function testSendThankYou() {
		variable_set( 'thank_you_add_civimail_records', 'false' );
		$result = thank_you_for_contribution( $this->contribution_id );
		$this->assertTrue( $result );
		$this->assertEquals( 1, TestMailer::countMailings() );
		$sent = TestMailer::getMailing( 0 );
		$this->assertEquals( $this->message['email'], $sent['to_address'] );
		$this->assertEquals(
			"{$this->message['first_name']} {$this->message['last_name']}",
			$sent['to_name']
		);
		$expectedBounce = "ty.{$this->contact_id}.{$this->contribution_id}" .
			'@donate.wikimedia.org';
		$this->assertEquals( $expectedBounce, $sent['reply_to'] );
		$this->assertRegExp( '/\$ 1.23/', $sent['html'] );
    $this->assertNotRegExp( '/Wikimedia Endowment/', $sent['html'] );
		$expectedSubjectTemplate = trim(file_get_contents(
		  __DIR__ .
      "/../../templates/subject/thank_you.{$this->message['language']}.subject"
    ));
		$expectedSubject = str_replace(
		  '{{ (currency ~ " " ~ amount) | l10n_currency(locale) }}',
      TwigLocalization::l10n_currency('USD 1.23'),
		  $expectedSubjectTemplate
    );
		$this->assertEquals( $expectedSubject, $sent['subject']);

		// Check for tax information, DAF emails have this removed
		$this->assertRegExp( '/tax-exempt number/', $sent['html'] );
	}

	public function testSendThankYouAddCiviMailActivity() {
		variable_set( 'thank_you_add_civimail_records', 'true' );
		variable_set( 'thank_you_civimail_rate', 1.0 );
		$result = thank_you_for_contribution( $this->contribution_id );
		$this->assertTrue( $result );
		$activity = civicrm_api3(
			'Activity',
			'getSingle',
			array(
				'contact_id' => $this->contact_id,
				'activity_type_id' => CRM_Core_PseudoConstant::getKey(
					'CRM_Activity_BAO_Activity',
					'activity_type_id',
					'Email'
				)
			)
		);
		$this->assertEquals( 1, TestMailer::countMailings() );
		$sent = TestMailer::getMailing( 0 );
		$this->assertEquals( $activity['details'], $sent['html'] );
	}

  public function testSendEndowmentThankYou() {
    variable_set( 'thank_you_add_civimail_records', 'false' );
    variable_set( 'thank_you_endowment_from_name', 'Endowment TY Sender' );
    $endowmentFinancialType = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'financial_type_id', 'Endowment Gift');
    CRM_Core_DAO::executeQuery("
      UPDATE civicrm_contribution SET financial_type_id = $endowmentFinancialType
      WHERE id = {$this->contribution_id} 
    ");
    $result = thank_you_for_contribution( $this->contribution_id );
    $this->assertTrue( $result );
    $this->assertEquals( 1, TestMailer::countMailings() );
    $sent = TestMailer::getMailing( 0 );
    $this->assertEquals( $this->message['email'], $sent['to_address'] );
    $this->assertEquals(
      "{$this->message['first_name']} {$this->message['last_name']}",
      $sent['to_name']
    );
    $this->assertEquals(
      'Endowment TY Sender',
      $sent['from_name']
    );
    $expectedBounce = "ty.{$this->contact_id}.{$this->contribution_id}" .
      '@donate.wikimedia.org';
    $this->assertEquals( $expectedBounce, $sent['reply_to'] );
    $this->assertRegExp( '/\$ 1.23/', $sent['html'] );
    $this->assertRegExp( '/Wikimedia Endowment/', $sent['html'] );
    $expectedSubjectTemplate = trim(file_get_contents(
      __DIR__ .
      "/../../templates/subject/endowment_thank_you.{$this->message['language']}.subject"
    ));
    $expectedSubject = str_replace(
      '{{ (currency ~ " " ~ amount) | l10n_currency(locale) }}',
      TwigLocalization::l10n_currency('USD 1.23'),
      $expectedSubjectTemplate
    );
    $this->assertEquals($expectedSubject, $sent['subject']);
  }

  /**
   * Test that DAF (Donor Advised Fund) thank you mails do not have tax information
   */
  public function testSendDAFThankYou() {
    variable_set( 'thank_you_add_civimail_records', 'false' );

    // Set the gift source to Donor Advised Fund
    $custom_field_name = wmf_civicrm_get_custom_field_name( 'Gift Source' );
    civicrm_api3( 'Contribution', 'create', array(
        'id' => $this->contribution_id,
        $custom_field_name => 'Donor Advised Fund',
    ) );

    $result = thank_you_for_contribution( $this->contribution_id );
    $this->assertTrue( $result );
    $this->assertEquals( 1, TestMailer::countMailings() );
    $sent = TestMailer::getMailing( 0 );
    $this->assertEquals( $this->message['email'], $sent['to_address'] );
    $this->assertEquals(
        "{$this->message['first_name']} {$this->message['last_name']}",
        $sent['to_name']
    );
    $expectedBounce = "ty.{$this->contact_id}.{$this->contribution_id}" .
                      '@donate.wikimedia.org';
    $this->assertEquals( $expectedBounce, $sent['reply_to'] );
    $this->assertRegExp( '/\$ 1.23/', $sent['html'] );

    // Check that tax information has been removed
    $this->assertNotRegExp( '/tax-exempt number/', $sent['html'] );
  }


	/**
	 * Helper function to protect test against cleanup issues.
	 *
	 * @param string $name
	 * @return int
	 */
	public function ensureTagExists( $name ) {
		$tags = $this->callAPISuccess( 'EntityTag', 'getoptions', array(
			'field' => 'tag_id'
		) );
		if ( in_array( $name, $tags['values'] ) ) {
			return array_search( $name, $tags['values'] );
		}
		$tag = $this->callAPISuccess(
			'Tag',
			'create',
			array(
				'used_for' => 'civicrm_contribution',
				'name' => $name
			)
		);
		$this->callAPISuccess( 'Tag', 'getfields', array( 'cache_clear' => 1 ) );
		return $tag['id'];
	}
}
