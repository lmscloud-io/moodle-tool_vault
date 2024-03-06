<?php
// This file is part of plugin tool_vault - https://lmsvault.io
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

use tool_vault\constants;
use xmldb_field;
use xmldb_index;
use xmldb_key;
use xmldb_table;

// Mdlcode-disable cannot-parse-db-tablename.

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
    /** @var string */
    protected $component;

    /**
     * Constructor
     *
     * @param xmldb_table $table
     * @param string $component
     */
    public function __construct(xmldb_table $table, string $component) {
        $this->xmldbtable = $table;
        $this->component = $component;
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
     * @param xmldb_table $table
     * @param xmldb_field $field
     * @return string
     */
    public function get_field_sql(xmldb_table $table, xmldb_field $field) {
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
                // Create the interim index, code copied from generator::getCreateTableSQL.
                $index = new xmldb_index('anyname');
                $index->setFields($key->getFields());
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
            $sqls[] = ($index->getUnique() ? 'UNIQUE ' : '') . 'INDEX (' . $list . ')';
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
     * Check for differences with another table, fix things like order or indexes names
     *
     * @param dbtable|null $deftable model table
     * @param bool $autofix automatically fix order of fields/keys/indexes and names of keys/indexes to match $deftable
     * @return array array that may contain keys:
     *        'extratables', 'extracolumns', 'missingcolumns', 'changedcolumns', 'missingindexes', 'extraindexes'
     */
    public function compare_with_other_table(?dbtable $deftable, bool $autofix = true): array {
        if (!$deftable) {
            return [constants::DIFF_EXTRATABLES => [$this->get_xmldb_table()]];
        }
        $this->component = $deftable->component;
        $res = $this->align_fields_with_defintion($deftable, $autofix);
        $res += $this->align_keys_and_indexes_with_definition($deftable, $autofix);
        return array_filter($res);
    }

    /**
     * Fixes fields sort order, detects extra/missing/changed fields
     *
     * @param dbtable $deftable model table with the correct sort order
     * @param bool $autofix
     * @return array
     */
    protected function align_fields_with_defintion(dbtable $deftable, bool $autofix): array {
        $actualfields = $this->get_xmldb_table()->getFields();
        $deffields = $deftable->get_xmldb_table()->getFields();
        $newfields = [];
        $changedcolumns = [];
        foreach ($deffields as $i => $deffield) {
            foreach ($actualfields as $f => $afield) {
                if ($deffield->getName() === $afield->getName()) {
                    $newfields[] = $afield;
                    if ($this->get_field_sql($deftable->get_xmldb_table(), $deffield) !==
                        $this->get_field_sql($this->get_xmldb_table(), $afield)) {
                        $changedcolumns[] = $afield;
                    } else if ($autofix) {
                        $newfields[count($newfields) - 1] = $deffield;
                    }
                    unset($actualfields[$f]);
                    unset($deffields[$i]);
                }
            }
        }
        if ($autofix) {
            $this->replace_in_table(array_merge($newfields, array_values($actualfields)));
        }
        return [
            constants::DIFF_EXTRACOLUMNS => array_values($actualfields),
            constants::DIFF_MISSINGCOLUMNS => array_values($deffields),
            constants::DIFF_CHANGEDCOLUMNS => $changedcolumns,
        ];
    }

    /**
     * Fixes indexes/keys and their sortorder
     *
     * DB scanning mixes up keys and indexes, for example, the index is created from a foreign key definition
     * and also in mysql a key is created if there was a unique index.
     *
     * @param dbtable $deftable
     * @param bool $autofix
     * @return array
     */
    public function align_keys_and_indexes_with_definition(dbtable $deftable, bool $autofix): array {
        $defobjs = array_merge($deftable->get_xmldb_table()->getKeys(), $deftable->get_xmldb_table()->getIndexes());
        $actualobjs = array_merge($this->get_xmldb_table()->getKeys(), $this->get_xmldb_table()->getIndexes());
        $matchingobjs = [];

        foreach ($defobjs as $k => $defobj) {
            foreach ($actualobjs as $i => $key) {
                if ($this->compare_key_or_index($key, $defobj)) {
                    $matchingobjs[] = $defobj;
                    unset($defobjs[$k]);
                    unset($actualobjs[$i]);
                    continue 2;
                }
            }
        }

        // Remove duplicates from actualkeys.
        foreach ($actualobjs as $k => $actualobj) {
            foreach ($matchingobjs as $obj) {
                if ($this->compare_key_or_index($obj, $actualobj)) {
                    unset($actualobjs[$k]);
                    continue 2;
                }
            }
        }

        // Remove duplicates from remaining defkeys (keys and indexes that were not found in this table).
        foreach ($defobjs as $k => $defobj) {
            foreach ($matchingobjs as $obj) {
                if ($this->compare_key_or_index($obj, $defobj)) {
                    unset($defobjs[$k]);
                    continue 2;
                }
            }
        }

        if ($autofix) {
            $this->replace_in_table(null, array_merge($matchingobjs, array_values($actualobjs)));
        }

        return [
            constants::DIFF_EXTRAINDEXES => array_values($actualobjs),
            constants::DIFF_MISSINGINDEXES => array_values($defobjs),
        ];
    }

    /**
     * Returns if two objects (xmldb_index/xmldb_key) are identical
     *
     * Compares the list of fields and uniqueness. Moodle does not have foreign keys, it creates
     * indexes instead.
     *
     * @param \xmldb_object $o1
     * @param \xmldb_object $o2
     * @return bool true if matches, false if not
     */
    protected function compare_key_or_index(\xmldb_object $o1, \xmldb_object $o2): bool {
        if (!in_array(get_class($o1), [xmldb_index::class, xmldb_key::class]) ||
            !in_array(get_class($o2), [xmldb_index::class, xmldb_key::class])) {
            throw new \coding_exception('Each argument must be either xmldb_index or xmldb_key');
        }
        $f1 = join(',', $o1->getFields());
        $f2 = join(',', $o2->getFields());
        if ($f1 !== $f2) {
            return false;
        }
        $isunique = function (\xmldb_object $o) {
            return ($o instanceof xmldb_key && (
                        $o->getType() == XMLDB_KEY_FOREIGN_UNIQUE || $o->getType() == XMLDB_KEY_UNIQUE
                    )) ||
                ($o instanceof xmldb_index && $o->getUnique());
        };
        if ($isunique($o1) != $isunique($o2)) {
            return false;
        }
        $isprimary = function (\xmldb_object $o) {
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
     * @param \xmldb_field[]|null $fields
     * @param \xmldb_object[]|null $keysandindexes
     * @return void
     */
    protected function replace_in_table(?array $fields = null, ?array $keysandindexes = null) {
        if ($fields === null) {
            $fields = $this->get_xmldb_table()->getFields();
        }
        if ($keysandindexes === null) {
            $keysandindexes = array_merge($this->get_xmldb_table()->getKeys(), $this->get_xmldb_table()->getIndexes());
        }
        $table = new xmldb_table($this->get_xmldb_table()->getName());
        foreach ($fields as $field) {
            $field->setPrevious(null);
            $field->setNext(null);
            $table->addField($field);
        }
        foreach ($keysandindexes as $obj) {
            try {
                $obj->setPrevious(null);
                $obj->setNext(null);
                if ($obj instanceof xmldb_key) {
                    $table->addKey($obj);
                } else if ($obj instanceof xmldb_index) {
                    $table->addIndex($obj);
                }
            } catch (\Throwable $e) {
                continue;
            }
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
        $xmldbtable = new xmldb_table($tablename);
        // Get fields info from ADODb.
        $dbfields = $DB->get_columns($tablename);
        if ($dbfields) {
            foreach ($dbfields as $dbfield) {
                $deftable = $structure->find_table_definition($tablename);
                $xmldbtable->addField(database_column_info::clone_from($dbfield)->to_xmldb_field($deftable));
            }
        }
        $table = new self($xmldbtable, '?');
        if ($DB->get_dbfamily() === 'postgres') {
            $table->retrieve_keys_and_indexes_postgres($structure);
        } else {
            $table->retrieve_keys_and_indexes_mysql();
        }
        return $table;
    }

    /**
     * Retrieves all keys and indexes defined in the current MySQL database
     *
     * This is a copy of {@see mysqli_native_moodle_database::get_indexes()} however
     * we do not want to ignore primary keys here
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
                if (specialrules::is_actual_index_ignored($this->get_xmldb_table(), $indexname, $dbindex)) {
                    continue;
                }
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
     * @param dbstructure $structure
     */
    protected function retrieve_keys_and_indexes_postgres(dbstructure $structure) {
        global $DB;
        $table = $this->get_xmldb_table();
        $tableparam = $table->getName();
        // Get PK, UK and indexes info from ADODb.
        $dbindexes = $DB->get_indexes($tableparam);
        if ($dbindexes) {
            foreach ($dbindexes as $indexname => $dbindex) {
                if (specialrules::is_actual_index_ignored($this->get_xmldb_table(), $indexname, $dbindex)) {
                    continue;
                }
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

        if ($primarykey = $structure->retrieve_primary_keys_postgres()[$tableparam] ?? null) {
            $table->addKey(new xmldb_key($primarykey->keyname, XMLDB_KEY_PRIMARY, [$primarykey->columnname]));
        }
    }

    /**
     * Return list of queries that will transform original table into this table
     *
     * @param dbtable|null $originaltable
     * @return array
     */
    public function get_alter_sql(?dbtable $originaltable): array {
        global $DB;
        $generator = $DB->get_manager()->generator;
        if (!$originaltable) {
            // Table does not exist, return SQL to create table.
            return $generator->getCreateTableSQL($this->get_xmldb_table());
        }
        $diff = $this->compare_with_other_table($originaltable);
        if (!$diff) {
            // Table is identical to the originaltable.
            return [];
        }
        if (!array_diff_key($diff, [constants::DIFF_EXTRAINDEXES, constants::DIFF_EXTRACOLUMNS])) {
            // Table only has extra fields and/or indexes - return queries to add fields/indexes.
            // TODO dropping extra indexes should be a straightforward operation too.
            $res = [];
            foreach ($diff[constants::DIFF_EXTRACOLUMNS] ?? [] as $field) {
                $res = array_merge($res, $generator->getAddFieldSQL($this->get_xmldb_table(), $field));
            }
            foreach ($diff[constants::DIFF_EXTRAINDEXES] ?? [] as $obj) {
                if ($obj instanceof xmldb_key) {
                    $res = array_merge($res, $generator->getAddKeySQL($this->get_xmldb_table(), $obj));
                } else if ($obj instanceof xmldb_index) {
                    $res = array_merge($res, $generator->getAddIndexSQL($this->get_xmldb_table(), $obj));
                }
            }
            return $res;
        }
        // Anything that is more difficult than that - drop and create the table.
        // We may not be able to simply delete columns or change their types because there might be indexes on
        // such columns.
        return array_merge(
            $generator->getDropTableSQL($originaltable->get_xmldb_table()),
            $generator->getCreateTableSQL($this->get_xmldb_table()),
        );
    }

    /**
     * Returns the sequence field in the table (usually 'id') or null if table does not have sequence
     *
     * @return string
     */
    protected function get_sequence(): ?string {
        foreach ($this->get_xmldb_table()->getFields() as $field) {
            if ($field->getSequence()) {
                return $field->getName();
            }
        }
        return null;
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
        if (!$field = $this->get_sequence()) {
            return [];
        }
        $tablename = $this->get_xmldb_table()->getName();
        $maxid = $DB->get_field_sql("SELECT MAX($field) FROM {" . $tablename . "}");
        if (!$maxid && !$nextvalue) {
            return [];
        }
        $nextid = max($nextvalue, $maxid + 1);

        if ($DB->get_dbfamily() === 'postgres') {
            return ["ALTER SEQUENCE {$CFG->prefix}{$tablename}_{$field}_seq RESTART WITH $nextid"];
        } else {
            return ["ALTER TABLE {$CFG->prefix}{$tablename} AUTO_INCREMENT = $nextid"];
        }
    }

    /**
     * Validate definitions
     *
     * @return array
     */
    public function validate_definition(): array {
        $result = [];
        $error = $this->get_xmldb_table()->validateDefinition();
        if ($error !== null) {
            $result[] = $error;
        }
        $objs = array_merge(
            $this->get_xmldb_table()->getFields(),
            $this->get_xmldb_table()->getKeys(),
            $this->get_xmldb_table()->getIndexes(),
        );
        foreach ($objs as $obj) {
            $error = $obj->validateDefinition($this->get_xmldb_table());
            if ($error !== null) {
                $result[] = $error;
            }
            if ($obj instanceof xmldb_field) {
                // Extra checks that are present in check_database_schema() but absent in validateDefinition().
                if (empty($obj->getType()) || $obj->getType() == XMLDB_TYPE_DATETIME || $obj->getType() == XMLDB_TYPE_TIMESTAMP) {
                    $result[] = 'Invalid field definition in table {'.$this->get_xmldb_table()->getName().'}: field "'.
                        $obj->getName().'" has unsupported type';
                }
            }
        }

        return $result;
    }

    /**
     * Output structure
     *
     * @return string
     */
    public function output() {
        $component = "COMPONENT=\"".htmlspecialchars($this->component)."\"";
        $res = $this->get_xmldb_table()->xmlOutput();
        $res = preg_replace('|( *<TABLE NAME="[^"]*")|', '$1'.' '.$component, $res);
        return $res;
    }

    /**
     * Return the name of the plugin or core component definiting this table
     *
     * @return void
     */
    public function get_component(): string {
        return $this->component;
    }
}
