<?php

/**
 * Delete orphaned records.
 * 
 * This function is designed to find and delete orphaned records that
 * may have been created while your database did not have properly configured
 * foreign keys installed.
 *
 * If the foreign key specifies CASCADE when a record is deleted, then the
 * orphaned record will be deleted. Otherwise, the field will be set to NULL.
 *
 * If you are using drush, you can execute with:
 * drush php-eval 'require("/path/to/delete-orphans.php"; delete_orphans();'
 *
 * WARNING: if you have large tables (like > 500,000) and you have to delete 
 * large numbers of records (like > 10,000) this script may take many hours
 * to complete and may peg your MySQL server at 100% of CPU time during that
 * period.
 */
function delete_orphans() {
  if(function_exists('_civicrm_init')) {
    _civicrm_init();
  }
  else {
    civicrm_initialize();
  }
  $sql = "SELECT kcu.`TABLE_NAME`, kcu.`COLUMN_NAME`, kcu.`REFERENCED_TABLE_NAME`, kcu.`REFERENCED_COLUMN_NAME`, rc.`DELETE_RULE` FROM information_schema.KEY_COLUMN_USAGE kcu LEFT JOIN
    INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc ON
    kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME WHERE kcu.REFERENCED_TABLE_SCHEMA IS NOT NULL";
  $dao = CRM_Core_DAO::executeQuery($sql);
  while($dao->fetch()) {
    if($dao->TABLE_NAME == $dao->REFERENCED_TABLE_NAME) {
      $find_sql = "SELECT t1.id FROM $dao->TABLE_NAME t1 LEFT JOIN $dao->REFERENCED_TABLE_NAME t2 ON (t1.$dao->COLUMN_NAME = t2.$dao->REFERENCED_COLUMN_NAME) WHERE t1.$dao->COLUMN_NAME IS NOT NULL AND t2.$dao->REFERENCED_COLUMN_NAME IS NULL";
    }
    else {
      $find_sql = "SELECT $dao->TABLE_NAME.id FROM $dao->TABLE_NAME LEFT JOIN $dao->REFERENCED_TABLE_NAME ON ($dao->TABLE_NAME.$dao->COLUMN_NAME=$dao->REFERENCED_TABLE_NAME.$dao->REFERENCED_COLUMN_NAME) WHERE $dao->TABLE_NAME.$dao->COLUMN_NAME IS NOT NULL AND $dao->REFERENCED_TABLE_NAME.$dao->REFERENCED_COLUMN_NAME IS NULL";
    }
    $fk_dao = CRM_Core_DAO::executeQuery($find_sql);
    if($fk_dao->N > 0) {
      echo  "Found " . $fk_dao->N . " Invalid Foreign Keys in table $dao->TABLE_NAME ($dao->REFERENCED_TABLE_NAME): " . $dao->DELETE_RULE . "\n";
      $sql = "CREATE TEMPORARY TABLE cruft (id int)";
      CRM_Core_DAO::executeQuery($sql);
      $sql = "INSERT INTO cruft $find_sql";
      CRM_Core_DAO::executeQuery($sql);
      if($dao->DELETE_RULE == 'CASCADE') {
        $sql = "DELETE FROM $dao->TABLE_NAME WHERE id IN (SELECT id FROM cruft)";
      }
      else {
        $sql = "UPDATE $dao->TABLE_NAME SET $dao->COLUMN_NAME = NULL WHERE id IN (SELECT id FROM cruft)";
      }
      CRM_Core_DAO::executeQuery($sql);
      $sql = "DROP TABLE cruft";
      CRM_Core_DAO::executeQuery($sql);
    }
  }
}
