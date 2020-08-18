<?php
$templateDir = tempnam(sys_get_temp_dir(), 'crmunit');
unlink($templateDir);
mkdir($templateDir);
define('CIVICRM_TEMPLATE_COMPILEDIR', $templateDir);
define('WMF_CRM_PHPUNIT', TRUE);
define('DRUPAL_ROOT', realpath(__DIR__) . "/../../drupal");
require_once(DRUPAL_ROOT . "/sites/all/modules/wmf_common/tests/includes/BaseWmfDrupalPhpUnitTestCase.php");
require_once(DRUPAL_ROOT . "/sites/all/modules/wmf_audit/tests/includes/BaseAuditTestCase.php");
require_once(DRUPAL_ROOT . "/sites/all/modules/offline2civicrm/tests/includes/BaseChecksFileTest.php");
require_once(DRUPAL_ROOT . "/sites/all/modules/wmf_communication/tests/phpunit/CiviMailTestBase.php");

// Argh.  Crib from _drush_bootstrap_drupal_site_validate
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

chdir(DRUPAL_ROOT);
require_once("includes/bootstrap.inc");
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

// Drupal just usurped PHPUnit's error handler.  Kick it off the throne.
restore_error_handler();

// Load contrib libs so tests can inherit from them.
require_once(DRUPAL_ROOT . '/../vendor/autoload.php');
// And explicitly load some DonationInterface things that it doesn't export via Composer
require_once(DRUPAL_ROOT . '/../vendor/wikimedia/donation-interface/tests/phpunit/TestConfiguration.php');
require_once(DRUPAL_ROOT . '/../vendor/wikimedia/donation-interface/tests/phpunit/includes/test_gateway/test.adapter.php');
require_once(DRUPAL_ROOT . '/../vendor/wikimedia/donation-interface/tests/phpunit/includes/test_gateway/TestingGlobalCollectAdapter.php');
require_once(DRUPAL_ROOT . '/../vendor/wikimedia/donation-interface/tests/phpunit/includes/test_gateway/TestingGlobalCollectOrphanAdapter.php');
require_once(DRUPAL_ROOT . '/../vendor/wikimedia/donation-interface/tests/phpunit/includes/test_gateway/TestingPaypalExpressAdapter.php');

putenv('CIVICRM_SETTINGS=' . DRUPAL_ROOT . '/sites/default/civicrm.settings.php');
require_once DRUPAL_ROOT . '/sites/default/civicrm/extensions/org.wikimedia.omnimail/tests/phpunit/bootstrap.php';
civicrm_initialize();
// Uncomment this if you would like to see all of the
// watchdog messages when a test fails. Can be useful
// to debug tests in CI where you can't see the syslog.
/*
if (!defined('PRINT_WATCHDOG_ON_TEST_FAIL')) {
  define('PRINT_WATCHDOG_ON_TEST_FAIL', TRUE);
}
*/
