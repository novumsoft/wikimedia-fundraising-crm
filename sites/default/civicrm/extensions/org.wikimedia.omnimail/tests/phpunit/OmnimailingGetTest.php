<?php

use Civi\Test\EndToEndInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
require_once __DIR__ . '/OmnimailBaseTestClass.php';

/**
 * FIXME - Add test description.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class OmnimailingGetTest extends OmnimailBaseTestClass {

  /**
   * Example: Test that a version is returned.
   */
  public function testOmnimailingGet() {
    $responses = array(
      file_get_contents(__DIR__ . '/Responses/MailingGetResponse1.txt'),
      file_get_contents(__DIR__ . '/Responses/AggregateGetResponse1.txt'),
      file_get_contents(__DIR__ . '/Responses/GetMailingTemplateResponse.txt'),
      file_get_contents(__DIR__ . '/Responses/GetMailingTemplateResponse2.txt'),
      file_get_contents(__DIR__ . '/Responses/GetMailingTemplateResponse2.txt'),
      file_get_contents(__DIR__ . '/Responses/LoginHtml.html'),
      '',
      file_get_contents(__DIR__ . '/Responses/QueryListHtml.html'),
      file_get_contents(__DIR__ . '/Responses/LoginHtml.html'),
      '',
      file_get_contents(__DIR__ . '/Responses/QueryListHtml.html'),
    );
    $mailings = $this->callAPISuccess('Omnimailing', 'get', array('mail_provider' => 'Silverpop', 'client' => $this->getMockRequest($responses), 'username' => 'Donald', 'password' => 'quack'));
    $this->assertEquals(2, $mailings['count']);
    $firstMailing = $mailings['values'][0];
    $this->assertEquals('cool email', $firstMailing['subject']);
    $this->assertEquals('WHEN  (country is equal to ILAND IsoLang is equal to heAND latest_donation_date is before Jan 1, 2019AND EMAIL_DOMAIN_PART is not equal to one of the following (aol.com | netscape.com | netscape.net | cs.com | aim.com | wmconnect.com | verizon.net)OR  (Email is equal to fundraisingemail-jajp+heIL@wikimedia.orgAND country is equal to IL))AND Segment is equal to 2', $firstMailing['list_criteria']);
  }

}
