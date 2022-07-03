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
    protected $deftables = null;
    /** @var dbtable[] */
    protected $actualtables = null;

    /**
     * Constructor, not accessible, use load()
     */
    protected function __construct() {
    }

    /**
     * Get all actual DB tables
     *
     * @return dbtable[]
     */
    public function get_tables_actual() {
        return $this->actualtables;
    }

    /**
     * Get table definitions
     *
     * @return dbtable[]|null
     */
    public function get_tables_definitions() {
        return $this->deftables;
    }

    /**
     * Load structure
     *
     * @return static
     */
    public static function load(): self {
        $s = new self();
        $s->load_definitions();
        $s->load_actual_tables();
        return $s;
    }

    /**
     * Retrieve the list of tables defined in this moodle version and all plugins
     */
    protected function load_definitions() {
        global $CFG;
        require_once($CFG->dirroot.'/lib/adminlib.php');

        $this->deftables = [];
        $dbdirs = get_db_directories();

        foreach ($dbdirs as $dbdir) {
            $xmldbfile = new \xmldb_file($dbdir.'/install.xml');
            if ($xmldbfile->fileExists()) {
                $loaded = $xmldbfile->loadXMLStructure();
                $structure = $xmldbfile->getStructure();

                if ($loaded && ($plugintables = $structure->getTables())) {
                    foreach ($plugintables as $table) {
                        $this->deftables[strtolower($table->getName())] = new dbtable($table, $this);
                    }
                }
            }
        }

        // We ignore all comments in tables definitions.
        foreach ($this->deftables as $table) {
            $table->remove_all_comments();
        }
    }

    /**
     * Find table by name
     *
     * @param string $tablename
     * @return dbtable|null
     */
    public function find_table_definition(string $tablename): ?dbtable {
        foreach ($this->get_tables_definitions() as $table) {
            if ($table->get_xmldb_table()->getName() === $tablename) {
                return $table;
            }
        }
        return null;
    }

    /**
     * Retrieve the list of tables in the current database
     */
    protected function load_actual_tables() {
        global $DB;
        $tablesnames = $DB->get_tables();
        $this->actualtables = [];
        foreach ($tablesnames as $tablename) {
            $table = dbtable::create_from_actual_db(strtolower(trim($tablename)), $this);
            $this->actualtables[$tablename] = $table;
        }
        // Fix indexes/keys and order of elements.
        foreach ($this->get_tables_actual() as $table) {
            $table->lookup_fields();
            $table->lookup_indexes();
        }
    }

    /** @var \stdClass[] */
    protected $primarykeys = null;

    /**
     * Retrieves all primary keys defined in the current Postgres database
     *
     * @return \stdClass[]
     */
    public function retrieve_primary_keys_postgres() {
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

    /**
     * Output structure
     *
     * @param array|null $onlytables
     * @param bool $showdefinitions
     * @return string
     */
    public function output(?array $onlytables = null, bool $showdefinitions = false) {
        $o = '<?xml version="1.0" encoding="UTF-8" ?>' . "\n";
        $o .= '<XMLDB ';
        $rel = '../../../..';
        $o .= '    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'."\n";
        $o .= '    xsi:noNamespaceSchemaLocation="'.$rel.'/lib/xmldb/xmldb.xsd"'."\n";
        $o .= '>' . "\n";
        // Now the tables.
        $tables = $showdefinitions ? $this->get_tables_definitions() : $this->get_tables_actual();
        if ($tables) {
            $o .= '  <TABLES>' . "\n";
            foreach ($tables as $table) {
                if (!$onlytables || in_array($table->get_xmldb_table()->getName(), $onlytables)) {
                    $o .= $table->get_xmldb_table()->xmlOutput();
                }
            }
            $o .= '  </TABLES>' . "\n";
        }
        $o .= '</XMLDB>' . "\n";

        return $o;

    }
}
