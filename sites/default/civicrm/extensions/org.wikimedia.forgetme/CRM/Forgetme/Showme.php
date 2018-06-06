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
class CRM_Forgetme_Showme {

  /**
   * @var string
   */

  protected $entity;
  /**
   * @var array
   */
  protected $filters;

  /**
   * @var array
   */
  protected $metadata;

  /**
   * @return array
   */
  public function getMetadata() {
    return $this->metadata;
  }

  /**
   * @param array $metadata
   */
  public function setMetadata($metadata) {
    $this->metadata = $metadata;
  }

  protected $displaySeparator = '|';

  /**
   * @return string
   */
  public function getDisplaySeparator() {
    return $this->displaySeparator;
  }

  /**
   * @param string $displaySeparator
   */
  public function setDisplaySeparator($displaySeparator) {
    $this->displaySeparator = $displaySeparator;
  }

  /**
   * Negative fields are fields that are not of interest if they are not true.
   *
   * Ie. is_deceased, do_not_email etc are implicitly uninteresting unless true.
   *
   * @var array
   */
  protected $negativeFields = [];

  /**
   * Fields that are for the system and are not useful to the user.
   *
   * @var array
   */
  protected $internalFields = [];

  /**
   * @return array
   */
  public function getInternalFields() {
    return $this->internalFields;
  }

  /**
   * @param array $internalFields
   */
  public function setInternalFields($internalFields) {
    $this->internalFields = $internalFields;
  }

  /**
   * Display values.
   *
   * @var array
   */
  protected $displayValues = [];

  /**
   * @return array
   */
  public function getNegativeFields() {
    return $this->negativeFields;
  }

  /**
   * @param array $negativeFields
   */
  public function setNegativeFields($negativeFields) {
    $this->negativeFields = $negativeFields;
  }

  /**
   * Showme constructor.
   *
   * @param string $entity
   * @param array $filters
   */
  public function __construct($entity, $filters) {
    $this->entity = $entity;
    $this->metadata = civicrm_api3($entity, 'getfields', ['action' => 'get'])['values'];
    $acceptableFields = array_merge($this->metadata, ['debug', 'sequential', 'check_permissions']);
    $this->filters = array_intersect_key($filters, $acceptableFields);
  }

  /**
   * Get all the values for the entity.
   *
   * @return array
   */
  protected function getAllValuesForEntity() {
    $getParams['return'] = array_keys($this->metadata);
    return civicrm_api3($this->entity, 'get', $this->filters)['values'];
  }

  /**
   * Get the values for the entity that are suitable for display.
   */
  public function getDisplayValues() {
    if (empty($this->displayValues)) {
      $this->displayValues = $this->getAllValuesForEntity();
      $this->filterOutEmptyFields();
      $this->filterOutDuplicateEntityID();
      $this->filterOutNegativeValues();
      $this->processOptionValueFields();
      $this->filterOutInternalFields();
    }
    return $this->displayValues;
  }

  /**
   * Get the displayable data as a string.
   *
   * @return array
   */
  public function getDisplayTiles() {
    $display = $return = [];
    foreach ($this->getDisplayValues() as $index => $entities) {
      foreach ($entities as $key => $value) {
        $display[] = $this->metadata[$key]['title'] . ':' . $value;
      }
      $return[$index] = implode($this->displaySeparator, $display);
    }
    return $return;
  }

  /**
   * Filter out fields with no data.
   */
  protected function filterOutEmptyFields() {
    foreach ($this->displayValues as $index => $displayValue) {
      foreach ($displayValue as $field => $value)
      if ($value === '') {
        unset($this->displayValues[$index][$field]);
      }
    }
  }

  /**
   * Filter out {$entity}_id as it duplicates if field.
   */
  protected function filterOutDuplicateEntityID() {
    foreach (array_keys($this->displayValues) as $displayValue) {
      unset($this->displayValues[$displayValue][strtolower($this->entity) . '_id']);
    }
  }

  /**
   * Filter out negative values when they are false.
   *
   * Transform to yes when true.
   */
  protected function filterOutNegativeValues() {
    foreach (array_keys($this->displayValues) as $displayValue) {
      foreach ($this->getNegativeFields() as $negativeField) {
        if (isset($this->displayValues[$displayValue][$negativeField])) {
          if ($this->displayValues[$displayValue][$negativeField]) {
            $this->displayValues[$displayValue][$negativeField] = E::ts('Yes');
          }
          else {
            unset($this->displayValues[$displayValue][$negativeField]);
          }
        }
      }
    }
  }

  /**
   * Filter out fields that are system fields not useful to users.
   */
  protected function filterOutInternalFields() {
    foreach (array_keys($this->displayValues) as $displayValue) {
      foreach ($this->getInternalFields() as $internalField) {
        if (isset($this->displayValues[$displayValue][$internalField])) {
          unset($this->displayValues[$displayValue][$internalField]);
        }
      }
    }
  }

  /**
   * Consolidate and filter fields based on option values.
   *
   * We are likely to get 2 fields returned eg.
   *   gender_id=1
   *   gender=Female
   *
   * We consolidate this to gender_id=Female (gender_id is the real
   * field & has the metadata.
   */
  protected function processOptionValueFields() {
    foreach ($this->displayValues as $displayIndex => $displayValue) {
      foreach ($displayValue as $index => $field) {
        if (!isset($this->metadata[$index]['pseudoconstant']['optionGroupName'])) {
          continue;
        }
        $secondaryFieldName = $this->metadata[$index]['pseudoconstant']['optionGroupName'];

        if (isset($displayValue[$secondaryFieldName])) {
          $this->displayValues[$displayIndex][$index] = $displayValue[$secondaryFieldName];
        }
        unset($this->displayValues[$displayIndex][$secondaryFieldName]);
      }
    }
  }

}
