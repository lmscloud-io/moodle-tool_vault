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

/**
 * Wrapper for database_column_info fixing some problems with it
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class database_column_info extends \database_column_info {

    /**
     * Creates an instance of this class from \database_column_info
     *
     * @param  \database_column_info $obj
     * @return database_column_info
     */
    public static function clone_from(\database_column_info $obj): self {
        return new static((object)$obj->data);
    }

    /**
     * Create xmldb_field from this object
     *
     * {@see \pgsql_native_moodle_database::fetch_columns()}
     * {@see \mysqli_native_moodle_database::fetch_columns()}
     *
     * @param dbtable|null $deftable
     * @return \xmldb_field
     */
    public function to_xmldb_field(?dbtable $deftable): \xmldb_field {
        global $DB;

        if ($DB->get_dbfamily() === 'mysql') {
            if ($this->data['type'] === 'mediumint') {
                // There is an error in setFromADOField() function, type 'mediumint' is missing.
                $this->data['type'] = 'smallint';
            }
            if ($this->data['type'] === 'double') {
                // Function xmldb_field::validateDefinition() is outdated, it thinks 20 is the max for the
                // length of float/double field.
                $this->data['max_length'] = min($this->data['max_length'], \xmldb_field::FLOAT_MAX_LENGTH);
            }
        }
        $this->data['type'] = (string)$this->data['type']; // Prevent PHP8 warnings.

        $field = new \xmldb_field($this->name);
        $field->setFromADOField($this);

        if ($DB->get_dbfamily() === 'postgres') {
            if ($this->type === 'float') {
                // There is some code in pgsql_native_moodle_database::fetch_columns() that returns very weird small
                // field size and precision for float type.
                if ($deftable && ($deffield = $deftable->get_xmldb_table()->getField($this->name))) {
                    $field->setDecimals($deffield->getDecimals());
                    $field->setLength($deffield->getLength());
                } else if ($field->getLength() == 4) {
                    $field->setDecimals(4);
                    $field->setLength(11);
                } else {
                    // TODO find examples.
                    $field->setDecimals(8);
                    $field->setLength(20);
                }
            }
        }

        if ($deftable) {
            $this->fix_field_precision($field, $deftable);
        }
        $this->fix_field_properties($field, $deftable);
        return $field;
    }

    /**
     * Fix precision
     *
     * @param \xmldb_field $actualfield
     * @param dbtable $deftable
     * @return void
     */
    protected function fix_field_precision(\xmldb_field $actualfield, dbtable $deftable) {
        global $DB;
        if (!($deffield = $deftable->get_xmldb_table()->getField($this->name))) {
            return;
        }
        $checksql = false;
        if ($actualfield->getType() === XMLDB_TYPE_CHAR && $deffield->getType() === XMLDB_TYPE_CHAR &&
            $deffield->getNotNull() === $actualfield->getNotNull() && $deffield->getNotNull() &&
            $deffield->getDefault() === null && $actualfield->getDefault() === '') {
            $checksql = true;
        }

        if ($actualfield->getType() == XMLDB_TYPE_INTEGER) {
            $actualfield->setLength($deffield->getLength());
        }

        if (in_array($actualfield->getType(), [XMLDB_TYPE_NUMBER, XMLDB_TYPE_FLOAT]) &&
            (float)$actualfield->getDefault() == (float)$deffield->getDefault()) {
            // Sometimes default value comes through as '0.0000' instead of '0'.
            $actualfield->setDefault($deffield->getDefault());
        }

        if ($DB->get_dbfamily() === 'mysql' && $actualfield->getType() == XMLDB_TYPE_FLOAT && !$deffield->getLength()) {
            // There are couple of fields that miss length.
            $checksql = true;
        }

        if ($checksql) {
            if ($deftable->get_field_sql($deftable->get_xmldb_table(), $actualfield) ===
                    $deftable->get_field_sql($deftable->get_xmldb_table(), $deffield)) {
                $actualfield->setDefault($deffield->getDefault());
                $actualfield->setLength($deffield->getLength());
                $actualfield->setDecimals($deffield->getDecimals());
            }
        }
    }

    /**
     * Fix field properties, for example default value
     *
     * @param \xmldb_field $actualfield
     * @param dbtable|null $deftable
     * @return void
     */
    protected function fix_field_properties(\xmldb_field $actualfield, ?dbtable $deftable) {
        // Non-null char column should have either no default or a default that is not empty string.
        // Otherwise the parser complains on restore.
        if ($actualfield->getType() === XMLDB_TYPE_CHAR && $actualfield->getNotNull() &&
                $actualfield->getDefault() === '') {
            $actualfield->setDefault(null);
        }
    }
}
