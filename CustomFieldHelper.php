<?php

/**
* Helper for working with Custom fields.
*
* @version 1.0
*/
class CustomFieldHelper {
  /**
  * Custom group title name. Table name and column name are queried based on this value.
  *
  * @var string
  */
  protected $_customGroupTitle;
  
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
  * @param string $customGroupTitle Title name of Custon group for what this helper is used for.
  */
  public function __construct($customGroupTitle) {
    $this->_customGroupTitle = $customGroupTitle;
    $this->initCustomFieldInfo();
  }
  
  /**
  * Finds Custom field table and column names based on Column group title.
  */
  protected function initCustomFieldInfo() {
    $result = $this->getCustomGroupIdAndTableForTitle($this->_customGroupTitle);
    $this->_tableName = $result["table_name"];
    $this->_columnName = $this->getCustomFieldColumnForGroupId($result["id"]);
  }
  
  /**
  * Finds Custom group id and table name for title name.
  * Throws CiviCRM fatal error if Custom group cannot be found with given title.
  *
  * @param string $title Custom group title
  * @return array Array with 'id' and 'table_name' keys
  */
  public function getCustomGroupIdAndTableForTitle($title='') {
    $sql = "
      SELECT id, table_name
      FROM civicrm_custom_group
      WHERE title = %1
    ";
    
    $params = array(1  => array($title, 'String'));
    $dao = CRM_Core_DAO::executeQuery($sql, $params);
    
    if($dao->fetch()) {
      $result = array();
      $result["id"] = $dao->id;
      $result["table_name"] = $dao->table_name;
      
      return $result;
    }
    else {
      CRM_Core_Error::fatal(ts('No custom group exists for name: '. $title));
    }
  }
  
  /**
  * Finds Custom field column name for group id.
  * Throws CiviCRM fatal error if Custom field cannot be found with given group id.
  *
  * @param string|int $groupId Custom group id
  * @return string Custom field column name
  */
  public function getCustomFieldColumnForGroupId($groupId) {
    $groupId = (int) $groupId;
  
    $sql = "
      SELECT column_name
      FROM civicrm_custom_field
      WHERE custom_group_id = $groupId
    ";
    
    $column_name = CRM_Core_DAO::singleValueQuery($sql);
    
    if(isset($column_name)) {
      return $column_name;
    }
    else {
      CRM_Core_Error::fatal(ts('No custom field exists for group id: '. $groupId));
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
}