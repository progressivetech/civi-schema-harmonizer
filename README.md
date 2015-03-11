# Civi Schema Harmonizer

Your database schema defines the tables and fields used to store your data.

In addition, it indicates which fields should be considered keys (which helps make your searches more efficient) and which fields should be foreign keys - which ensures that your data is consistent (for example, you don't have an email address record for a contact that has been deleted).

For more information on the CiviCRM schema and keeping it up to date, please see the [CiviCRM schema wiki page](http://wiki.civicrm.org/confluence/display/CRMDOC/Ensuring+Schema+Integrity+on+Upgrades).

Ensuring your schema is accurate is important - particularly the foreign keys since that information is used by CiviCRM when merging records. If your schema is not accurate, you could end up with data loss.

This script is designed to check for schema changes between your CiviCRM databases and a reference schema and alter your database so that it is inline with the reference schema.

There are two approaches you can take.

The least destructive is to only compare foreign keys and make sure all required foreign key constraints are in place.

If you are using Drupal and have drush installed, you can use this approach.

 * Ensure you have a .drush directory in your home directory: `mkdir -p ~/drush`
 * Change into your web directory
 * Backup your database: `drush civicrm-sql-dump ~/civicrm.backup.sql`
 * Check for changes that would be made: `drush cfk-show`
 * Make the changes: `drush cfk-fix`
 * Check for orphaned records: `drush cfk-orphans-show`
 * Fix orphaned records: `drush cfk-orphans-fix`

See below for a more destructive but complete approach.

NOTE: THIS SCRIPT DESTROYS DATA. Be sure to backup your database and carefully review the script. It might have bugs and do serious damage.

To use the script, follow these instructions:

 * Backup your database
 * Be sure your database schema matches one of the included schemas (check the schema directory for the version number of your database).
 * If your database is a different version, you will need to generate a reference tables list and reference schema with the following command (replace <dbversion> with the database version you are creating the schema for and <dbname> with the name of the database):
```
    mysql --skip-column-names -e "SHOW TABLES LIKE 'civicrm_%'" <dbname> | \
      grep -v "civicrm_value" > schemas/<dbversion>.tables.reference.txt
    for table in $(cat schemas/<dbversion>.tables.reference.txt); do \
      mysqldump --no-data --skip-triggers --skip-comments <dbname> "$table" | \
      sed 's/ AUTO_INCREMENT=[0-9]*//g' >> schemas/<dbversion>.create.tables.reference.txt; \
    done
```
 * Ensure the user you are running this bash script as has full access to your database via a [my.cnf](https://dev.mysql.com/doc/refman/5.1/en/option-files.html) file that specifies a valid username and password.
 * Run the following command, replacing <dbname> with the name of your database:
```
    ./schema-harmonizer <dbname>
```
 * Once you have fixed your schema, you may have orphaned records (if your schema was missing foreign keys). If you are using drush, you can use the included scripts and drush `drush cfk-orphans-show` to see what will be done followed by `drush cfk-orphans-fix` to fix them. In addition, this scripts will drop all triggers in your database. You can rebuild them by disabling and then re-enabling an extension or by running the drush command: `drush cfk-rebuild-triggers`.
