<?php

use Omnimail\Silverpop\Credentials;

/**
 * Created by IntelliJ IDEA.
 * User: emcnaughton@wikimedia.org
 * Date: 7/4/17
 * Time: 1:50 PM
 */
class CRM_Omnimail_Helper {

  /**
   * Array of CiviCRM settings for easy access.
   *
   * @var array
   */
  protected static $settings;
  /**
   * Get credentials
   *
   * @param $params
   * @return array
   */
  public static function getCredentials($params) {
    $credentialKeys = ['username', 'password', 'client_id', 'client_secret', 'refresh_token'];
    if ((!isset($params['username']) || !isset($params['password']))
      // The latter 3 are required for Rest.
      && (!isset($params['client_id']) || !isset($params['client_secret']) || !isset($params['refresh_token']) )
    ) {
      $credentials = self::getSetting('omnimail_credentials');
      $credentials = CRM_Utils_Array::value($params['mail_provider'], $credentials);
    }
    else {
      foreach ($credentialKeys as $credentialKey) {
        if (!empty($params[$credentialKey])) {
          $credentials[$credentialKey] = $params[$credentialKey];
        }
      }
    }
    $mailerCredentials = array(
      'credentials' => new Credentials($credentials)
    );
    if (!empty($params['client'])) {
      $mailerCredentials['client'] = $params['client'];
    }
    return $mailerCredentials;
  }

  /**
   * Get settings.
   *
   * This is just a helper for convenience.
   *
   * We are not caching as there is caching in the api & we use this little
   * enough it's not worth extra caching.
   *
   * @return array
   */
  public static function getSettings() {
    $settings = civicrm_api3('Setting', 'get', array('group' => 'omnimail'));
    self::$settings= reset($settings['values']);
    return self::$settings;
  }

  /**
   * Get named setting.
   *
   * This is just a helper for convenience.
   *
   * @param string $name
   * @return mixed
   */
  public static function getSetting($name) {
    $settings = self::getSettings();
    return CRM_Utils_Array::value($name, $settings);
  }

}
