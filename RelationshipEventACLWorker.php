<?php

/**
* Only import worker if it is not already loaded. Multiple imports can happen
* because relationshipACL and relationshipEvenACL modules uses same worker. 
*/
if(class_exists('RelationshipACLQueryWorker') === false) {
  require_once "RelationshipACLQueryWorker.php";
}
RelationshipACLQueryWorker::checkVersion("1.1");

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
  * Executed when Event participant contribution page is built.
  * Checks if user has rights to edit contribution.
  *
  * @param CRM_Contribute_Page_Tab $page Contribution edit page
  */
  public function contributionPageRunHook(&$page) {
    /*
    * CRM_Contribute_Page_Tab is also used to load snippets by Ajax. 
    * Lets only check permissions for main page and not for Ajax snippets.
    */
    if(isset($_GET["snippet"])) {
      return;
    }
    
    $this->checkParticipantContributionEditPermission($page);
  }
  
  /**
  * Executed when Event participant page is built.
  * Checks if user has rights to edit participant.
  *
  * @param CRM_Event_Page_Tab $page Participant edit page
  */
  public function participantPageRunHook(&$page) {
    /*
    * CRM_Event_Page_Tab is also used to load snippets by Ajax. 
    * Lets only check permissions for main page and not for Ajax snippets.
    */
    if(isset($_GET["snippet"])) {
      return;
    }
    
    $this->checkParticipantEditPermission($page);
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
  * Check if current logged in user has rights to edit selected participant. Show fatal error if no permission.
  *
  * @param CRM_Event_Page_Tab $page Participant edit page
  */
  private function checkParticipantEditPermission(&$page) {
    $participantId = $page->_id;
    $eventID = $this->getParticipantEventId($participantId);
    
    $rows = array();
    $rows[$eventID] = array();
    $this->filterEventRows($rows);
    
    if(count($rows) === 0) {
      CRM_Core_Error::fatal(ts('You do not have permission to edit this participant'));
    }
  }
  
  /**
  * Check if current logged in user has rights to edit selected contribution. Show fatal error if no permission.
  *
  * @param CRM_Contribute_Page_Tab $page Contribution edit page
  */
  private function checkParticipantContributionEditPermission(&$page) {
    $contributionId = $page->_id;
    $dao = new CRM_Contribute_DAO_Contribution();
    $dao->get("id", $contributionId);
    $contributonPageId = $dao->contribution_page_id;
    
    /*
    * Contribution page contribution edit rights are checked by relationshipContributionACL extension. 
    * Contributions that has contribution_page_id set belong to Contribution page.
    */
    if(isset($contributonPageId)) {
      return;
    }
  
    //Find event id from civicrm_participant_payment and civicrm_participant tables
    $eventID = $this->getContributionEventId($contributionId);
    
    $rows = array();
    $rows[$eventID] = array();
    $this->filterEventRows($rows);
    
    if(count($rows) === 0) {
      CRM_Core_Error::fatal(ts('You do not have permission to view this contribution'));
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
  * Iterates 'rows' array from template and removes participants to which event logged in user does not have 
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
    $worker = RelationshipACLQueryWorker::getInstance();
    $allowedContactIDs = $worker->getContactIDsWithEditPermissions($currentUserContactID);
    
    //Array with event ID as key and event owner contact ID as value
    $worker = new CustomFieldHelper($this->getEventOwnerCustomGroupNameFromConfig());
    $eventOwnerMap = $worker->loadAllValues();
    
    foreach ($rows as $index => &$row) {
      $eventID = $row["event_id"];
    
      //Skip events that does not have owner info. These are always visible.
      if(!array_key_exists($eventID, $eventOwnerMap)) {
        continue;
      }
      
      //Get event owner contact ID from custom field
      $eventOwnerContactID = $eventOwnerMap[$eventID];
      
      //If logged in user contact ID is not allowed to edit event, remove event from array
      if(!in_array($eventOwnerContactID, $allowedContactIDs)) {
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
    $worker = RelationshipACLQueryWorker::getInstance();
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
    
    $customGroupName = CRM_Core_DAO::singleValueQuery($sql);
    
    if(!isset($customGroupName)) {
      CRM_Core_Error::fatal(ts('relationshipEventACL extension requires Custom field config for Event ownership. '.
      'Add Custom group name to civicrm_relationshipeventacl_config table.'));
    }
    
    return $customGroupName;
  }
  
  /**
  * Find event id for Event participation contribution.
  *
  * @param int|string $contributionId Contribution id
  * @return int Event id
  */
  private function getContributionEventId($contributionId) {
    $contributionId = (int) $contributionId;
  
    //Find participant id
    $sql = "
      SELECT participant_id  
      FROM civicrm_participant_payment
      WHERE contribution_id = $contributionId
    ";
    
    $participantId = CRM_Core_DAO::singleValueQuery($sql);
    
    return $this->getParticipantEventId($participantId);
  }
  
  /**
  * Find event id for Event participation.
  *
  * @param int|string $participantId Contribution id
  * @return int Event id
  */
  private function getParticipantEventId($participantId) {
    $participantId = (int) $participantId;
    
    $sql = "
      SELECT event_id  
      FROM civicrm_participant
      WHERE id = $participantId
    ";
    
    return CRM_Core_DAO::singleValueQuery($sql);
  }
}