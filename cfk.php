<?php

/**
 * @file foreign key manipulation functions.
 *
 * This file contains functions for ensuring your CiviCRM
 * database has all the required foreign keys and will alter
 * your database schema to add or drop them as needed.
 */

/**
 * Delete orphaned foreign key records.
 * Help from https://github.com/michaelirey/mysql-foreign-key-checker
 *
 * This function finds orphans (as defined by the existing schema) and 
 * either deletes them are sets them to NULL depending on the DELETE_RULE. 
 *
 * parameter @dry_run BOOLEAN TRUE if you want to only show what would be executed.
 */
function cfk_delete_orphans($dry_run = FALSE) {
  $sql = "SELECT kcu.`TABLE_NAME`, kcu.`COLUMN_NAME`, kcu.`REFERENCED_TABLE_NAME`,
    kcu.`REFERENCED_COLUMN_NAME`, rc.`DELETE_RULE` FROM information_schema.KEY_COLUMN_USAGE kcu LEFT JOIN
    INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
    WHERE kcu.REFERENCED_TABLE_SCHEMA IS NOT NULL";
  $dao = CRM_Core_DAO::executeQuery($sql);
  while($dao->fetch()) {
    if($dao->TABLE_NAME == $dao->REFERENCED_TABLE_NAME) {
      $find_sql = "SELECT t1.$dao->COLUMN_NAME
        FROM $dao->TABLE_NAME t1 LEFT JOIN $dao->REFERENCED_TABLE_NAME t2 ON
        (t1.$dao->COLUMN_NAME = t2.$dao->REFERENCED_COLUMN_NAME)
        WHERE t1.$dao->COLUMN_NAME IS NOT NULL AND
         t2.$dao->REFERENCED_COLUMN_NAME IS NULL";
    }
    else {
      $find_sql = "SELECT $dao->TABLE_NAME.$dao->COLUMN_NAME
        FROM $dao->TABLE_NAME LEFT JOIN $dao->REFERENCED_TABLE_NAME ON
       ($dao->TABLE_NAME.$dao->COLUMN_NAME = $dao->REFERENCED_TABLE_NAME.$dao->REFERENCED_COLUMN_NAME)
       WHERE $dao->TABLE_NAME.$dao->COLUMN_NAME IS NOT NULL
       AND $dao->REFERENCED_TABLE_NAME.$dao->REFERENCED_COLUMN_NAME IS NULL";
    }
    $cfk_dao = CRM_Core_DAO::executeQuery($find_sql);
    if($cfk_dao->N > 0) {
      $on_delete = "Set the field to NULL";
      if($dao->DELETE_RULE == 'CASCADE') {
        $on_delete = 'Delete the records';
      }
      $msg = "Found " . $cfk_dao->N .
        " records in table $dao->TABLE_NAME without a corresponding record in $dao->REFERENCED_TABLE_NAME. On delete this script will: " .
        $on_delete;
      cfk_output($msg, 'ok');
      $sql = "CREATE TEMPORARY TABLE cruft (id int)";
      if(!$dry_run) CRM_Core_DAO::executeQuery($sql);
      $sql = "INSERT INTO cruft " . str_replace('SELECT ', 'SELECT DISTINCT ', $find_sql);
      if(!$dry_run) CRM_Core_DAO::executeQuery($sql);
      if($dao->DELETE_RULE == 'CASCADE') {
        $sql = "DELETE FROM $dao->TABLE_NAME WHERE $dao->COLUMN_NAME IN (SELECT id FROM cruft)";
      }
      else {
        $sql = "UPDATE $dao->TABLE_NAME SET $dao->COLUMN_NAME = NULL WHERE $dao->COLUMN_NAME IN (SELECT id FROM cruft)";
      }
      if(!$dry_run) CRM_Core_DAO::executeQuery($sql);
      $sql = "DROP TABLE cruft";
      if(!$dry_run) CRM_Core_DAO::executeQuery($sql);
    }
  }
}

/**
 * Get Database version.
 */
function cfk_get_db_version() {
  $sql = 'SELECT version FROM civicrm_domain WHERE id = %0';
  $dao = CRM_Core_DAO::executeQuery($sql, array(0 => array(CIVICRM_DOMAIN_ID, 'Integer')));
  $dao->fetch();
  return $dao->version;
}

/**
 * Get path to foreign key schema file
 */
function cfk_get_schema_path() {
  return __DIR__ . '/schemas/' . cfk_get_db_version() . '.fks.json';
}

/**
 * Generate foreign key schema file
 * 
 * This function creates the output json file with the up-to-date schema.
 * and stores it as a file in the schemas directory prefixed with the
 * CiviCRM version. 
 *
 */
function cfk_generate() {
  $file = cfk_get_schema_path();
  if(file_exists($file)) {
    cfk_output("The file $file already exists.", 'error');
    return;
  }
  $ref = cfk_get_fk();
  file_put_contents($file, json_encode($ref));
}

/**
 * Output foreign key references as an array.
 * 
 * Returns an array of all fk references.
 */
function cfk_get_fk() {
  _civicrm_init();

  $sql = "select kcu.constraint_name, kcu.table_name, kcu.column_name,
    kcu.referenced_table_name, kcu.referenced_column_name, rc.update_rule,
    rc.delete_rule from information_schema.key_column_usage kcu
    join information_schema.referential_constraints rc
    on kcu.constraint_name = rc.constraint_name 
    where referenced_table_schema = database() and
    information_schema.kcu.table_name not like 'civicrm_value%'
    order by constraint_name";
  $dao = CRM_Core_DAO::executeQuery($sql);
  $reference = array();
  while($dao->fetch()) {
    $table = $dao->table_name;
    $constraint_name = $dao->constraint_name;
    $reference[$table][$constraint_name] = array(
      'referenced_table_name' => $dao->referenced_table_name,
      'column_name' => $dao->column_name,
      'referenced_column_name' => $dao->referenced_column_name,
      'update_rule' => $dao->update_rule,
      'delete_rule' => $dao->delete_rule,
    );
   }

  return $reference;
}

/**
 * Generate a sql statement to create a foreign key
 *
 * Given the passed table, constraint name and values, generate
 * an ALTER TABLE query that will add the given constraint.
 */
function cfk_sql_for_fk($table, $constraint_name, $values) {
  $values = (array) $values;
  $referenced_table_name = $values['referenced_table_name'];
  $column_name = $values['column_name'];
  $referenced_column_name = $values['referenced_column_name'];
  $rule = array();
  if($values['delete_rule'] != 'RESTRICT') {
    $rule[] = "ON DELETE " . $values['delete_rule'];
  }
  if($values['update_rule'] != 'RESTRICT') {
    $rule[] = "ON UPDATE " . $values['delete_rule'];
  }
  $rule = trim(implode(' ', $rule));
  
  $sql = "ALTER TABLE `$table` ADD CONSTRAINT $constraint_name FOREIGN KEY
    (`$column_name`) REFERENCES `$referenced_table_name` (`$referenced_column_name`) $rule";
  return $sql;
}

/**
 * Check if a given KEY exists. 
 *
 * Return TRUE if it exists or FALSE otherwise.
 */
function cfk_key_exists($table, $constraint_name) {
  // Check if we need to first drop an key. I can't find any way to search for this
  // information other than SHOW CREATE TABLE.
  $dao = CRM_Core_DAO::executeQuery("SHOW CREATE TABLE `$table`");
  $dao->fetch();
  $sql = '';
  if(preg_match('/KEY `' . $constraint_name . '`/', $dao->Create_Table)) {
    return TRUE;
  }
  return FALSE;
}


/**
 * Ensure all foreign keys are set
 *
 * Compare the current database with a reference set of foreign keys
 * and drop/add foreign keys as necessary.
 *
 * @dry_run BOOLEAN If TRUE, only output queries you would run.
 */
function cfk_fix($dry_run = FALSE) {
  // Pull in reference schema. This was created against civicrm using the function
  // above.
  $json_path = cfk_get_schema_path();
  if(!file_exists($json_path)) {
    cfk_output("I can't find a schema file for your database version ($json_path).", 'error');
    return FALSE;
  }
  $ref = json_decode(file_get_contents($json_path)); 
  $site = cfk_get_fk();

  $queries = array(); 
  $queries[] = "SET FOREIGN_KEY_CHECKS=0";
  // First iterate over existing foreign keys - and if anything is missing from the reference
  // site, then delete it.
  while(list($table, $constraints) = each($site)) {
    // If the table doesn't exist in the reference list, then continue. Different sites may have different extensions/modules
    // installed that create their own tables.
    if(!property_exists($ref, $table)) continue;
    while(list($constraint, $params) = each($constraints)) {
      $drop = FALSE;
      if(!property_exists($ref->$table, $constraint)) {
        $drop = TRUE;
      }
      else {
        // if it does exist, make sure all values are the same.
        if($params !== (array) $ref->$table->$constraint) {
          $drop = TRUE;
          // If we are dropping it, we have to remove it from the $site variable
          // so it will be re-created.
          unset($site[$table][$constraint]);
        }
      }
      if($drop) {
        $queries[] = "ALTER TABLE `$table` DROP FOREIGN KEY `$constraint`";
      }
    }
  }

  reset($site);
  reset($ref);
  while(list($table, $constraints) = each($ref)) {
    while(list($constraint, $params) = each($constraints)) {
      if(!array_key_exists($table, $site) || !array_key_exists($constraint, $site[$table])) {
        if(cfk_key_exists($table, $constraint)) {
          // We have to drop this key first.
          $queries[] = "ALTER TABLE `$table` DROP KEY `$constraint`";
        }
        // Special exception: this field seems to sometimes be set to NOT NULL
        // which causes the adding of the constraint to fail.
        if($constraint == 'FK_civicrm_financial_account_contact_id') {
          $queries[] = "ALTER TABLE `civicrm_financial_account` CHANGE COLUMN
            `contact_id` `contact_id` int(10) unsigned DEFAULT NULL COMMENT
            'FK to Contact ID that is responsible for the funds in this account'";
        }
        $queries[] = cfk_sql_for_fk($table, $constraint, $params);
      }
    }
  } 
  // We always have one query - it's the foreign keys check set to off.
  if(count($queries) == 1) {
    cfk_output("No missing foreign keys.", 'ok');
    return;
  }
  $queries[] = "SET FOREIGN_KEY_CHECKS=1";
  while(list(,$query) = each($queries)) {
    cfk_output($query, 'ok');
    if(!$dry_run) {
      if(!CRM_Core_DAO::executeQuery($query)) {
        // If we get an error - stop everything
        return;
      }
    }
  }
}

/**
 * Rebuild CiviCRM triggers.
 * 
 * Convenience function - our bash script drops triggers to
 * avoid any constraint violations or problems caused by triggers
 * being fired. This function re-builds them.
 */
function cfk_rebuild_triggers() {
  CRM_Core_DAO::triggerRebuild();    
}
