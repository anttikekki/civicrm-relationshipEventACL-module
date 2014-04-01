<?php

require_once "RelationshipEventACLAdminDAO.php";

/**
* Ajax request listener for RelationshipEventACL Admin page Ajax calls.
* This listener methods intercept URLs in form civicrm/relationshipEventACL/settings/ajax/*. This is configured in menu.xml.
* All methods print JSON-response and terminates CiviCRM.
*/
class Admin_Page_RelationshipEventACLAdminAjax {
  
  /**
  * Returns all rows from civicrm_relationshipeventacl_config table.
  * Listens URL civicrm/relationshipEventACL/settings/ajax/getConfig.
  */
  public static function getConfig() {
    echo json_encode(RelationshipEventACLAdminDAO::getAllConfigRows());
    CRM_Utils_System::civiExit();
  }
  
  /**
  * Saves (creates or updates) configuration row in civicrm_relationshipeventacl_config table.
  * Prints "ok" if save was succesfull. All other responses are error messages.
  * Listens URL civicrm/relationshipEventACL/settings/ajax/saveConfigRow.
  *
  * Saved parameters are queried from $_GET.
  */
  public static function saveConfigRow() {
    echo RelationshipEventACLAdminDAO::saveConfigRow($_GET);
    CRM_Utils_System::civiExit();
  }
  
  /**
  * Deletes configuration row from civicrm_relationshipeventacl_config table.
  * Prints "ok" if delete was succesfull.
  * Listens URL civicrm/relationshipEventACL/settings/ajax/deleteConfigRow.
  *
  * Delete parameters are queried from $_GET.
  */
  public static function deleteConfigRow() {
    RelationshipEventACLAdminDAO::deleteConfigRow($_GET);
    
    echo "ok";
    CRM_Utils_System::civiExit();
  }
}