<?php

/**
 * @group Adyen
 * @group WmfAudit
 */
class AdyenAuditTest extends BaseAuditTestCase {

  protected $contact_id;

  protected $contribution_ids = [];

  public function setUp() {
    parent::setUp();

    $dirs = [
      'wmf_audit_log_archive_dir' => __DIR__ . '/data/logs/',
      'adyen_audit_recon_completed_dir' => $this->getTempDir(),
      'adyen_audit_working_log_dir' => $this->getTempDir(),
    ];

    foreach ($dirs as $var => $dir) {
      if (!is_dir($dir)) {
        mkdir($dir);
      }
      variable_set($var, $dir);
    }

    $old_working = glob($dirs['adyen_audit_working_log_dir'] . '*');
    foreach ($old_working as $zap) {
      if (is_file($zap)) {
        unlink($zap);
      }
    }

    variable_set('adyen_audit_log_search_past_days', 7);

    // Fakedb doesn't fake the original txn for refunds, so add one here
    $existing = wmf_civicrm_get_contributions_from_gateway_id('adyen', '4522268860022701');
    if ($existing) {
      // Previous test run may have crashed before cleaning up
      $contribution = $existing[0];
    }
    else {
      $msg = [
        'contribution_tracking_id' => 92598312,
        'currency' => 'USD',
        'date' => 1455825706,
        'email' => 'asdf@asdf.com',
        'gateway' => 'adyen',
        'gateway_txn_id' => '4522268860022701',
        'gross' => 1.00,
        'payment_method' => 'cc',
      ];
      $contribution = wmf_civicrm_contribution_message_import($msg);
    }
    $this->contact_id = $this->ids['Contact'][$contribution['contact_id']] = $contribution['contact_id'];
    $this->contribution_ids[] = $contribution['id'];

    // and another for the chargeback
    $existing = wmf_civicrm_get_contributions_from_gateway_id('adyen', '4555568860022701');
    if ($existing) {
      // Previous test run may have crashed before cleaning up
      $contribution = $existing[0];
    }
    else {
      $msg = [
        'contribution_tracking_id' => 92598318,
        'currency' => 'USD',
        'date' => 1443724034,
        'email' => 'asdf@asdf.org',
        'gateway' => 'adyen',
        'gateway_txn_id' => '4555568860022701',
        'gross' => 1.00,
        'payment_method' => 'cc',
      ];
      $contribution = wmf_civicrm_contribution_message_import($msg);
    }
    $this->contact_id = $this->ids['Contact'][$contribution['contact_id']] = $contribution['contact_id'];
    $this->contribution_ids[] = $this->ids['Contribution'][] = $contribution['id'];
  }

  public function tearDown() {
    parent::tearDown();
  }

  public function auditTestProvider() {
    return [
      [
        __DIR__ . '/data/Adyen/donation_new/',
        [
          'donations' => [
            [
              'contribution_tracking_id' => '43992337',
              'city' => 'asdf',
              'country' => 'US',
              'currency' => 'USD',
              'date' => 1487484651,
              'email' => 'asdf@asdf.com',
              'fee' => '0.24',
              'first_name' => 'asdf',
              'gateway' => 'adyen',
              'gateway_account' => 'TestMerchant',
              'gateway_txn_id' => '5364893193133131',
              'gross' => '1.00',
              'language' => 'en',
              'last_name' => 'asdff',
              'order_id' => '43992337.0',
              'payment_method' => 'cc',
              'payment_submethod' => 'visa',
              'postal_code' => '11111',
              'recurring' => '',
              'state_province' => 'AK',
              'street_address' => 'asdf',
              'user_ip' => '77.177.177.77',
              'utm_campaign' => 'C13_en.wikipedia.org',
              'utm_medium' => 'sidebar',
              'utm_source' => '..cc',
              'settled_gross' => '0.76',
              'settled_currency' => 'USD',
              'settled_fee' => '0.24',
            ],
          ],
        ],
      ],
      [
        __DIR__ . '/data/Adyen/refund/',
        [
          'refund' => [
            [
              'date' => 1455128736,
              'gateway' => 'adyen',
              'gateway_parent_id' => '4522268860022701',
              'gateway_refund_id' => '4522268869855336',
              'gross' => '1.00',
              'gross_currency' => 'USD',
              'type' => 'refund',
            ],
          ],
        ],
      ],
      [
        __DIR__ . '/data/Adyen/chargeback/',
        [
          'refund' => [
            [
              'date' => 1455128736,
              'gateway' => 'adyen',
              'gateway_parent_id' => '4555568860022701',
              'gateway_refund_id' => '4555568869855336',
              'gross' => '1.00',
              'gross_currency' => 'USD',
              'type' => 'chargeback',
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * @dataProvider auditTestProvider
   */
  public function testParseFiles($path, $expectedMessages) {
    variable_set('adyen_audit_recon_files_dir', $path);

    $this->runAuditor();

    $this->assertMessages($expectedMessages);
  }

  protected function runAuditor() {
    $options = [
      'fakedb' => TRUE,
      'quiet' => TRUE,
      'test' => TRUE,
      #'verbose' => 'true', # Uncomment to debug.
    ];
    $audit = new AdyenAuditProcessor($options);
    $audit->run();
  }
}
