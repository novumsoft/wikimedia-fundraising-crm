<?php

use SmashPig\Core\Context;
use SmashPig\PaymentProviders\Amazon\Tests\AmazonTestConfiguration;

/**
 * @group Amazon
 * @group WmfAudit
 */
class AmazonAuditTest extends BaseAuditTestCase {

  protected $contact_id;

  protected $contribution_id;

  public function setUp() {
    parent::setUp();

    // Use the test configuration for SmashPig
    $ctx = Context::get();
    $config = AmazonTestConfiguration::instance($ctx->getGlobalConfiguration());
    $ctx->setProviderConfiguration($config);

    $dirs = [
      'wmf_audit_log_archive_dir' => __DIR__ . '/data/logs/',
      'amazon_audit_recon_completed_dir' => $this->getTempDir(),
      'amazon_audit_working_log_dir' => $this->getTempDir(),
    ];

    foreach ($dirs as $var => $dir) {
      if (!is_dir($dir)) {
        mkdir($dir);
      }
      variable_set($var, $dir);
    }

    $old_working = glob($dirs['amazon_audit_working_log_dir'] . '*');
    foreach ($old_working as $zap) {
      if (is_file($zap)) {
        unlink($zap);
      }
    }

    variable_set('amazon_audit_log_search_past_days', 7);

    // Fakedb doesn't fake the original txn for refunds, so add one here
    $existing = wmf_civicrm_get_contributions_from_gateway_id('amazon', 'P01-4968629-7654321-C070794');
    if ($existing) {
      // Previous test run may have crashed before cleaning up
      $contribution = $existing[0];
    }
    else {
      $msg = [
        'contribution_tracking_id' => 2476135333,
        'currency' => 'USD',
        'date' => 1443724034,
        'email' => 'lurch@yahoo.com',
        'gateway' => 'amazon',
        'gateway_txn_id' => 'P01-4968629-7654321-C070794',
        'gross' => 1.00,
        'payment_method' => 'amazon',
      ];
      $contribution = wmf_civicrm_contribution_message_import($msg);
    }
    $this->contact_id = $contribution['contact_id'];
    $this->contribution_id = $contribution['id'];
  }

  public function tearDown() {
    $this->callAPISuccess('Contribution', 'delete', ['id' => $this->contribution_id]);
    $this->callAPISuccess('Contact', 'delete', ['id' => $this->contact_id]);
    parent::tearDown();
  }

  public function auditTestProvider() {
    return [
      [
        __DIR__ . '/data/Amazon/donation/',
        [
          'donations' => [
            [
              'contribution_tracking_id' => '87654321',
              'country' => 'US',
              'currency' => 'USD',
              'date' => 1443723034,
              'email' => 'nonchalant@gmail.com',
              'fee' => '0.59',
              'first_name' => 'Test',
              'gateway' => 'amazon',
              'gateway_account' => 'default',
              'gateway_txn_id' => 'P01-1488694-1234567-C034811',
              'gross' => '10.00',
              'language' => 'en',
              'last_name' => 'Person',
              'order_id' => '87654321-0',
              'payment_method' => 'amazon',
              'payment_submethod' => '',
              'recurring' => '',
              'user_ip' => '1.2.3.4',
              'utm_campaign' => 'C13_en.wikipedia.org',
              'utm_medium' => 'sidebar',
              'utm_source' => '..amazon',
            ],
          ],
        ],
      ],
      [
        __DIR__ . '/data/Amazon/refund/',
        [
          'refund' => [
            [
              'date' => 1444087249,
              'gateway' => 'amazon',
              'gateway_parent_id' => 'P01-4968629-7654321-C070794',
              'gateway_refund_id' => 'P01-4968629-7654321-R017571',
              'gross' => '1.00',
              'gross_currency' => 'USD',
              'type' => 'refund',
            ],
          ],
        ],
      ],
      [
        __DIR__ . '/data/Amazon/chargeback/',
        [
          'refund' => [
            [
              'date' => 1444087249,
              'gateway' => 'amazon',
              'gateway_parent_id' => 'P01-4968629-7654321-C070794',
              'gateway_refund_id' => 'P01-4968629-7654321-R017571',
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
    variable_set('amazon_audit_recon_files_dir', $path);

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
    $audit = new AmazonAuditProcessor($options);
    $audit->run();
  }
}
