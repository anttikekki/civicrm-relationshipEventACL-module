<?php

/**
* Helper for working with Custom fields.
*
* @version 1.1
*/
class CustomFieldHelper {

  /**
  * Version of this worker
  */
  const VERSION = "1.1";
  
  /**
  * Custom field id. Table name and column name are queried based on this value.
  *
  * @var string
  */
  protected $_customFieldId;
  
  /**
  * Custom field table name.
  *
  * @var string
  */
  protected $_tableName;
  
  /**
  * Custom field value column name in custom field table
  *
  * @var string
  */
  protected $_columnName;

  /**
  * Creates new worker.
  *
  * @param int $customFieldId Id of Custon field for what this helper is used for.
  */
  public function __construct($customFieldId) {
    $this->_customFieldId = $customFieldId;
    $this->initCustomFieldInfo();
  }
  
  /**
  * Finds Custom field table and column names based on Custom field id.
  */
  protected function initCustomFieldInfo() {
    $this->_tableName = $this->getCustomGroupTableForCustomGroupId($this->_customFieldId);
    $this->_columnName = $this->getCustomFieldColumnForId($this->_customFieldId);
  }
  
  /**
  * Finds Custom group table name for Custom Field Id.
  * Throws CiviCRM fatal error if Custom group cannot be found with given Custom field id.
  *
  * @param string|int $customFieldId Custom field id
  * @return string Custom field value table name
  */
  public function getCustomGroupTableForCustomGroupId($customFieldId) {
    $customFieldId = (int) $customFieldId;
    
    $sql = "
      SELECT civicrm_custom_group.table_name
      FROM civicrm_custom_field
      LEFT JOIN civicrm_custom_group ON (civicrm_custom_group.id = civicrm_custom_field.custom_group_id)
      WHERE civicrm_custom_field.id = $customFieldId
    ";
    
    $table_name = CRM_Core_DAO::singleValueQuery($sql);
    
    if(isset($table_name)) {
      return $table_name;
    }
    else {
      CRM_Core_Error::fatal(ts('No Custom Group exists for Custom Field id: '. $customFieldId));
    }
  }
  
  /**
  * Finds Custom field column name for group id.
  * Throws CiviCRM fatal error if Custom field cannot be found with given id.
  *
  * @param string|int $customFieldId Custom field id
  * @return string Custom field column name
  */
  public function getCustomFieldColumnForId($customFieldId) {
    $customFieldId = (int) $customFieldId;
  
    $sql = "
      SELECT column_name
      FROM civicrm_custom_field
      WHERE id = $customFieldId
    ";
    
    $column_name = CRM_Core_DAO::singleValueQuery($sql);
    
    if(isset($column_name)) {
      return $column_name;
    }
    else {
      CRM_Core_Error::fatal(ts('No custom field exists for id: '. $customFieldId));
    }
  }
  
  /**
  * Insert or updates Custom field value.
  * Update is done if row already exists. Does nothing if $value is not number larger than zero.
  *
  * @param string|int $entityId Id of host entity (contribution, event...)
  * @param string|int $value Custom field value
  */
  public function insertOrUpdateValue($entityId, $value) {
    $oldValue = self::loadValue($entityId);
    
    if($oldValue == 0) {
      self::insertValue($entityId, $value);
    }
    else {
      self::updateValue($entityId, $value);
    }
  }
  
  /**
  * Insert Custom field value.
  * Does nothing if $value is not number larger than zero.
  *
  * @param string|int $entityId Id of host entity (contribution, event...)
  * @param string|int $value Custom field value
  */
  public function insertValue($entityId, $value) {
    $entityId = (int) $entityId;
    $value = (int) $value;
    
    //No value, do not insert row
    if($value == 0) {
      return;
    }
  
    $sql = "
      INSERT INTO ".$this->_tableName." (entity_id, ".$this->_columnName.")
      VALUES ($entityId, $value)
    ";
 
    CRM_Core_DAO::executeQuery($sql);
  }
  
  /**
  * Updates Custom field value.
  * Does nothing if $value is not number larger than zero.
  *
  * @param string|int $entityId Id of host entity (contribution, event...)
  * @param string|int $value Custom field value
  */
  public function updateValue($entityId, $value) {
    $entityId = (int) $entityId;
    $value = (int) $value;
    
    //No value, do not update row
    if($value == 0) {
      return;
    }
  
    $sql = "
      UPDATE ".$this->_tableName."
      SET ".$this->_columnName." = $value
      WHERE entity_id = $entityId
    ";
 
    CRM_Core_DAO::executeQuery($sql);
  }
  
  /**
  * Loads single value for Custom field for host entity id.
  *
  * @param string|int $entityId Id of host entity (contribution, event...)
  * @return int Custom field value. Null if $entityId is not number larger than zero
  */
  public function loadValue($entityId) {
    $entityId = (int) $entityId;
    
    if($entityId == 0) {
      return null;
    }
    
    $sql = "
      SELECT ".$this->_columnName."  
      FROM ".$this->_tableName."
      WHERE entity_id = $entityId
    ";
    
    return (int) CRM_Core_DAO::singleValueQuery($sql);
  }
  
  /**
  * Loads all values for Custom field.
  *
  * @return array Custom field values in associative array where key is entity_id and value is custom field value.
  */
  public function loadAllValues() {    
    $sql = "
      SELECT entity_id, ".$this->_columnName."  
      FROM ".$this->_tableName."
    ";
    
    $dao = CRM_Core_DAO::executeQuery($sql);
    
    $result = array();
    while ($dao->fetch()) {
      $result[$dao->entity_id] = $dao->{$this->_columnName};
    }
    
    return $result;
  }
  
  /**
  * Checks that this CustomFieldHelper version number is same as parameter version number. 
  * Throws CiviCRM fatal exception id version is wrong.
  *
  * @param string $version Version nunmber as String 
  */
  public static function checkVersion($version) {
    if(CustomFieldHelper::VERSION !== $version) {
      CRM_Core_Error::fatal("CustomFieldHelper is version ". CustomFieldHelper::VERSION ." and not version ". $version);
    }
  }
}