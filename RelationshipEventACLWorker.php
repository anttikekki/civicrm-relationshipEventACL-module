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
* Only import phpQuery if it is not already loaded. Multiple imports can happen
* because relationshipEvenACL module uses same worker. 
*/
if(class_exists('phpQuery') === false) {
  require_once('phpQuery.php');
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
  * Executed when Contact Contributions tab is built.
  * Filters Contribution page contribution rows based on Contribution page owner.
  *
  * Html manipulation is required because page data before rendering can not be manipulated with hooks. 
  * Contact Contributions tab is class CRM_Contribute_Page_Tab that embeds instance of CRM_Contribute_Form_Search.
  * This instance can not be accessed and modified so we need to modify the result.
  *
  * @param string $html Search contributions tab HTML
  */
  public function contactContributionTabAlterContentHook(&$html) {
    $this->filterContactContributionTableHTMLRows($html);
  }
  
  /**
  * Executed when Contact Events tab is built.
  * Filters Event rows based on Event owner.
  *
  * Html manipulation is required because page data before rendering can not be manipulated with hooks. 
  * Contact Events tab is class CRM_Event_Page_Tab that embeds instance of CRM_Event_Form_Search.
  * This instance can not be accessed and modified so we need to modify the result.
  *
  * @param string $html Search events tab HTML
  */
  public function contactEventTabAlterContentHook(&$html) {
    $this->filterContactEventTableHTMLRows($html);
  }
  
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
  * Executed when Contribution search form is built.
  * Filters Contributions rows based on permissions.
  *
  * @param CRM_Contribute_Form_Search $form Search Contributions form
  */
  public function contributionSearchAlterTemplateFileHook(&$form) {
    $this->filterEventContributionsSearchFormResults($form);
    
    //No need to add JavaScript to modify pager because it is done by relationshipCOntributionACL module.
  }
  
  /**
  * Executed when Manage Events page is built.
  * Disables pager and filters Event rows based on permissions.
  *
  * @param CRM_Event_Page_ManageEvent $page Manage Events page
  */
  public function manageEventPageRunHook(&$page) {
    $this->checkPagerRowCount(9999999);
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
  * Executed when Contact Contributions tab is built.
  * Filters contribution rows based on Contribution page owner.
  *
  * @param string $html Search contributions tab HTML
  */
  private function filterContactContributionTableHTMLRows(&$html) {
    $doc = phpQuery::newDocumentHTML($html);
    $contributionIds = $this->getContributionSearchFormHtmlTableContributionIds($doc);
    $allowedEventContributionIds = $this->getAllowedEventContributionIds($contributionIds);
    
    /* 
    * Remove Event contribution rows that are not allowed to current logged in user.
    * Contribution page contributions are filtered by relationshipContributionACL module.
    */
    foreach ($contributionIds as $contributionId) {
      if(!in_array($contributionId, $allowedEventContributionIds)) {
        $doc->find("#Search tr.crm-contribution_" . $contributionId)->remove();
      }
    }
    
    $html = $doc->getDocument();
  }
  
  /**
  * Executed when Contact Events tab is built.
  * Filters Event rows based on Event owner.
  *
  * @param string $html Search events tab HTML
  */
  private function filterContactEventTableHTMLRows(&$html) {
    $doc = phpQuery::newDocumentHTML($html);
    $eventIds = $this->getEventSearchFormHtmlTableEventIds($doc);
    $allowedEventIds = $this->getAllowedEventIds($eventIds);
    
    //Remove Event rows that are not allowed to current logged in user
    foreach ($eventIds as $eventId) {
      if(!in_array($eventId, $allowedEventIds)) {
        $doc->find("#Search tr.crm-event_" . $eventId)->remove();
      }
    }
    
    $html = $doc->getDocument();
  }
  
  /**
  * Iterates 'row' array from template and removes Contributions that belongs to Event that current logged in user does not have 
  * editing rights. Editing rights are based on relationship tree.
  *
  * @param CRM_Contribute_Form_Search CiviCRM Page for Contribution search
  */
  private function filterEventContributionsSearchFormResults(&$form) {
    $template = $form->getTemplate();
    $rows = $template->get_template_vars("rows");
    
    //If there are no contribution search results (this happens before search) do not continue
    if(!is_array($rows)) {
      return;
    }
  
    $this->filterEventContributions($rows);
    $template->assign("rows", $rows);
  }
  
  /**
  * Iterates rows array from template and removes Contributions that belongs to Events where current logged in user does not have 
  * editing rights. Editing rights are based on relationship tree.
  *
  * @param array $rows Array of Contributions
  */
  private function filterEventContributions(&$rows) {
    //If there are no contribution search results (this happens before search) do not continue
    if(!is_array($rows)) {
      return;
    }
    
    //Find all contribution ids
    $contributionIds = array();
    foreach ($rows as $index => &$row) {
      $contributionIds[] = $row["contribution_id"];
    }
    
    $allowedContributionIds = $this->getAllowedEventContributionIds($contributionIds);
    
    foreach ($rows as $index => &$row) {
      $contributionId = $row["contribution_id"];
      
      if(!in_array($contributionId, $allowedContributionIds)) {
        unset($rows[$index]);
      }
    }
  }
  
  /**
  * Iterates array of Contribution ids and removes Contributions ids that belongs to Events where current logged in user does not have 
  * editing rights. Editing rights are based on relationship tree.
  *
  * @param array $contributionIds Array of Contribution ids
  * @return array Array of allowed Contribution ids
  */
  private function getAllowedEventContributionIds($contributionIds) {
    /*
    * Find Event ids for contributions. Uses civicrm_participant_payment table to link Contribution to Event.
    * Return value array key is Contribution id and value is Event id.
    * Returned array contains contribution ids only for Event participant contributions.
    */
    $contributionEventIds = $this->getContributionsEventIds($contributionIds);
    
    $currentUserContactID = $this->getCurrentUserContactID();
    
    //All contact IDs the current logged in user has rights to edit through relationships
    $aclWorker = RelationshipACLQueryWorker::getInstance();
    $allowedContactIDs = $aclWorker->getContactIDsWithEditPermissions($currentUserContactID);
    
    //Array with event ID as key and event owner contact ID as value
    $customFieldWorker = new CustomFieldHelper($this->getEventOwnerCustomGroupNameFromConfig());
    $eventOwnerMap = $customFieldWorker->loadAllValues();
    
    foreach ($contributionIds as $index => &$contributionId) {
      /*
      * If contribution id is not found it means that contribution is from Contribution page. 
      * Contribution page Contributions are filtered by relationshipContributionACL module.
      */
      if(!isset($contributionEventIds[$contributionId])) {
        continue;
      }
      
      $eventId = $contributionEventIds[$contributionId];
    
      //Skip Events that does not have owner info. These contributions are always visible.
      if(!array_key_exists($eventId, $eventOwnerMap)) {
        continue;
      }
      
      //Get event owner contact ID from custom field
      $eventOwnerContactID = $eventOwnerMap[$eventId];
      
      //If logged in user contact ID is not allowed to edit event, remove contribution from array
      if(!in_array($eventOwnerContactID, $allowedContactIDs)) {
        unset($contributionIds[$index]);
      }
    }
    
    return $contributionIds;
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
    
    //Find all event ids
    $eventIds = array();
    foreach ($rows as $eventId => &$row) {
      $eventIds[] = (int) $row["event_id"];
    }
    
    $allowedEventIds = $this->getAllowedEventIds($eventIds);
    
    foreach ($rows as $index => &$row) {
      $eventID = (int) $row["event_id"];
      
      //If logged in user is not allowed to edit event, remove event from array
      if(!in_array($eventId, $allowedEventIds)) {
        unset($rows[$index]);
      }
    }
    
    $template->assign("rows", $rows);
  }
  
  /**
  * Iterates rows array from template and removes events where current logged in user does not have 
  * editing rights. Editing rights are based on relationship tree.
  *
  * @param array $rows Array of events
  */
  private function filterEventRows(&$rows) {    
    //Find all event ids
    $eventIds = array();
    foreach ($rows as $eventId => &$row) {
      $eventIds[] = (int) $eventId;
    }
    
    $allowedEventIds = $this->getAllowedEventIds($eventIds);
    
    foreach ($eventIds as $index => $eventId) {
      //If logged in user is not allowed to edit event, remove event from array
      if(!in_array($eventId, $allowedEventIds)) {
        unset($rows[$eventId]);
      }
    }
  }
  
  /**
  * Iterates Event id array and removes Event ids where current logged in user does not have 
  * editing rights. Editing rights are based on relationship tree.
  *
  * @param array $eventIds Array of event ids
  * @return array Array of allowed event ids
  */
  private function getAllowedEventIds($eventIds) {
    $currentUserContactID = $this->getCurrentUserContactID();
    
    //All contact IDs the current logged in user has rights to edit through relationships
    $worker = RelationshipACLQueryWorker::getInstance();
    $allowedContactIDs = $worker->getContactIDsWithEditPermissions($currentUserContactID);
    
    //Array with event ID as key and event owner contact ID as value
    $worker = new CustomFieldHelper($this->getEventOwnerCustomGroupNameFromConfig());
    $eventOwnerMap = $worker->loadAllValues();
    
    foreach ($eventIds as $index => &$eventId) {
      //Skip events that does not have owner info. These are always visible.
      if(!array_key_exists($eventId, $eventOwnerMap)) {
        continue;
      }
      
      //Get event owner contact ID from custom field
      $eventOwnerContactID = $eventOwnerMap[$eventId];
      
      //If logged in user contact ID is not allowed to edit event, remove event from array
      if(!in_array($eventOwnerContactID, $allowedContactIDs)) {
        unset($eventIds[$index]);
      }
    }
    
    return $eventIds;
  }
  
  /**
  * Checks that Manage Events URL contains crmRowCount parameter. 
  * If not, do redirect to same page with crmRowCount paramer. crmRowCount is needed 
  * to remove pager so all rows are always visible. Pager is broken because this module 
  * filters rows after pager is constructed.
  *
  * @param int|string $pagerPageSize Pager page max row count
  */
  private function checkPagerRowCount($pagerPageSize) {
    $currentURL = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    
    if(!isset($_GET["crmRowCount"])) {
      CRM_Utils_System::redirect($currentURL . "&crmRowCount=" . $pagerPageSize);
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
  * Find event id for Event participation contributions.
  *
  * @param array $contributionIds Contribution ids
  * @return array Array where key is contribution id and value is Event id
  */
  private function getContributionsEventIds($contributionIds) {
    //Remove values that are not numeric
    $contributionIds = array_filter($contributionIds, "is_numeric");
    
    if(count($contributionIds) == 0) {
      return array();
    }
  
    $sql = "
      SELECT contribution_id, participant_id  
      FROM civicrm_participant_payment
      WHERE contribution_id IN (". implode(",", $contributionIds) .")
    ";
    
    $dao = CRM_Core_DAO::executeQuery($sql);
    
    $participantIdForContributionId = array();
    while ($dao->fetch()) {
      $participantIdForContributionId[$dao->contribution_id] = $dao->participant_id;
    }
    
    return $this->getParticipantsEventIds($participantIdForContributionId);
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
  
  /**
  * Find event id for Event participations.
  *
  * @param array $participantIdForContributionId Array where key is contribution id and value is Participant id
  * @return array Array where key is contribution id and value is Event id
  */
  private function getParticipantsEventIds($participantIdForContributionId) {
    if(count($participantIdForContributionId) == 0) {
      return array();
    }
  
    $sql = "
      SELECT id, event_id  
      FROM civicrm_participant
      WHERE id IN (". implode(",", $participantIdForContributionId) .")
    ";
    
    $dao = CRM_Core_DAO::executeQuery($sql);
    
    $eventIdForParticipantId = array();
    while ($dao->fetch()) {
      $eventIdForParticipantId[$dao->id] = $dao->event_id;
    }
    
    foreach ($participantIdForContributionId as $contributionId => $participantId) {
      $participantIdForContributionId[$contributionId] = $eventIdForParticipantId[$participantId];
    }
    
    return $participantIdForContributionId;
  }
  
  /**
  * Finds all Contribution ids from Search contributions page HTML string. Ids are stored in 
  * class name "crm-contribution_123".
  *
  * @param phpQuery $doc phpQuery instance holding HTML content
  * @return array Array of rows contribution ids
  */
  private function getContributionSearchFormHtmlTableContributionIds($doc) {
    $contributionIds = array();
    foreach ($doc->find("#Search tr") as $tr) {
      $class = pq($tr)->attr('class');
      
      $startIndex = strrpos($class, "crm-contribution_");
      if($startIndex == 0) {
        continue;
      }
      
      $startIndex += strlen("crm-contribution_");
      $contributionIds[] = (int) substr($class, $startIndex);
    }
    
    return $contributionIds;
  }
  
  /**
  * Finds all Event ids from Search events page HTML string. Ids are stored in 
  * class name "crm-event_123".
  *
  * @param phpQuery $doc phpQuery instance holding HTML content
  * @return array Array of rows event ids
  */
  private function getEventSearchFormHtmlTableEventIds($doc) {
    $eventIds = array();
    foreach ($doc->find("#Search tr") as $tr) {
      $class = pq($tr)->attr('class');
      
      $startIndex = strrpos($class, "crm-event_");
      if($startIndex == 0) {
        continue;
      }
      
      $startIndex += strlen("crm-event_");
      $eventIds[] = (int) substr($class, $startIndex);
    }
    
    return $eventIds;
  }
}