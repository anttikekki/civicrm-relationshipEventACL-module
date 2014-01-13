<?php

/**
* Only import worker if it is not already loaded. Multiple imports can happen
* because relationshipACL and relationshipEvenACL modules uses same worker. 
*/
if(class_exists('RelationshipACLQueryWorker') === false) {
  require_once "RelationshipACLQueryWorker.php";
}

/**
 * Worker to solve Event visibility and edit rights for user from relationship edit rights.
 */
class RelationshipEventACLWorker {
  /**
  * Event custom field table name.
  */
  private $eventCustomFieldTable = "civicrm_value_tapahtuman_omistajuus_2";
  
  /**
  * Event custom field table contact ID column name.
  */
  private $eventCustomFieldContactIDColumn = "j_rjest_j_organisaatio_1";
  
  /**
  * Start worker.
  *
  * @param CRM_Core_Page|CRM_Core_Form $page CiviCRM Page or Form object from hook
  */
  public function run(&$page) {
    //Manage events
    if($page instanceof CRM_Event_Page_ManageEvent) {
      $this->filterManagementEventRows($page);
      $this->createPager($page);
    }
    //Edit event
    else if($page instanceof CRM_Event_Form_ManageEvent) {
      $this->checkEventEditPermission($page);
    }
    //Dashboard
    else if($page instanceof CRM_Event_Page_DashBoard) {
      $this->filterDashBoardEventRows($page);
    }
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
    $rows = $eventSummary["events"];
  
    $this->filterEventRows($rows);
    
    $eventSummary["events"] = $rows;
    $page->assign("eventSummary", $eventSummary);
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
    $allowedContactIDs = $this->getContactIDsWithEditPermissions($currentUserContactID);
    
    //Array with event ID as key and event owner contact ID as value
    $eventOwnerMap = $this->getEventOwnerCustomFieldContactIDs();
    
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
  * Count the length of 'rows' array from template. Counts only rows with integer id.
  *
  * @param CRM_Core_Page|CRM_Core_Form $page CiviCRM Page or Form object
  * @return int rowcount
  */
  private function getEventRowCount(&$page) {
    $template = $page->getTemplate();
    $rows = $template->get_template_vars("rows");
    $eventCount = 0;
    
    foreach ($rows as $eventID => &$row) {
      if(is_int($eventID)) {
        $eventCount++;
      }
    }
    
    return $eventCount;
  }
  
  /**
  * Recreates pager to update it to new rowcount after filtering.
  *
  * @param CRM_Core_Page|CRM_Core_Form $page CiviCRM Page or Form object
  */
  private function createPager(&$page) {
    $rowCount = $this->getEventRowCount($page);
  
    $params['status'] = ts('Event %%StatusMessage%%');
    $params['csvString'] = NULL;
    $params['buttonTop'] = 'PagerTopButton';
    $params['buttonBottom'] = 'PagerBottomButton';
    $params['rowCount'] = $page->get(CRM_Utils_Pager::PAGE_ROWCOUNT);
    if (!$params['rowCount']) {
      $params['rowCount'] = CRM_Utils_Pager::ROWCOUNT;
    }
    $params['total'] = $rowCount;

    $pager = new CRM_Utils_Pager($params);
    $page->assign_by_ref('pager', $pager);
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
  * Loads all event owner custom field data.
  *
  * @return array Associative array where key is event ID and value is contact ID.
  */
  private function getEventOwnerCustomFieldContactIDs() {
    $contactIDColumn = $this->eventCustomFieldContactIDColumn;
    $customFieldTable = $this->eventCustomFieldTable;
  
    $sql = "SELECT entity_id AS event_id, $contactIDColumn AS contact_id 
      FROM $customFieldTable
    ";
    $dao = CRM_Core_DAO::executeQuery($sql);
    
    $result = array();
    while ($dao->fetch()) {
      $result[$dao->event_id] = $dao->contact_id;
    }
    
    return $result;
  }

  /**
  * Loads all contact IDs where current logged in user has edit rights trouhg relationships.
  * Uses RelationshipACLQueryWorker.
  *
  * @return int[] Contact IDs.
  */
  private function getContactIDsWithEditPermissions($contactID) {
    $worker = new RelationshipACLQueryWorker();
    $contactTableName = $worker->createContactsTableWithEditPermissions($contactID);
    
    $sql = "SELECT contact_id 
      FROM $contactTableName
    ";
    $dao = CRM_Core_DAO::executeQuery($sql);
    
    $contactIDs = array();
    while ($dao->fetch()) {
      $contactIDs[] = $dao->contact_id;
    }
    
    return $contactIDs;
  }
}