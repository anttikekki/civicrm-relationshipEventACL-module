<?php

require_once "RelationshipEventACLWorker.php";

function relationshipEventACL_civicrm_pageRun(&$page) {
  $worker = new RelationshipEventACLWorker();
  $worker->run($page);
}

function relationshipEventACL_civicrm_buildForm($formName, &$form) {
  $worker = new RelationshipEventACLWorker();
  $worker->run($form);
}