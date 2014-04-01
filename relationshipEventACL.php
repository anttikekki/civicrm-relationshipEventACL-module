<?php

/**
* RelationshipEventACLWorker hooks.
*/

require_once "RelationshipEventACLWorker.php";

/**
* Implements CiviCRM 'install' hook.
*/
function relationshipEventACL_civicrm_install() {
  //Add table for configuration
  $sql = "
    CREATE TABLE IF NOT EXISTS civicrm_relationshipeventacl_config (
      config_key varchar(255) NOT NULL,
      config_value varchar(255) NOT NULL,
      PRIMARY KEY (`config_key`)
    ) ENGINE=InnoDB;
  ";
  CRM_Core_DAO::executeQuery($sql);
}

/**
* Implemets CiviCRM 'pageRun' hook.
*
* @param CRM_Core_Page $page Current page.
*/
function relationshipEventACL_civicrm_pageRun(&$page) {
  //Manage Events
  if($page instanceof CRM_Event_Page_ManageEvent) {
    $worker = new RelationshipEventACLWorker();
    $worker->manageEventPageRunHook($page);
  }
  //Event Dashboard
  else if($page instanceof CRM_Event_Page_DashBoard) {
    $worker = new RelationshipEventACLWorker();
    $worker->dashboardPageRunHook($page);
  }
  //Event participant contribution
  else if($page instanceof CRM_Contribute_Page_Tab) {
    $worker = new RelationshipEventACLWorker();
    $worker->contributionPageRunHook($page);
  }
  //Partcipant edit page
  else if($page instanceof CRM_Event_Page_Tab) {
    $worker = new RelationshipEventACLWorker();
    $worker->participantPageRunHook($page);
  }
}

/**
* Implemets CiviCRM 'alterTemplateFile' hook.
*
* @param String $formName Name of current form.
* @param CRM_Core_Form $form Current form.
* @param CRM_Core_Form $context Page or form.
* @param String $tplName The file name of the tpl - alter this to alter the file in use.
*/
function relationshipEventACL_civicrm_alterTemplateFile($formName, &$form, $context, &$tplName) {
  //Participant search
  if($form instanceof CRM_Event_Form_Search) {
    $worker = new RelationshipEventACLWorker();
    $worker->participantSearchAlterTemplateFileHook($form);
  }
  //Contribution search
  else if($form instanceof CRM_Contribute_Form_Search) {
    $worker = new RelationshipEventACLWorker();
    $worker->contributionSearchAlterTemplateFileHook($form);
  }
  //Contact Contributions tab
  else if($form instanceof CRM_Contribute_Page_Tab) {
    $worker = new RelationshipEventACLWorker();
    $worker->contactContributionTabAlterTemplateFileHook($form);
  }
  //Contact Events tab
  else if($form instanceof CRM_Event_Page_Tab) {
    $worker = new RelationshipEventACLWorker();
    $worker->contactEventTabAlterTemplateFileHook($form);
  }
  //Contact main page
  else if($form instanceof CRM_Contact_Page_View_Summary) {
    $worker = new RelationshipEventACLWorker();
    $worker->contactMainPageAlterTemplateFileHook($form);
  }
  //Contribution dashboard
  else if($form instanceof CRM_Contribute_Page_DashBoard) {
    $worker = new RelationshipEventACLWorker();
    $worker->contributionDashboardAlterTemplateHook($form);
  }
  //Event reports
  else if($form instanceof CRM_Report_Form_Event) {
    $worker = new RelationshipEventACLWorker();
    $worker->eventReportsAlterTemplateHook($form);
  }
  //Contribution reports (Event contribution filtering)
  else if(RelationshipEventACLWorker::isContributionReportClassName($formName)) {
    $worker = new RelationshipEventACLWorker();
    $worker->contributionReportsAlterTemplateHook($form);
  }
  //Extension admin page
  else if($form instanceof Admin_Page_RelationshipEventACLAdmin) {
    $res = CRM_Core_Resources::singleton();
    $res->addScriptFile('com.github.anttikekki.relationshipEventACL', 'Admin/Page/admin.js');
    
    //Add CMS neutral ajax callback URLs
    $res->addSetting(array('relationshipEventACL' => 
      array(
        'getConfigAjaxURL' =>  CRM_Utils_System::url('civicrm/relationshipEventACL/settings/ajax/getConfig'),
        'saveConfigRowAjaxURL' =>  CRM_Utils_System::url('civicrm/relationshipEventACL/settings/ajax/saveConfigRow'),
        'deleteConfigRowAjaxURL' =>  CRM_Utils_System::url('civicrm/relationshipEventACL/settings/ajax/deleteConfigRow')
      )
    ));
  }
}

/**
* Implemets CiviCRM 'buildForm' hook.
*
* @param String $formName Name of current form.
* @param CRM_Core_Form $form Current form.
*/
function relationshipEventACL_civicrm_buildForm($formName, &$form) {
  if($form instanceof CRM_Event_Form_ManageEvent) {
    $worker = new RelationshipEventACLWorker();
    $worker->eventFormBuildFormHook($form);
  }
}

/**
* Implemets CiviCRM 'navigationMenu' hook. Alters navigation menu to 
* remove Pager from 'Manage Event' page. Pager is broken because of row 
* filtering done by this module.
*
* Menu rebuild is required to make this work.
*
* @param Array $params Navigation menu structure.
*/
function relationshipEventACL_civicrm_navigationMenu(&$params) {
  //Edit Manage Event menu
  $url = $params[52]["child"][59]["attributes"]["url"];
  $url = $url . "&crmRowCount=9999999";
  $params[52]["child"][59]["attributes"]["url"] = $url;
  
  /*
  * Add admin menu for extension
  */
  //Find last index of Administer menu children
  $maxKey = max(array_keys($params[108]['child']));
  
  //Add extension menu as Admin menu last children
  $params[108]['child'][$maxKey+1] = array(
     'attributes' => array (
        'label'      => 'RelationshipEventACL',
        'name'       => 'RelationshipEventACL',
        'url'        => null,
        'permission' => null,
        'operator'   => null,
        'separator'  => null,
        'parentID'   => null,
        'navID'      => $maxKey+1,
        'active'     => 1
      ),
     'child' =>  array (
        '1' => array (
          'attributes' => array (
             'label'      => 'Settings',
             'name'       => 'Settings',
             'url'        => 'civicrm/relationshipEventACL/settings',
             'permission' => 'administer CiviCRM',
             'operator'   => null,
             'separator'  => 1,
             'parentID'   => $maxKey+1,
             'navID'      => 1,
             'active'     => 1
              ),
          'child' => null
        )
      )
  );
}

/**
* Implemets CiviCRM 'config' hook.
*
* @param object $config the config object
*/
function relationshipEventACL_civicrm_config(&$config) {
  $template =& CRM_Core_Smarty::singleton();
  $extensionDir = dirname(__FILE__);
 
  // Add extension template directory to the Smarty templates path
  if (is_array($template->template_dir)) {
    array_unshift($template->template_dir, $extensionDir);
  }
  else {
    $template->template_dir = array($extensionDir, $template->template_dir);
  }

  //Add extension folder to included folders list so that Ajax php is found whe accessin it from URL
  $include_path = $extensionDir . DIRECTORY_SEPARATOR . PATH_SEPARATOR . get_include_path();
  set_include_path($include_path);
}

/**
* Implemets CiviCRM 'xmlMenu' hook.
*
* @param array $files the array for files used to build the menu. You can append or delete entries from this file. 
* You can also override menu items defined by CiviCRM Core.
*/
function relationshipEventACL_civicrm_xmlMenu( &$files ) {
  //Add Ajax and Admin page URLs to civicrm_menu table so that they work
  $files[] = dirname(__FILE__)."/menu.xml";
}