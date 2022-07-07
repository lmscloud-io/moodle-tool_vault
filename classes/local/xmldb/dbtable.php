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

use xmldb_index;
use xmldb_key;
use xmldb_table;

/**
 * Stores information about one DB table - either from definitions or from actual db
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dbtable {
    /** @var \xmldb_table */
    protected $xmldbtable;
    /** @var dbstructure */
    protected $structure;

    /**
     * Constructor
     *
     * @param \xmldb_table $table
     * @param dbstructure $structure
     */
    public function __construct(\xmldb_table $table, dbstructure $structure) {
        $this->xmldbtable = $table;
        $this->structure = $structure;
    }

    /**
     * Get actual instance of xmldb_table
     *
     * @return \xmldb_table
     */
    public function get_xmldb_table(): xmldb_table {
        return $this->xmldbtable;
    }

    /**
     * Returns SQL used to compare the field definition
     *
     * @param \xmldb_table $table
     * @param \xmldb_field $field
     * @return string
     */
    public function get_field_sql(\xmldb_table $table, \xmldb_field $field) {
        global $DB;
        if ($error = $field->validateDefinition($table)) {
            // TODO do something here, otherwise getFieldSQL throws an exception.
            \debugging($error, DEBUG_DEVELOPER);
        }
        $sql = $DB->get_manager()->generator->getFieldSQL($table, $field);
        if ($DB->get_dbfamily() === 'mysql') {
            $sql = preg_replace('/\b(BIGINT|TINYINT|SMALLINT|MEDIUMINT|INT)\(\d+\)/', 'INT', $sql);
            $sql = preg_replace('/\bDOUBLE\(\d+, \d+\)/', 'DOUBLE', $sql);
            $sql = preg_replace('/ DEFAULT ([\d]+)\.0+$/', ' DEFAULT \\1', $sql);
            $sql = preg_replace('/ DEFAULT ([\d]+\.[\d]*[1-9])0+$/', ' DEFAULT \\1', $sql);
        }
        return $sql;
    }

    /**
     * Returns array of strings that can be used to compare with another table
     *
     * The strings contain SQL commands to create fields, keys and indexes in the current db dialect
     * This is used to compare table definition from XML file with the actual table
     *
     * For example, the table definition may say INT(10) but postgres actually defines it as INT(18)
     * This happens because SQL command for both is BIGINT.
     *
     * Another example - foreign keys from the definition are actually just indexes
     *
     * This function uses the same way of forming SQL as the actual db generator in Moodle (sometimes
     * even by calling the same functions).
     *
     * @return array
     */
    public function get_sqls_for_comparison() {
        global $DB;
        $sqls = [];
        $table = $this->xmldbtable;
        foreach ($table->getFields() as $field) {
            $sqls[] = 'FIELD ' . $this->get_field_sql($table, $field);
        }
        $indexes = $table->getIndexes();
        $gen = $DB->get_manager()->generator;
        foreach ($table->getKeys() as $key) {
            if ($key->getType() == XMLDB_KEY_PRIMARY) {
                $sqls[] = 'PRIMARY KEY (' . implode(', ', $gen->getEncQuoted($key->getFields())) . ')';
            } else {
                // Create the interim index, copied from generator::getCreateTableSQL.
                $index = new \xmldb_index('anyname');
                $index->setFields($key->getFields());
                // Tables do not exist yet, which means indexed can not exist yet.
                switch ($key->getType()) {
                    case XMLDB_KEY_UNIQUE:
                    case XMLDB_KEY_FOREIGN_UNIQUE:
                        $index->setUnique(true);
                        $indexes[] = $index;
                        break;
                    case XMLDB_KEY_FOREIGN:
                        $index->setUnique(false);
                        $indexes[] = $index;
                        break;
                }
            }
        }
        foreach ($indexes as $index) {
            $list = implode(', ', $gen->getEncQuoted($index->getFields()));
            $sqls[] = ($index->getUnique() ? 'UNIQUE ' : '').'INDEX ('.$list.')';
        }
        sort($sqls, SORT_STRING);
        return array_values(array_unique($sqls));
    }

    /**
     * Removes all comments from the table, fields, indexes and keys
     *
     * @return void
     */
    public function remove_all_comments() {
        $this->get_xmldb_table()->setComment(null);
        foreach ($this->get_xmldb_table()->getFields() as $field) {
            $field->setComment(null);
        }
        foreach ($this->get_xmldb_table()->getIndexes() as $index) {
            $index->setComment(null);
            $index->setHints([]);
        }
        foreach ($this->get_xmldb_table()->getKeys() as $key) {
            $key->setComment(null);
        }
    }

    /**
     * Fixes fields sort order
     */
    public function lookup_fields() {
        if (!$deftable = $this->structure->find_table_definition($this->get_xmldb_table()->getName())) {
            return;
        }
        $actualfields = $this->get_xmldb_table()->getFields();
        $deffields = $deftable->get_xmldb_table()->getFields();
        $newfields = [];
        // Fix fields order.
        foreach ($deffields as $i => $deffield) {
            foreach ($actualfields as $f => $afield) {
                if ($deffield->getName() === $afield->getName()) {
                    $newfields[] = $afield;
                    unset($actualfields[$f]);
                    unset($deffields[$i]);
                }
            }
        }
        $this->replace_in_table(array_merge($newfields, array_values($actualfields)));
    }

    /**
     * Fixes indexes/keys and their sortorder
     *
     * DB scanning mixes up keys and indexes, for example, the index is created from a foreign key definition
     * and also in mysql a key is created if there was a unique index.
     */
    public function lookup_indexes() {
        if (!$deftable = $this->structure->find_table_definition($this->get_xmldb_table()->getName())) {
            return;
        }
        $defindexes = $deftable->get_xmldb_table()->getIndexes();
        $defkeys = $deftable->get_xmldb_table()->getKeys();
        $actualindexes = $this->get_xmldb_table()->getIndexes();
        $actualkeys = $this->get_xmldb_table()->getKeys();
        $newkeys = [];
        $newindexes = [];

        foreach ($defkeys as $k => $defkey) {
            foreach ($actualkeys as $i => $key) {
                if ($this->compare_keys_and_indexes($key, $defkey)) {
                    $newkeys[] = $defkey;
                    unset($defkeys[$k]);
                    unset($actualkeys[$i]);
                    continue 2;
                }
            }
            foreach ($actualindexes as $i => $index) {
                if ($this->compare_keys_and_indexes($index, $defkey)) {
                    $newkeys[] = $defkey;
                    unset($defkeys[$k]);
                    unset($actualindexes[$i]);
                    continue 2;
                }
            }
            // TODO def key not found.
        }

        foreach ($defindexes as $k => $defindex) {
            foreach ($actualindexes as $i => $index) {
                if ($this->compare_keys_and_indexes($index, $defindex)) {
                    $newindexes[] = $defindex;
                    unset($defindexes[$k]);
                    unset($actualindexes[$i]);
                    continue 2;
                }
            }
            foreach ($actualkeys as $i => $key) {
                if ($this->compare_keys_and_indexes($key, $defindex)) {
                    $newindexes[] = $defindex;
                    unset($defindexes[$k]);
                    unset($actualkeys[$i]);
                    continue 2;
                }
            }
            // TODO def index not found.
        }

        // Remove duplicates.
        foreach ($actualkeys as $k => $actualkey) {
            foreach (array_merge($newkeys, $newindexes) as $obj) {
                if ($this->compare_keys_and_indexes($obj, $actualkey)) {
                    unset($actualkeys[$k]);
                    continue 2;
                }
            }
        }
        foreach ($actualindexes as $k => $actualindex) {
            foreach (array_merge($newkeys, $newindexes) as $obj) {
                if ($this->compare_keys_and_indexes($obj, $actualindex)) {
                    unset($actualindexes[$k]);
                    continue 2;
                }
            }
        }

        $this->replace_in_table(null,
            array_merge($newkeys, array_values($actualkeys)),
            array_merge($newindexes, array_values($actualindexes)));
    }

    /**
     * Returns if two objects (xmldb_index/xmldb_key) are identical
     *
     * Compares the list of fields and uniqueness. Moodle does not have foreign keys, it creates
     * indexes instead.
     *
     * @param \xmldb_object $o1
     * @param \xmldb_object $o2
     * @return bool
     */
    protected function compare_keys_and_indexes(\xmldb_object $o1, \xmldb_object $o2): bool {
        if (!in_array(get_class($o1), [xmldb_index::class, xmldb_key::class]) ||
            !in_array(get_class($o2), [xmldb_index::class, xmldb_key::class])) {
            throw new \coding_exception('Each argument must be either xmldb_index or xmldb_key');
        }
        $f1 = join(',', $o1->getFields());
        $f2 = join(',', $o1->getFields());
        if ($f1 !== $f2) {
            return false;
        }
        $isunique = function(\xmldb_object $o) {
            return ($o instanceof xmldb_key && (
                $o->getType() == XMLDB_KEY_FOREIGN_UNIQUE || $o->getType() == XMLDB_KEY_UNIQUE
                )) ||
                ($o instanceof xmldb_index && $o->getUnique());
        };
        if ($isunique($o1) != $isunique($o2)) {
            return false;
        }
        $isprimary = function(\xmldb_object $o) {
            return $o instanceof xmldb_key && $o->getType() == XMLDB_KEY_PRIMARY;
        };
        if ($isprimary($o1) != $isprimary($o2)) {
            return false;
        }

        return true;
    }

    /**
     * Replace fields, keys and/or indexes in this table.
     *
     * @param \xmldb_field[] $fields
     * @param \xmldb_key[] $keys
     * @param \xmldb_index[] $indexes
     * @return void
     */
    protected function replace_in_table(?array $fields = null, ?array $keys = null, ?array $indexes = null) {
        if ($fields === null) {
            $fields = $this->get_xmldb_table()->getFields();
        }
        if ($keys === null) {
            $keys = $this->get_xmldb_table()->getKeys();
        }
        if ($indexes === null) {
            $indexes = $this->get_xmldb_table()->getIndexes();
        }
        $table = new \xmldb_table($this->get_xmldb_table()->getName());
        foreach ($fields as $field) {
            $field->setPrevious(null);
            $field->setNext(null);
            $table->addField($field);
        }
        foreach ($keys as $key) {
            $key->setPrevious(null);
            $key->setNext(null);
            $table->addKey($key);
        }
        foreach ($indexes as $index) {
            $index->setPrevious(null);
            $index->setNext(null);
            $table->addIndex($index);
        }
        $this->xmldbtable = $table;
    }

    /**
     * Loads the table from the actual table in the db
     *
     * @param string $tablename
     * @param dbstructure $structure
     * @return static
     */
    public static function create_from_actual_db(string $tablename, dbstructure $structure): self {
        global $DB;
        $xmldbtable = new \xmldb_table($tablename);
        // Get fields info from ADODb.
        $dbfields = $DB->get_columns($tablename);
        if ($dbfields) {
            foreach ($dbfields as $dbfield) {
                $deftable = $structure->find_table_definition($tablename);
                $xmldbtable->addField(database_column_info::clone_from($dbfield)->to_xmldb_field($deftable));
            }
        }
        $table = new self($xmldbtable, $structure);
        if ($DB->get_dbfamily() === 'postgres') {
            $table->retrieve_keys_and_indexes_postgres();
        } else {
            $table->retrieve_keys_and_indexes_mysql();
        }
        return $table;
    }

    /**
     * Retrieves all keys and indexes defined in the current MySQL database
     */
    protected function retrieve_keys_and_indexes_mysql() {
        global $DB, $CFG;
        $table = $this->get_xmldb_table();
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
     */
    protected function retrieve_keys_and_indexes_postgres() {
        global $DB;
        $table = $this->get_xmldb_table();
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

        if ($primarykey = $this->structure->retrieve_primary_keys_postgres()[$tableparam] ?? null) {
            $table->addKey(new xmldb_key($primarykey->keyname, XMLDB_KEY_PRIMARY, [$primarykey->columnname]));
        }
    }

    public function get_alter_sql(?dbtable $originaltable): array {
        // TODO return list of queries that will transform original table into this table.
        return [];
    }

    protected function has_sequence(): bool {
        foreach ($this->get_xmldb_table()->getFields() as $field) {
            if ($field->getSequence()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Fix sequence on db table
     *
     * for postgres: https://stackoverflow.com/questions/244243/how-to-reset-postgres-primary-key-sequence-when-it-falls-out-of-sync
     *
     * @param int $nextvalue
     * @return array|string[]
     */
    public function get_fix_sequence_sql(int $nextvalue): array {
        global $DB, $CFG;
        if (!$this->has_sequence()) {
            return [];
        }
        $tablename = $this->get_xmldb_table()->getName();
        $maxid = $DB->get_field_sql("SELECT MAX(id) FROM {".$tablename."}");
        if (!$maxid && !$nextvalue) {
            return [];
        }
        $nextid = max($nextvalue, $maxid + 1);

        if ($DB->get_dbfamily() === 'postgres') {
            return ["ALTER SEQUENCE {$CFG->prefix}{$tablename}_id_seq RESTART WITH $nextid"];
        } else {
            return ["ALTER TABLE {$CFG->prefix}{$tablename} AUTO_INCREMENT = $nextid"];
        }
    }
}
