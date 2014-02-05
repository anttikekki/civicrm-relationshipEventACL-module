<?php

/**
* Only import worker if it is not already loaded. Multiple imports can happen
* because relationshipACL and relationshipEvenACL modules uses same worker. 
*/
if(class_exists('RelationshipACLQueryWorker') === false) {
  require_once "RelationshipACLQueryWorker.php";
}

/**
* Only import worker if it is not already loaded. Multiple imports can happen
* because relationshipACL and relationshipEvenACL modules uses same worker. 
*/
if(class_exists('CustomFieldHelper') === false) {
  require_once "CustomFieldHelper.php";
}


/**
 * Worker to solve Event visibility and edit rights for user from relationship edit rights.
 */
class RelationshipEventACLWorker {
  
  /**
  * Config key for civicrm_relationshipeventacl_config table. This key 
  * stores name of Event Custom field group that stores event owner contact id.
  *
  * @var string
  */
  protected $configKey_eventOwnerCustomGroupName = "eventOwnerCustomGroupName";
  
  /**
  * Executed when Participant search form is built.
  * Filters participant rows based on permissions. Disables pager.
  *
  * @param CRM_Event_Form_Search $form Search participants form
  */
  public function participantSearchAlterTemplateFileHook(&$form) {
    $this->filterParticipants($form);
    
    //JavaScript adds 'limit=0' to participants search form action URL. This removes paging.
    CRM_Core_Resources::singleton()->addScriptFile('com.github.anttikekki.relationshipEventACL', 'participantsSearch.js');
  }
  
  /**
  * Executed when Manage Events page is built.
  * Disables pager and filters Event rows based on permissions.
  *
  * @param CRM_Event_Page_ManageEvent $page Manage Events page
  */
  public function manageEventPageRunHook(&$page) {
    $this->checkPagerRowCount();
    $this->filterManagementEventRows($page);
  }
  
  /**
  * Executed when Event Dashboard is built.
  * Filters Event rows based on permissions.
  *
  * @param CRM_Event_Page_DashBoard $page Dashboard page
  */
  public function dashboardPageRunHook(&$page) {
    $this->filterDashBoardEventRows($page);
  }
  
  /**
  * Executed when Event form is built.
  * Checks if user has rights to edit this event.
  *
  * @param CRM_Event_Form_ManageEvent $form Event form
  */
  public function eventFormBuildFormHook(&$form) {
    $this->checkEventEditPermission($form);
  }
  
  /**
  * Check if current logged in user has rights to edit selected event. Show fatal error if no permission.
  *
  * @param CRM_Core_Page|CRM_Core_Form $page CiviCRM Page or Form object
  */
  private function checkEventEditPermission(&$page) {
    $eventID = $page->_id;
    
    $rows = array();
    $rows[$eventID] = array();
    $this->filterEventRows($rows);
    
    if(count($rows) === 0) {
      CRM_Core_Error::fatal(ts('You do not have permission to view this event'));
    }
  }
  
  /**
  * Iterates 'row' array from template and removes events where current logged in user does not have 
  * editing rights. Editing rights are based on relationship tree.
  *
  * @param CRM_Core_Page|CRM_Core_Form $page CiviCRM Page or Form object
  */
  private function filterManagementEventRows(&$page) {
    $template = $page->getTemplate();
    $rows = $template->get_template_vars("rows");
  
    $this->filterEventRows($rows);
    
    $page->assign("rows", $rows);
  }
  
  /**
  * Iterates 'eventSummary' variable 'events' array from template and removes events where current logged in user does not have 
  * editing rights. Editing rights are based on relationship tree.
  *
  * @param CRM_Core_Page|CRM_Core_Form $page CiviCRM Page or Form object
  */
  private function filterDashBoardEventRows(&$page) {
    $template = $page->getTemplate();
    $eventSummary = $template->get_template_vars("eventSummary");
    
    //If dashboard is empty, do nothing
    if(!array_key_exists("events", $eventSummary)) {
     return;
    }
    
    $rows = $eventSummary["events"];
    $this->filterEventRows($rows);
    
    $eventSummary["events"] = $rows;
    $page->assign("eventSummary", $eventSummary);
  }
  
  /**
  * Iterates 'rows' array from template and removes participants to whom current logged in user does not have 
  * editing rights. Editing rights are based on relationship tree.
  *
  * @param CRM_Core_Page|CRM_Core_Form $page CiviCRM Page or Form object
  */
  private function filterParticipants(&$page) {
    $template = $page->getTemplate();
    $rows = $template->get_template_vars("rows");
    
    //If there are no participants (this happens before search) do not continue
    if(!is_array($rows)) {
      return;
    }
    
    $currentUserContactID = $this->getCurrentUserContactID();
    
    //All contact IDs the current logged in user has rights to edit through relationships
    $worker = new RelationshipACLQueryWorker();
    $allowedContactIDs = $worker->getContactIDsWithEditPermissions($currentUserContactID);
    
    //Remove participants that current user do not have edit rights
    foreach ($rows as $index => &$row) {
      if(!in_array($row["contact_id"], $allowedContactIDs)) {
        unset($rows[$index]);
      }
    }
    
    $template->assign("rows", $rows);
    
    //Update row count info
    $rowCount = count($rows);
    $rowsEmpty = $rowCount ? FALSE : TRUE;
    $template->assign("rowsEmpty", $rowsEmpty);
    $template->assign("rowCount", $rowCount);
  }
  
  /**
  * Iterates rows array from template and removes events where current logged in user does not have 
  * editing rights. Editing rights are based on relationship tree.
  *
  * @param array $rows Array of events
  */
  private function filterEventRows(&$rows) {
    $currentUserContactID = $this->getCurrentUserContactID();
    
    //All contact IDs the current logged in user has rights to edit through relationships
    $worker = new RelationshipACLQueryWorker();
    $allowedContactIDs = $worker->getContactIDsWithEditPermissions($currentUserContactID);
    
    //Array with event ID as key and event owner contact ID as value
    $worker = new CustomFieldHelper($this->getEventOwnerCustomGroupNameFromConfig());
    $eventOwnerMap = $worker->loadAllValues();
    
    foreach ($rows as $eventID => &$row) {
      //Skip events that does not have owner info. These are always visible.
      if(!array_key_exists($eventID, $eventOwnerMap)) {
        continue;
      }
      
      //Get event owner contact ID from custom field
      $eventOwnerContactID = $eventOwnerMap[$eventID];
      
      //If logged in user contact ID is not allowed to edit event, remove event from array
      if(!in_array($eventOwnerContactID, $allowedContactIDs)) {
        unset($rows[$eventID]);
      }
    }
  }
  
  /**
  * Checks that Manage Events URL contains crmRowCount parameter. 
  * If not, do redirect to same page with crmRowCount paramer. crmRowCount is needed 
  * to remove pager so all rows are always visible. Pager is broken because this module 
  * filters rows after pager is constructed.
  */
  private function checkPagerRowCount() {
    $currentURL = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    
    if(!isset($_GET["crmRowCount"])) {
      CRM_Utils_System::redirect($currentURL . "&crmRowCount=9999999");
    }
  }
  
  /**
  * Returns current logged in user contact ID.
  *
  * @return int Contact ID
  */
  private function getCurrentUserContactID() {
    global $user;
    $userID = $user->uid;

    $params = array(
      'uf_id' => $userID,
      'version' => 3
    );
    $result = civicrm_api( 'UFMatch','Get',$params );
    $values = array_values ($result['values']);
    $contact_id = $values[0]['contact_id'];
    
    return $contact_id;
  }
  
  /**
  * Return Contribution custom field group name that is used to store contribution 
  * owner contact id.
  *
  * @return string Custom field group title name.
  */
  private function getEventOwnerCustomGroupNameFromConfig() {
    $sql = "
      SELECT config_value  
      FROM civicrm_relationshipeventacl_config
      WHERE config_key = '".$this->configKey_eventOwnerCustomGroupName."'
    ";
    
    return CRM_Core_DAO::singleValueQuery($sql);
  }
}