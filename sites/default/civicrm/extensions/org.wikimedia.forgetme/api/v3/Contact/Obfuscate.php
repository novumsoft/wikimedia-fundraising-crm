<?php
use CRM_Forgetme_ExtensionUtil as E;

/**
 * Contact.Showme API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_contact_obfuscate_spec(&$spec) {
  $spec['id']['api.required'] = 1;
}

/**
 * Contact.obfuscate API
 *
 * The point of this api is to get all data about a contact with some prefiltering
 * and formatting.
 *
 * SCARY NOTE - if we use 'forget' as the function name the crmApi function finds the
 * string 'get' in it & uses HTTP GET rather than POST.
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_contact_obfuscate($params) {
  $result = [];
  $entitiesToDelete = ['phone', 'email', 'website', 'im'];
  foreach ($entitiesToDelete as $entityToDelete) {
    $delete = civicrm_api3($entityToDelete, 'showme', ['contact_id' => $params['id'], "api.{$entityToDelete}.delete" => 1]);
    if ($delete['count']) {
      foreach ($delete['showme'] as $id => $string) {
        $result[$entityToDelete . $id] = $string;
      }
    }
  }
  $loggedInUser = CRM_Core_Session::getLoggedInContactID();

  civicrm_api3('Activity', 'create', [
    'activity_type_id' => 'forget_me',
    'subject' => (empty($params['reference'])) ? ts('Privacy request') : $params['reference'] . ' ' . ts('Privacy Request'),
    'target_contact_id' => $params['id'],
    'source_contact_id' => ($loggedInUser ? : $params['id']),
  ]);
  return civicrm_api3_create_success($result, $params);
}
