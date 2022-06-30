<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_vault\local\xmldb;

use xmldb_field;
use xmldb_index;
use xmldb_key;
use xmldb_table;

/**
 * Stores DB structure - list of tables and sequences. Can be created either from definitions or from actual db
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dbstructure {

    /** @var dbtable[] */
    protected $alltables = null;
    /** @var bool */
    protected $isdefinitions = false;

    /**
     * Get all DB tables
     *
     * @return dbtable[]
     */
    public function get_tables() {
        return $this->alltables;
    }

    /**
     * Retrieve the list of tables defined in this moodle version and all plugins
     *
     * @return self
     */
    public static function create_from_tables_definitions() {
        global $CFG;
        require_once($CFG->dirroot.'/lib/adminlib.php');
        $s = new self();
        $s->isdefinitions = true;

        $s->alltables = [];
        $dbdirs = get_db_directories();

        foreach ($dbdirs as $dbdir) {
            $xmldbfile = new \xmldb_file($dbdir.'/install.xml');
            if ($xmldbfile->fileExists()) {
                $loaded = $xmldbfile->loadXMLStructure();
                $structure = $xmldbfile->getStructure();

                if ($loaded && ($plugintables = $structure->getTables())) {
                    foreach ($plugintables as $table) {
                        $s->alltables[strtolower($table->getName())] = new dbtable($table);
                    }
                }
            }
        }

        return $s;
    }

    /**
     * Retrieve the list of tables in the current database
     *
     * @return self
     */
    public static function create_from_actual_db() {
        global $DB;
        $s = new self();
        $tablesnames = $DB->get_tables();
        $s->alltables = [];
        foreach ($tablesnames as $tablename) {
            $s->alltables[$tablename] = $s->load_actual_table($tablename);
        }
        return $s;
    }

    /**
     * Load a table from the current database
     *
     * @param string $tablename
     * @return dbtable
     */
    protected function load_actual_table(string $tablename): dbtable {
        global $DB;

        // Create one new xmldb_table.
        $table = new xmldb_table(strtolower(trim($tablename)));
        // Get fields info from ADODb.
        $dbfields = $DB->get_columns($tablename);
        if ($dbfields) {
            foreach ($dbfields as $dbfield) {
                // Wrap the field definition in our class to fix some problems with it.
                $dbfield = database_column_info::clone_from($dbfield);
                // Create new XMLDB field.
                $field = new xmldb_field($dbfield->name);
                // Set field with info retrofitted.
                $field->setFromADOField($dbfield);
                // Add field to the table.
                $table->addField($field);
            }
        }
        if ($DB->get_dbfamily() === 'postgres') {
            self::retrieve_keys_and_indexes_postgres($table);
        } else {
            self::retrieve_keys_and_indexes_mysql($table);
        }
        return new dbtable($table);
    }

    /**
     * Retrieves all keys and indexes defined in the current MySQL database
     *
     * @param xmldb_table $table
     * @return void
     */
    protected function retrieve_keys_and_indexes_mysql(xmldb_table $table) {
        global $DB, $CFG;
        $tableparam = $table->getName();
        // Get PK, UK and indexes info from ADODb.
        $result = $DB->get_recordset_sql('SHOW INDEXES FROM '.$CFG->prefix.$tableparam);
        $dbindexes = [];
        foreach ($result as $res) {
            if (!isset($dbindexes[$res->key_name])) {
                $dbindexes[$res->key_name] = ['unique' => empty($res->non_unique), 'columns' => []];
            }
            $dbindexes[$res->key_name]['columns'][$res->seq_in_index - 1] = $res->column_name;
        }
        $result->close();
        if ($dbindexes) {
            foreach ($dbindexes as $indexname => $dbindex) {
                // Add the indexname to the array.
                $dbindex['name'] = $indexname;
                // We are handling one xmldb_key (primaries + uniques).
                if ($dbindex['unique']) {
                    $key = new xmldb_key(strtolower($dbindex['name']));
                    // Set key with info retrofitted.
                    $key->setFromADOKey($dbindex);
                    // Add key to the table.
                    $table->addKey($key);

                    // We are handling one xmldb_index (non-uniques).
                } else {
                    $index = new xmldb_index(strtolower($dbindex['name']));
                    // Set index with info retrofitted.
                    $index->setFromADOIndex($dbindex);
                    // Add index to the table.
                    $table->addIndex($index);
                }
            }
        }
    }

    /**
     * Retrieves all keys and indexes defined in the current Postgres database
     *
     * @param xmldb_table $table
     * @return void
     */
    private function retrieve_keys_and_indexes_postgres(xmldb_table $table) {
        global $DB;
        $tableparam = $table->getName();
        // Get PK, UK and indexes info from ADODb.
        $dbindexes = $DB->get_indexes($tableparam);
        if ($dbindexes) {
            foreach ($dbindexes as $indexname => $dbindex) {
                // Add the indexname to the array.
                $dbindex['name'] = $indexname;
                $index = new xmldb_index(strtolower($dbindex['name']));
                // Set index with info retrofitted.
                $index->setFromADOIndex($dbindex);
                $index->setUnique((bool)$dbindex['unique']);
                // Add index to the table.
                $table->addIndex($index);
            }
        }

        if ($primarykey = static::retrieve_primary_keys_postgres()[$tableparam] ?? null) {
            $table->addKey(new xmldb_key($primarykey->keyname, XMLDB_KEY_PRIMARY, [$primarykey->columnname]));
        }
    }

    /** @var \stdClass[] */
    protected $primarykeys = null;

    /**
     * Retrieves all primary keys defined in the current Postgres database
     *
     * @return \stdClass[]
     */
    protected function retrieve_primary_keys_postgres() {
        global $DB, $CFG;
        if ($this->primarykeys !== null) {
            return $this->primarykeys;
        }
        $sql = "SELECT tc.table_name AS tablename, tc.constraint_name AS keyname, c.column_name AS columnname,
                    c.data_type AS type, column_default AS columndefault
            FROM information_schema.table_constraints tc
                     JOIN information_schema.constraint_column_usage AS ccu USING (constraint_schema, constraint_name)
                     JOIN information_schema.columns AS c ON c.table_schema = tc.constraint_schema
                AND tc.table_name = c.table_name AND ccu.column_name = c.column_name
            WHERE constraint_type = 'PRIMARY KEY' and tc.table_name LIKE :tableprefix";
        $rs = $DB->get_recordset_sql($sql, ['tableprefix' => $CFG->prefix.'%']);
        $this->primarykeys = [];
        foreach ($rs as $record) {
            $record->keyname = substr($record->keyname, strlen($CFG->prefix));
            $record->tablename = substr($record->tablename, strlen($CFG->prefix));
            $this->primarykeys[$record->tablename] = $record;
        }
        $rs->close();
        return $this->primarykeys;
    }

    /**
     * Retrieves last value in all sequences in postgres
     *
     * @return array
     */
    protected function retrieve_sequences_postgres() {
        // TODO: SELECT (CASE WHEN is_called THEN last_value ELSE 0 END ) FROM mdl_assign_grades_id_seq .
        return [];
    }
}
