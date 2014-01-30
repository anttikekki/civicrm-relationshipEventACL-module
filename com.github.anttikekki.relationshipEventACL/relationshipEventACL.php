<?php

/**
* RelationshipEventACLWorker hooks.
*/

require_once "RelationshipEventACLWorker.php";

/**
* Implemets CiviCRM 'pageRun' hook.
*
* @param CRM_Core_Page $page Current page.
*/
function relationshipEventACL_civicrm_pageRun(&$page) {
  $worker = RelationshipEventACLWorker::getInstance();
  $worker->run($page);
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
  $worker = RelationshipEventACLWorker::getInstance();
  $worker->run($page);
}

/**
* Implemets CiviCRM 'buildForm' hook.
*
* @param String $formName Name of current form.
* @param CRM_Core_Form $form Current form.
*/
function relationshipEventACL_civicrm_buildForm($formName, &$form) {
  $worker = RelationshipEventACLWorker::getInstance();
  $worker->run($form);
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