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
     * @param \database_column_info $obj
     * @return database_column_info
     */
    public static function clone_from(\database_column_info $obj): self {
        return new static((object)$obj->data);
    }

    /**
     * Magic get function.
     *
     * @param string $variablename variable name to return the value of.
     * @return mixed The variable contents.
     */
    public function __get($variablename) {
        global $DB;
        if ($DB->get_dbfamily() === 'mysql') {
            if ($variablename === 'type' && $this->data[$variablename] === 'mediumint') {
                // There is an error in setFromADOField() function, type 'mediumint' is missing.
                return 'smallint';
            }
            if ($variablename === 'max_length' && $this->data['type'] === 'double') {
                // Function xmldb_field::validateDefinition() is outdated, it thinks 20 is the max for the
                // length of float/double field.
                return min($this->data[$variablename], \xmldb_field::FLOAT_MAX_LENGTH);
            }
        }
        return parent::__get($variablename);
    }
}
