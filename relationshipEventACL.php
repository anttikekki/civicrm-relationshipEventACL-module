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
* Implemets CiviCRM 'alterContent' hook.
*
* @param string $content previously generated content
* @param string $context context of content - page or form
* @param string $tplName the file name of the tpl
* @param object $object a reference to the page or form object
*/
function relationshipEventACL_civicrm_alterContent(&$content, $context, $tplName, &$object) {
  //Contact Contributions tab
  if($object instanceof CRM_Contribute_Page_Tab) {
    $worker = new RelationshipEventACLWorker();
    $worker->contactContributionTabAlterContentHook($content);
  }
  //Contact Events tab
  else if($object instanceof CRM_Event_Page_Tab) {
    $worker = new RelationshipEventACLWorker();
    $worker->contactEventTabAlterContentHook($content);
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
  $url = $params[52]["child"][59]["attributes"]["url"];
  $url = $url . "&crmRowCount=9999999";
  $params[52]["child"][59]["attributes"]["url"] = $url;
}