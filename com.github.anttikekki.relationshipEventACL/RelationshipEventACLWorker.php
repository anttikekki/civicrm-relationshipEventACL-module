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
  private $eventCustomFieldTable = "civicrm_value_tapahtuman_omistajuus_2";
  private $eventCustomFieldContactIDColumn = "j_rjest_j_organisaatio_1";
  
  /**
  * Start worker.
  *
  * @param Page|Form $page CiviCRM Page or Form object from hook
  */
  public function run(&$page) {
    //Manage events
    if($page instanceof CRM_Event_Page_ManageEvent) {
      $this->filterEventRows($page);
      $this->createPager($page);
    }
    //Edit event
    else if($page instanceof CRM_Event_Form_ManageEvent) {
      $this->checkEventEditPermission($page);
    }
  }
  
  /**
  * Check if current logged in user has rights to edit selected event. Show fatal error if no permission.
  *
  * @param Page|Form $page CiviCRM Page or Form object
  */
  private function checkEventEditPermission(&$page) {
    $eventID = $page->_id;
     
    $currentUserContactID = $this->getCurrentUserContactID();
    $allowedContactIDs = $this->getContactIDsWithEditPermissions($currentUserContactID);
    
    //Array with event ID as key and event owner contact ID as value
    $eventOwnerMap = $this->getEventOwnerCustomFieldContactIDs();
    
    if(!array_key_exists($eventID, $eventOwnerMap)) {
      return;
    }
    
    $eventOwnerContactID = $eventOwnerMap[$eventID];
    
    if(!in_array($eventOwnerContactID, $allowedContactIDs)) {
      CRM_Core_Error::fatal(ts('You do not have permission to view this event'));
    }
  }
  
  private function filterEventRows(&$page) {
    $template = $page->getTemplate();
    $rows = $template->get_template_vars("rows");
  
    $currentUserContactID = $this->getCurrentUserContactID();
    $allowedContactIDs = $this->getContactIDsWithEditPermissions($currentUserContactID);
    
    //Array with event ID as key and event owner contact ID as value
    $eventOwnerMap = $this->getEventOwnerCustomFieldContactIDs();
    
    foreach ($rows as $eventID => &$row) {
      if(!array_key_exists($eventID, $eventOwnerMap)) {
        continue;
      }
      
      $eventOwnerContactID = $eventOwnerMap[$eventID];
      
      if(!in_array($eventOwnerContactID, $allowedContactIDs)) {
        unset($rows[$eventID]);
      }
    }
    
    $page->assign("rows", $rows);
  }
  
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