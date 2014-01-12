<?php

require_once "RelationshipEventACLWorker.php";

function relationshipEventACL_civicrm_pageRun(&$page) {
  $worker = new RelationshipEventACLWorker();
  $worker->run($page);
}