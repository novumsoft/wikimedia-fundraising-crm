<?php
use CRM_Dedupetools_ExtensionUtil as E;

class CRM_Dedupetools_BAO_ContactNamePair extends CRM_Dedupetools_DAO_ContactNamePair {

  /**
   * Create a new ContactNamePair based on array-data
   *
   * @param array $params key-value pairs
   * @return CRM_Dedupetools_DAO_ContactNamePair|NULL
   *
  public static function create($params) {
    $className = 'CRM_Dedupetools_DAO_ContactNamePair';
    $entityName = 'ContactNamePair';
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, CRM_Utils_Array::value('id', $params), $params);
    $instance = new $className();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);

    return $instance;
  } */

}
