<?php

/**
 * @file
 *
 * This file provides drush compatibility code for civicrm-fix-fks.php,
 * which provides functions for keeping your CiviCRM foreign keys 
 * harmonized. 
 */

function cfk_drush_command() {
  $items = array();
  $items['cfk-generate'] =
    array('description' => "Generate a reference file of the correct foreign keys and save it. Run this command against a fresh install of the same version to which you want to compare.");
  $items['cfk-show'] =
    array('description' => "Show SQL statements that would be executed to harmonize your foreign keys, but don't execute them.");
  $items['cfk-fix'] =
    array('description' => "Run SQL staements to fix your foreign keys.");
  $items['cfk-orphans-show'] =
    array( 'description' => "Report all foreign key orphans in your database.");
  $items['cfk-orphans-fix'] =
    array( 'description' => "Fix all orphaned records (this will delete data).");
  return $items;
}

function cfk_output($msg, $severity) {
  drush_log($msg, $severity);
}

function cfk_init() {
  _civicrm_init();
  require_once(__DIR__ . '/cfk.php');
}

function drush_cfk_generate() {
  cfk_init();
  cfk_generate();
}

function drush_cfk_show() {
  cfk_init();
  $dry_run = TRUE;
  cfk_fix($dry_run);
}

function drush_cfk_fix() {
  cfk_init();
  $dry_run = FALSE;
  cfk_fix($dry_run);
}

function drush_cfk_orphans_show() {
  cfk_init();
  $dry_run = TRUE;
  cfk_delete_orphans($dry_run);
}

function drush_cfk_orphans_fix() {
  cfk_init();
  $dry_run = FALSE;
  cfk_delete_orphans($dry_run);
}
?>
