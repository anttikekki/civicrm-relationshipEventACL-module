<?php

require_once "RelationshipEventACLWorker.php";

/**
* Implemets CiviCRM 'pageRun' hook.
*
* @param CRM_Core_Page $page Current page.
*/
function relationshipEventACL_civicrm_pageRun(&$page) {
  $worker = new RelationshipEventACLWorker();
  $worker->run($page);
}

/**
* Implemets CiviCRM 'buildForm' hook.
*
* @param String $formName Name of current form.
* @param CRM_Core_Form $form Current form.
*/
function relationshipEventACL_civicrm_buildForm($formName, &$form) {
  $worker = new RelationshipEventACLWorker();
  $worker->run($form);
}