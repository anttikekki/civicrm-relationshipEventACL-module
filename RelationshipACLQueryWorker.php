<?php

/**
* Worker for creating temporary table with information of what contacts the given contact id 
* has right to edit. Editing rights are determined from contact relation edit right info.
*
* This worker traverses the whole contact relation tree from given contact id.
*
* @version 1.2
*/
class RelationshipACLQueryWorker {

  /**
  * Version of this worker
  */
  const VERSION = "1.2";

  /**
  * Singleton instace of this worker
  *
  * @var RelationshipACLQueryWorker
  */
  private static $instance = null;
  
  /**
  * This worker result table. NULL if worjer is not executed.
  *
  * @var string
  */
  private $resultTableName = NULL;
  
  /**
  * Hide public constructor to force singleton pattern.
  */
  protected function __construct() {}

  /**
  * Loads all contact IDs where given contact has edit rights trough relationships.
  *
  * @param int|string $contactID Contact Id that edit rights are queried.
  * @return int[] Contact IDs.
  */
  public function getContactIDsWithEditPermissions($contactID) {
    if(!isset($this->resultTableName)) {
      $this->createContactsTableWithEditPermissions($contactID);
    }
    
    $sql = "SELECT contact_id FROM " . $this->resultTableName;
    $dao = CRM_Core_DAO::executeQuery($sql);
    
    $contactIDs = array();
    while ($dao->fetch()) {
      $contactIDs[] = $dao->contact_id;
    }
    
    return $contactIDs;
  }

  /**
   * Create temporary table of all permissioned contacts. Searches the whole 
   * relationships tree structure.
   * 
   * @param int $contactID Contact id of user of whom relationships we search
   * @return string Result table name. Table contains contact_id field.
   */
  public function createContactsTableWithEditPermissions($contactID) {
    if(isset($this->resultTableName)) {
      return $this->resultTableName;
    }
  
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

    $this->resultTableName = $resultTableName;
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
      SELECT DISTINCT contact_id_a 
      FROM civicrm_relationship
      WHERE contact_id_b = $contactID
      AND is_active = 1
      AND (start_date IS NULL OR start_date <= '{$now}' )
      AND (end_date IS NULL OR end_date >= '{$now}')
      AND is_permission_b_a = 1
    ";
    CRM_Core_DAO::executeQuery($sql);

    //A to B
    $sql = "REPLACE INTO $resultTableName
      SELECT DISTINCT contact_id_b 
      FROM civicrm_relationship
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
      SELECT DISTINCT contact_id_b
      FROM $resultTableName tmp
      LEFT JOIN civicrm_relationship r  ON tmp.contact_id = r.contact_id_a
      INNER JOIN civicrm_contact c ON c.id = r.contact_id_a 
      WHERE r.is_active = 1
      AND (start_date IS NULL OR start_date <= '{$now}' )
      AND (end_date IS NULL OR end_date >= '{$now}')
      AND is_permission_a_b = 1
    ";
    CRM_Core_DAO::executeQuery($sql);

    //B to A
    $sql = "REPLACE INTO $workTableName
      SELECT DISTINCT contact_id_a
      FROM $resultTableName tmp
      LEFT JOIN civicrm_relationship r ON tmp.contact_id = r.contact_id_b
      INNER JOIN civicrm_contact c ON c.id = r.contact_id_b 
      WHERE r.is_active = 1
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
  private function getRowCount($tableName) {
    $sql = "SELECT COUNT(*)  
      FROM $tableName";
    return (int) CRM_Core_DAO::singleValueQuery($sql);
  }
  
  
  
  /**
  * Call this method to get singleton RelationshipACLQueryWorker
  *
  * @return RelationshipACLQueryWorker
  */
  public static function getInstance() {
    if (!isset(static::$instance)) {
      static::$instance = new RelationshipACLQueryWorker();
    }
    return static::$instance;
  }
  
  /**
  * Checks that this RelationshipACLQueryWorker version number is same as parameter version number. 
  * Throws CiviCRM fatal exception id version is wrong.
  *
  * @param string $version Version nunmber as String 
  */
  public static function checkVersion($version) {
    if(RelationshipACLQueryWorker::VERSION !== $version) {
      CRM_Core_Error::fatal("RelationshipACLQueryWorker is version ". RelationshipACLQueryWorker::VERSION ." and not version ". $version);
    }
  }
}