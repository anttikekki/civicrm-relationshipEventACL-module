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
  * Singleton instance of this worker. Singleton is needed to check that 
  * every page is only processed once.
  */
  protected static $instance = null;
  
  /**
  * Array of page or form class names that have been processe during this request. 
  * This array is used to make sure that every page is only processed once per request.
  */
  protected static $processedPageClassNames = array();
  
  /**
  * Event custom field table name.
  */
  private $eventCustomFieldTable = "civicrm_value_tapahtuman_omistajuus_2";
  
  /**
  * Event custom field table contact ID column name.
  */
  private $eventCustomFieldContactIDColumn = "j_rjest_j_organisaatio_1";
  
  /**
  * Only getInstance() can create new instance. Hide constructor.
  */
  protected function __construct() {
    
  }
  
  /**
  * Start worker.
  *
  * @param CRM_Core_Page|CRM_Core_Form $page CiviCRM Page or Form object from hook
  */
  public function run(&$page) {
    if($this->isPageProcessed($page)) {
      return;
    }
  
    //Manage events
    if($page instanceof CRM_Event_Page_ManageEvent) {
      $this->checkPagerRowCount();
      $this->filterManagementEventRows($page);
    }
    //Edit event
    else if($page instanceof CRM_Event_Form_ManageEvent) {
      $this->checkEventEditPermission($page);
    }
    //Dashboard
    else if($page instanceof CRM_Event_Page_DashBoard) {
      $this->filterDashBoardEventRows($page);
    }
    //Participant search
    else if($page instanceof CRM_Event_Form_Search) {
      $this->filterParticipants($page);
      
      //JavaScript adds 'limit=0' to participants search form action URL. This removes paging.
      CRM_Core_Resources::singleton()->addScriptFile('com.github.anttikekki.relationshipEventACL', 'participantsSearch.js');
    }
    
    //Add page or form class name to static array so that we can check that every page is only processed once
    static::$processedPageClassNames[] = get_class($page);
  }
  
  /**
  * Check if current page is already processed by this module. Some hooks trigger multiple times. 
  * One page can also trigger multiple triggers.
  *
  * @param CRM_Core_Page|CRM_Core_Form $page CiviCRM Page or Form object
  */
  public function isPageProcessed(&$page) {
    return in_array(get_class($page), static::$processedPageClassNames);
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
  * Returns singleton instance of this worker. Singleton is needed to check that 
  * every page is only processed once.
  *
  * @return RelationshipEventACLWorker Worker instance
  */
  public static function getInstance() {
      if (!isset(static::$instance)) {
          static::$instance = new RelationshipEventACLWorker();
      }
      return static::$instance;
  }
}