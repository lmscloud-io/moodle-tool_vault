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

use core\check\performance\debugging;

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

    /**
     * Constructor
     *
     * @param \xmldb_table $table
     */
    public function __construct(\xmldb_table $table) {
        $this->xmldbtable = $table;
    }

    /**
     * Get actual instance of xmldb_table
     *
     * @return \xmldb_table
     */
    public function get_xmldb_table() {
        return $this->xmldbtable;
    }

    /**
     * Returns SQL used to compare the field definition
     *
     * @param \xmldb_table $table
     * @param \xmldb_field $field
     * @return string
     */
    protected function get_field_sql(\xmldb_table $table, \xmldb_field $field) {
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
}
