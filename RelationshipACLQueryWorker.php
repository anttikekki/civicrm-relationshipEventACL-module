<?php

class RelationshipACLQueryWorker {
  /**
   * Create temporary table of all permissioned contacts. Searches the whole 
   * relationships tree structure.
   * 
   * @param int $contactID Contact id of user of whom relationships we search
   */
  public function createContactsTableWithEditPermissions($contactID) {
    $resultTableName = 'relationship_event_acl_result' . rand(10000, 100000);
    $workTableName = 'relationship_event_acl_worktemp' . rand(10000, 100000);
    
    //Create temporary tables
    $this->createTempTable($resultTableName);
    $this->createTempTable($workTableName);
    
    //Add contacts that given contctID is related to and can edit
    $this->addRelatedContactsForContactId($resultTableName, $contactID);
    
    $oldRowCount = 0;
    $newRowCount = 0;
    $continueSearch = true;
    
    //Start search down to the tree of contact in relationship
    while($continueSearch) {
      //Get row count before adding next step of contacts
      $oldRowCount = $this->getRowCount($resultTableName);
      
      //Add next step of contacts in relationship tree
      $this->addNextStepOfRelatedContacts($resultTableName, $workTableName);
      
      //Get new row count. If new rows is added then continue search down the relationship tree
      $newRowCount = $this->getRowCount($resultTableName);
      $continueSearch = $oldRowCount < $newRowCount;
    }

    return $resultTableName;
  }

  /**
  * Adds all contact ids that given contact has direct relationship to and right to edit.
  *
  * @param string $resultTableName Table where found contact ids are added
  * @param int $contactID Contact id of user of whom relationships we search
  */
  private function addRelatedContactsForContactId($resultTableName, $contactID) {
    $now = date('Y-m-d');

    //B to A
    $sql = "INSERT INTO $resultTableName
      SELECT contact_id_a FROM civicrm_relationship
      WHERE contact_id_b = $contactID
      AND is_active = 1
      AND (start_date IS NULL OR start_date <= '{$now}' )
      AND (end_date IS NULL OR end_date >= '{$now}')
      AND is_permission_b_a = 1
    ";
    CRM_Core_DAO::executeQuery($sql);

    //A to B
    $sql = "REPLACE INTO $resultTableName
      SELECT contact_id_b FROM civicrm_relationship
      WHERE contact_id_a = $contactID
      AND is_active = 1
      AND (start_date IS NULL OR start_date <= '{$now}' )
      AND (end_date IS NULL OR end_date >= '{$now}')
      AND is_permission_a_b = 1
    ";
    CRM_Core_DAO::executeQuery($sql);
  }


  /**
  * Adds next level of contact ids in realionship to result table.
  *
  * @param string $resultTableName Table where found contact ids are added
  * @param string $workTableName Table where temporary result is added
  */
  private function addNextStepOfRelatedContacts($resultTableName, $workTableName) {
    $now = date('Y-m-d');

    //Empty working temporary table
    $this->truncateTable($workTableName);

    //A to B
    $sql = "INSERT INTO $workTableName
      SELECT contact_id_b
      FROM $resultTableName tmp
      LEFT JOIN civicrm_relationship r  ON tmp.contact_id = r.contact_id_a
      INNER JOIN civicrm_contact c ON c.id = r.contact_id_a 
      WHERE
      r.is_active = 1
      AND (start_date IS NULL OR start_date <= '{$now}' )
      AND (end_date IS NULL OR end_date >= '{$now}')
      AND is_permission_a_b = 1
    ";
    CRM_Core_DAO::executeQuery($sql);

    //B to A
    $sql = "REPLACE INTO $workTableName
      SELECT contact_id_a
      FROM $resultTableName tmp
      LEFT JOIN civicrm_relationship r ON tmp.contact_id = r.contact_id_b
      INNER JOIN civicrm_contact c ON c.id = r.contact_id_b 
      WHERE
      r.is_active = 1
      AND (start_date IS NULL OR start_date <= '{$now}' )
      AND (end_date IS NULL OR end_date >= '{$now}')
      AND is_permission_b_a = 1
    ";
    CRM_Core_DAO::executeQuery($sql);

    //Add to result table
    $sql = "REPLACE INTO $resultTableName
      SELECT * FROM $workTableName";
    CRM_Core_DAO::executeQuery($sql);
  }


  /**
  * Creates temporary table
  *
  * @param string $tableName Table that is created
  */
  private function createTempTable($tableName) {
    $sql = "CREATE TEMPORARY TABLE $tableName
    (
     `contact_id` INT(10) NULL DEFAULT NULL,
     PRIMARY KEY (`contact_id`)
    )";
    CRM_Core_DAO::executeQuery($sql);
  }


  /**
  * Truncates table
  *
  * @param string $tableName Table that is truncated
  */
  private function truncateTable($tableName) {
    $sql = "TRUNCATE TABLE $tableName";
    CRM_Core_DAO::executeQuery($sql);
  }


  /**
  * Get row count from table
  *
  * @param string $tableName Table where row count is queried
  * @return int table row count
  */
  function getRowCount($tableName) {
    $sql = "SELECT COUNT(*)  
      FROM $tableName";
    return (int) CRM_Core_DAO::singleValueQuery($sql);
  }
}