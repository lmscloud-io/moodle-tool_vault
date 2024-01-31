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

use tool_vault\local\helpers\siteinfo;
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
    protected $deftables = [];
    /** @var dbtable[] */
    protected $actualtables = [];
    /** @var dbtable[] */
    protected $backuptables = [];

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
     * Get table definitions
     *
     * @return dbtable[]|null
     */
    public function get_backup_tables() {
        return $this->backuptables;
    }

    /**
     * Get table definitions
     *
     * @param array $tables
     */
    public function set_tables_definitions(array $tables) {
        if ((defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
            $this->deftables = $tables;
        } else {
            throw new \coding_exception('This function is for unittests only');
        }
    }

    /**
     * Load structure from actual tables and definitions in install.xml
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
     * Load structure from actual tables and definitions in the given file
     *
     * @param string $filepath path to __structure__.xml
     * @return static
     */
    public static function load_from_backup(string $filepath): self {
        $s = new self();
        $s->load_definitions();
        $s->load_actual_tables();
        $s->load_definitions_from_backup_xml($filepath);
        return $s;
    }

    /**
     * List of /db directories indexed by plugin name
     *
     * Very similar to {@see get_db_directories()} except the array has index
     *
     * @return string[]
     */
    protected static function get_db_directories() {
        global $CFG;
        $dbdirs = ['core' => $CFG->libdir.'/db'];
        $plugintypes = \core_component::get_plugin_types();
        foreach ($plugintypes as $plugintype => $pluginbasedir) {
            if ($plugins = \core_component::get_plugin_list($plugintype)) {
                foreach ($plugins as $plugin => $plugindir) {
                    $dbdirs[$plugintype.'_'.$plugin] = $plugindir.'/db';
                }
            }
        }
        return $dbdirs;
    }

    /**
     * Retrieve the list of tables defined in this moodle version and all plugins
     */
    protected function load_definitions() {
        global $CFG;
        require_once($CFG->dirroot.'/lib/adminlib.php');

        $dbdirs = self::get_db_directories();

        foreach ($dbdirs as $pluginname => $dbdir) {
            $xmldbfile = new \xmldb_file($dbdir.'/install.xml');
            if ($xmldbfile->fileExists()) {
                $loaded = $xmldbfile->loadXMLStructure();
                $structure = $xmldbfile->getStructure();

                if ($loaded && ($plugintables = $structure->getTables())) {
                    foreach ($plugintables as $table) {
                        $tablename = strtolower($table->getName());
                        $this->deftables[$tablename] = new dbtable($table, $pluginname);
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
     * Load definitions from backup file
     *
     * We do not use $xmldbfile->loadXMLStructure() because this file is not in the dirroot
     * and validation fails
     *
     * @param string $filepath
     * @return void
     */
    protected function load_definitions_from_backup_xml(string $filepath) {
        global $CFG;
        $oldxmldb = $CFG->xmldbdisablecommentchecking ?? null;
        $CFG->xmldbdisablecommentchecking = 1;
        $xmlarr = xmlize(file_get_contents($filepath));
        if (isset($xmlarr['XMLDB']['#']['TABLES']['0']['#']['TABLE'])) {
            foreach ($xmlarr['XMLDB']['#']['TABLES']['0']['#']['TABLE'] as $xmltable) {
                $name = strtolower(trim($xmltable['@']['NAME']));
                // Mdlcode-disable-next-line cannot-parse-db-tablename.
                $table = new xmldb_table($name);
                $table->arr2xmldb_table($xmltable);
                $this->backuptables[$name] = new dbtable($table, trim($xmltable['@']['COMPONENT'] ?? ''));
            }
        }
        set_config('xmldbdisablecommentchecking', $oldxmldb);
        // TODO try to match indexes/keys with the actual tables.
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
        $DB->reset_caches();
        $tablesnames = $DB->get_tables();
        foreach ($tablesnames as $tablename) {
            $deftable = $this->find_table_definition($tablename);
            $table = dbtable::create_from_actual_db(strtolower(trim($tablename)), $this);
            $this->actualtables[$tablename] = $table;
            // Fix indexes/keys and order of elements.
            $table->compare_with_other_table($deftable);
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

    /** @var array */
    protected $sequences = null;

    /**
     * Retrieve next value in all sequences
     *
     * @return array array tablename=>nextseqval
     */
    public function retrieve_sequences() {
        global $DB;
        if ($this->sequences !== null) {
            return $this->sequences;
        }
        if ($DB->get_dbfamily() === 'postgres') {
            $this->sequences = $this->retrieve_sequences_postgres();
        } else {
            $this->sequences = $this->retrieve_sequences_mysql();
        }
        return $this->sequences;
    }

    /**
     * Retrieve next value in sequences in mysql
     *
     * @return array
     */
    protected function retrieve_sequences_mysql() {
        global $DB, $CFG;
        $sequences = [];
        $prefix = $CFG->prefix;
        try {
            // Mysql caches information schema. Remember old setting, set to 0 and then reset in the end.
            $oldval = $DB->get_field_sql('select @@information_schema_stats_expiry');
            if ($oldval) {
                $DB->execute('set session information_schema_stats_expiry=0');
            }
        } catch (\Throwable $e) {
            $oldval = null;
        }
        $rs = $DB->get_recordset_sql("SHOW TABLE STATUS LIKE ?", [$prefix.'%']);

        foreach ($rs as $info) {
            $table = strtolower($info->name);
            if (strpos($table, $prefix) !== 0) {
                // Incorrect table match caused by _ .
                continue;
            }
            if (!is_null($info->auto_increment) && (int)$info->auto_increment) {
                $table = preg_replace('/^'.preg_quote($prefix, '/').'/', '', $table);
                $sequences[$table] = (int)$info->auto_increment;
            }
        }
        $rs->close();
        if ($oldval) {
            $DB->execute('set session information_schema_stats_expiry='.$oldval);
        }
        return $sequences;
    }

    /**
     * Retrieves next value in all sequences in postgres
     *
     * @return array
     */
    protected function retrieve_sequences_postgres() {
        global $DB;
        $keys = $this->retrieve_primary_keys_postgres();
        $sequences = [];
        foreach ($keys as $key) {
            if (preg_match("/^nextval\('([a-z|_]*)'::regclass\)$/", (string)$key->columndefault, $matches)) {
                $seqname = $matches[1];
                try {
                    $value = $DB->get_field_sql(
                        'SELECT CASE when is_called then last_value + 1 else last_value END FROM '.$seqname);
                    if ($value) {
                        $sequences[$key->tablename] = $value;
                    }
                } catch (\dml_exception $e) {
                    // Do nothing, just skip.
                    null;
                }
            }
        }
        return $sequences;
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
        $o .= '    xsi:noNamespaceSchemaLocation="xmldb.xsd"'."\n";
        $o .= '>' . "\n";
        // Now the tables.
        $tables = $showdefinitions ? $this->get_tables_definitions() : $this->get_tables_actual();
        if ($tables) {
            $o .= '  <TABLES>' . "\n";
            foreach ($tables as $table) {
                if (!$onlytables || in_array($table->get_xmldb_table()->getName(), $onlytables)) {
                    $o .= $table->output();
                }
            }
            $o .= '  </TABLES>' . "\n";
        }
        $o .= '</XMLDB>' . "\n";

        return $o;

    }

    /**
     * Calculate table sizes in the database
     *
     * @return array
     */
    public function get_actual_tables_sizes(): array {
        global $CFG, $DB;
        $sizes = [];
        if ($DB->get_dbfamily() === 'postgres') {
            foreach ($this->get_tables_actual() as $tablename => $table) {
                $sizes[$tablename] =
                    $DB->get_field_sql("SELECT pg_total_relation_size(?)",
                        [$CFG->prefix . $tablename]);
            }
        } else {
            $records = $DB->get_records_sql_menu("SELECT
                    table_name AS tablename,
                    data_length AS tablesize
                FROM information_schema.TABLES
                WHERE table_schema = ? AND table_name like ?",
                [$CFG->dbname, $CFG->prefix . '%']);
            foreach ($this->get_tables_actual() as $tablename => $table) {
                $sizes[$tablename] = $records[$CFG->prefix . $tablename] ?? 0;
            }
        }
        return $sizes;
    }
}
