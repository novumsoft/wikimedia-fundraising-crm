<?php
/**
 * Created by IntelliJ IDEA.
 * User: emcnaughton
 * Date: 5/31/18
 * Time: 10:07 AM
 */

use CRM_Forgetme_ExtensionUtil as E;
/**
 * Class CRM_Forgetme_Showme
 *
 * Show me class is intended to get as much data as is relevant about an entity
 * in a key-value format where the keys align to the entitiy metadata and the values
 * are formatted for display.
 *
 * This differs from other displays in Civi (e.g forms) in that forms tend to be based on opt-in
 * rather than opt-out (ie. we decide what to show). Here we decide only what not to shoe.
 */
class CRM_Forgetme_LoggingShowme extends CRM_Forgetme_Showme {

  /**
   * Get all the values for the entity.
   *
   * Reformat to be a row per line in the logging table.
   *
   * @return array
   */
  protected function getAllValuesForLogging() {

    $tables = CRM_Forgetme_Metadata::getEntitiesMetadata();
    $customTables = CRM_Forgetme_Metadata::getContactExtendingCustomTables();
    $displayValues = [];
    foreach (array_merge($tables, $customTables) as $tableName => $detail) {
      if (in_array('showme', $detail)) {
        $values = $this->getValues($tableName, $detail);
        foreach ($values as $value) {
          $entity = CRM_Forgetme_Metadata::getEntityName($tableName);
          $key = $tableName . '_' . $value['id'] . '_' . $value['log_conn_id'];
          $displayValues[$entity][$key] = $value;
          $displayValues[$entity][$key]['table'] = $tableName;
        }
      }
    }
    return $displayValues;
  }

  /**
   * Get the values for the entity that are suitable for display.
   */
  public function getDisplayValues() {
    if (empty($this->displayValues)) {
      $valuesForAllEntities = $this->getAllValuesForLogging();
      $displayValues = [];
      foreach ($valuesForAllEntities as $entity => $values) {
        $this->entity = $entity;
        $this->setEntityBasedMetadataDefinitions($entity);
        $this->setInternalFields(array_merge($this->getInternalFields(), ['log_action']));
        $this->displayValues = $values;
        $this->preFormatDisplayValues();
        $displayValues = array_merge($displayValues, $this->displayValues);
      }
      $this->displayValues = $displayValues;
    }
    return $this->displayValues;
  }

  /**
   * @param $filters
   */
  protected function setFilters($filters) {
    $acceptableFields = ['contact_id' => TRUE];
    $this->filters = array_intersect_key($filters, $acceptableFields);
  }

  /**
   * Get values for table.
   *
   * @param string $tableName
   * @param array $detail
   *
   * @return array
   *
   * @throws \CRM_Core_Exception
   */
  protected function getValues($tableName, $detail) {
    $getParams = $this->filters;
    $params = [1 => [(int) $getParams['contact_id'], 'Integer']];
    if (!empty($detail['is_custom'])) {
      return CRM_Core_DAO::executeQuery("SELECT * FROM log_{$tableName} WHERE entity_id = %1", $params)->fetchAll();
    }
    $daoName = CRM_Core_DAO_AllCoreTables::getClassForTable($tableName);
    $dao = new $daoName();
    $tableName = 'log_' . $tableName;
    if (property_exists($dao, 'contact_id')) {
      return CRM_Core_DAO::executeQuery("SELECT * FROM $tableName WHERE contact_id = %1", $params)->fetchAll();
    }
    elseif (property_exists($dao, 'entity_table')) {
      return  CRM_Core_DAO::executeQuery("SELECT * FROM $tableName WHERE entity_table = 'civicrm_contact' AND entity_id = %1", $params)->fetchAll();
    }
    elseif ($tableName === 'log_civicrm_contact') {
      return CRM_Core_DAO::executeQuery("SELECT * FROM $tableName WHERE id = %1", $params)->fetchAll();
    }
    elseif (isset($detail['keys'])) {
      $clauses = [];
      foreach ($detail['keys'] as $fieldName) {
        $clauses[] = "$fieldName = %1";
      }
      return CRM_Core_DAO::executeQuery("SELECT * FROM $tableName WHERE " . implode(' OR ', $clauses), $params)->fetchAll();
    }
    else {
      throw new CRM_Core_Exception(ts('unhandled table'));
    }
  }

}
