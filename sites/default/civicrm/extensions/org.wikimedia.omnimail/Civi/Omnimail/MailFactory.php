<?php

namespace Civi\Omnimail;

use wmf_communication\MailerPHPMailer;
use wmf_communication\TestMailer;

/**
 * Class Mailer
 *
 * @package Civi\Omnimail
 */
class MailFactory {

  /**
   * We only need one instance of this object.
   *
   * So we use the singleton pattern and cache the instance in this variable
   *
   * @var self
   */
  static private $singleton;

  /**
   * Singleton function used to manage this object.
   *
   * @return self
   */
  public static function singleton(): self {
    if (self::$singleton === NULL) {
      self::$singleton = new self();
    }
    return self::$singleton;
  }

  protected $activeMailer;

  /**
   * Set the active mailer.
   *
   * @param string $name
   *
   * @throws \CRM_Core_Exception
   */
  public function setActiveMailer(string $name): void {
    switch ($name) {
      case 'phpmailer':
        $this->activeMailer = new MailerPHPMailer();
        break;

      case 'test':
        $this->activeMailer =  new TestMailer();
        break;

      default:
        throw new \CRM_Core_Exception("Unknown mailer requested: " . $name);
    }
  }

  /**
   * Get the Mailer class.
   */
  public function getMailer() {
    if (!$this->activeMailer) {
      $this->setActiveMailer('phpmailer');
    }
    return $this->activeMailer;
  }

}
