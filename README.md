# Civi Schema Harmonizer

This script is designed to check for schema changes between your CiviCRM databases and a reference schema and alter your database so that it is inline with the reference schema.

NOTE: THIS SCRIPT DESTROYS DATA. Be sure to backup your database and carefully review the script. It might have bugs and do serious damage.

To use the script, follow these instructions:

 * Backup your database
 * Be sure your database schema matches one of the included schemas (check the schema directory for the version number of your database).
 * If your database is a different version, you will need to generate a reference tables list and reference schema with the following command (replace <dbversion> with the database version you are creating the schema for):
```
    mysql --skip-column-names -e "SHOW TABLES LIKE 'civicrm_%'" schemapiglet | \
      grep -v "civicrm_value" > schemas/<dbversion>.tables.reference.txt
    for table in $(cat schemas/<dbversion>.tables.reference.txt); do \
      mysqldump --no-data --skip-triggers --skip-comments schemapiglet "$table" | \
      sed 's/ AUTO_INCREMENT=[0-9]*//g' >> schemas/<dbversion>.create.tables.reference.txt; \
    done
```
 * Ensure the user you are running this bash script as has full access to your database via a [my.cnf](https://dev.mysql.com/doc/refman/5.1/en/option-files.html) file that specifies a valid username and password.
 * Run the following command, replacing <dbname> with the name of your database:
```
    ./schema-harmonizer <dbname>
```
 * Once you have fixed your schema, you may have orphaned records (if your schema was missing foreign key). Check the delete-orphans.php script for a function that will do that for you. 
