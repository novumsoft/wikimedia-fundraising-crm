<?php

/**
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2020
 *
 * Generated from /Users/eileenmcnaughton/buildkit/build/dmaster/sites/default/files/civicrm/ext/org.wikimedia.dedupetools/xml/schema/CRM/Dedupetools/ContactNamePair.xml
 * DO NOT EDIT.  Generated by CRM_Core_CodeGen
 * (GenCodeChecksum:aa4e6ef87d5f261059aa7dcee5d1bf73)
 */

/**
 * Database access object for the ContactNamePair entity.
 */
class CRM_Dedupetools_DAO_ContactNamePair extends CRM_Core_DAO {

  /**
   * Static instance to hold the table name.
   *
   * @var string
   */
  public static $_tableName = 'civicrm_contact_name_pair';

  /**
   * Should CiviCRM log any modifications to this table in the civicrm_log table.
   *
   * @var bool
   */
  public static $_log = TRUE;

  /**
   * Unique ContactNamePair ID
   *
   * @var int
   */
  public $id;

  /**
   * First name (this is the master, if that matters)
   *
   * @var string
   */
  public $name_a;

  /**
   * Second name (if one name is a nickname or a mis-spelling it will be this one)
   *
   * @var string
   */
  public $name_b;

  /**
   * @var bool
   */
  public $is_name_b_nickname;

  /**
   * @var bool
   */
  public $is_name_b_inferior;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->__table = 'civicrm_contact_name_pair';
    parent::__construct();
  }

  /**
   * Returns all the column names of this table
   *
   * @return array
   */
  public static function &fields() {
    if (!isset(Civi::$statics[__CLASS__]['fields'])) {
      Civi::$statics[__CLASS__]['fields'] = [
        'id' => [
          'name' => 'id',
          'type' => CRM_Utils_Type::T_INT,
          'description' => CRM_Deduper_ExtensionUtil::ts('Unique ContactNamePair ID'),
          'required' => TRUE,
          'where' => 'civicrm_contact_name_pair.id',
          'table_name' => 'civicrm_contact_name_pair',
          'entity' => 'ContactNamePair',
          'bao' => 'CRM_Dedupetools_DAO_ContactNamePair',
          'localizable' => 0,
        ],
        'name_a' => [
          'name' => 'name_a',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => CRM_Deduper_ExtensionUtil::ts('Name A'),
          'description' => CRM_Deduper_ExtensionUtil::ts('First name (this is the master, if that matters)'),
          'maxlength' => 64,
          'size' => 30,
          'where' => 'civicrm_contact_name_pair.name_a',
          'table_name' => 'civicrm_contact_name_pair',
          'entity' => 'ContactNamePair',
          'bao' => 'CRM_Dedupetools_DAO_ContactNamePair',
          'localizable' => 0,
          'html' => [
            'type' => 'Text',
          ],
        ],
        'name_b' => [
          'name' => 'name_b',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => CRM_Deduper_ExtensionUtil::ts('Name B'),
          'description' => CRM_Deduper_ExtensionUtil::ts('Second name (if one name is a nickname or a mis-spelling it will be this one)'),
          'maxlength' => 64,
          'size' => 30,
          'where' => 'civicrm_contact_name_pair.name_b',
          'table_name' => 'civicrm_contact_name_pair',
          'entity' => 'ContactNamePair',
          'bao' => 'CRM_Dedupetools_DAO_ContactNamePair',
          'localizable' => 0,
          'html' => [
            'type' => 'Text',
          ],
        ],
        'is_name_b_nickname' => [
          'name' => 'is_name_b_nickname',
          'type' => CRM_Utils_Type::T_BOOLEAN,
          'title' => CRM_Deduper_ExtensionUtil::ts('Is Name B a Nickname of Name A?'),
          'where' => 'civicrm_contact_name_pair.is_name_b_nickname',
          'default' => '0',
          'table_name' => 'civicrm_contact_name_pair',
          'entity' => 'ContactNamePair',
          'bao' => 'CRM_Dedupetools_DAO_ContactNamePair',
          'localizable' => 0,
          'html' => [
            'type' => 'CheckBox',
          ],
        ],
        'is_name_b_inferior' => [
          'name' => 'is_name_b_inferior',
          'type' => CRM_Utils_Type::T_BOOLEAN,
          'title' => CRM_Deduper_ExtensionUtil::ts('Is Name B Inferior to Name A?'),
          'where' => 'civicrm_contact_name_pair.is_name_b_inferior',
          'default' => '0',
          'table_name' => 'civicrm_contact_name_pair',
          'entity' => 'ContactNamePair',
          'bao' => 'CRM_Dedupetools_DAO_ContactNamePair',
          'localizable' => 0,
          'html' => [
            'type' => 'CheckBox',
          ],
        ],
      ];
      CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'fields_callback', Civi::$statics[__CLASS__]['fields']);
    }
    return Civi::$statics[__CLASS__]['fields'];
  }

  /**
   * Return a mapping from field-name to the corresponding key (as used in fields()).
   *
   * @return array
   *   Array(string $name => string $uniqueName).
   */
  public static function &fieldKeys() {
    if (!isset(Civi::$statics[__CLASS__]['fieldKeys'])) {
      Civi::$statics[__CLASS__]['fieldKeys'] = array_flip(CRM_Utils_Array::collect('name', self::fields()));
    }
    return Civi::$statics[__CLASS__]['fieldKeys'];
  }

  /**
   * Returns the names of this table
   *
   * @return string
   */
  public static function getTableName() {
    return self::$_tableName;
  }

  /**
   * Returns if this table needs to be logged
   *
   * @return bool
   */
  public function getLog() {
    return self::$_log;
  }

  /**
   * Returns the list of fields that can be imported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &import($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getImports(__CLASS__, 'contact_name_pair', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of fields that can be exported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &export($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getExports(__CLASS__, 'contact_name_pair', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of indices
   *
   * @param bool $localize
   *
   * @return array
   */
  public static function indices($localize = TRUE) {
    $indices = [
      'name_a' => [
        'name' => 'name_a',
        'field' => [
          0 => 'name_a',
        ],
        'localizable' => FALSE,
        'sig' => 'civicrm_contact_name_pair::0::name_a',
      ],
      'name_b' => [
        'name' => 'name_b',
        'field' => [
          0 => 'name_b',
        ],
        'localizable' => FALSE,
        'sig' => 'civicrm_contact_name_pair::0::name_b',
      ],
      'is_name_b_nickname' => [
        'name' => 'is_name_b_nickname',
        'field' => [
          0 => 'is_name_b_nickname',
        ],
        'localizable' => FALSE,
        'sig' => 'civicrm_contact_name_pair::0::is_name_b_nickname',
      ],
      'is_name_b_inferior' => [
        'name' => 'is_name_b_inferior',
        'field' => [
          0 => 'is_name_b_inferior',
        ],
        'localizable' => FALSE,
        'sig' => 'civicrm_contact_name_pair::0::is_name_b_inferior',
      ],
    ];
    return ($localize && !empty($indices)) ? CRM_Core_DAO_AllCoreTables::multilingualize(__CLASS__, $indices) : $indices;
  }

}
