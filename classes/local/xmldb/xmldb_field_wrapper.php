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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_vault\local\xmldb;

use xmldb_field;
use xmldb_table;

// phpcs:disable moodle.NamingConventions.ValidFunctionName.LowercaseMethod

/**
 * Wrapper for xmldb_field class that relaxes some validation rules (i.e. max length of the char field)
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class xmldb_field_wrapper extends xmldb_field {

    /**
     * Creates an instance (does nothing if $field is already instance of this class)
     *
     * @param \xmldb_field $field
     * @return self
     */
    public static function create_field(xmldb_field $field) {
        if ($field instanceof self) {
            return $field;
        }
        $f = new static($field->getName());
        // Set all properties of this object to properties of $f.
        $classvars = get_class_vars(get_class($field));
        foreach ($classvars as $name => $value) {
            $f->$name = $field->$name;
        }
        return $f;
    }

    /**
     * Replaces all fields in the table with the instances of this class
     *
     * This way we can call $table->validateDefinition()
     *
     * @param \xmldb_table $table
     */
    public static function replace_table_fields(xmldb_table $table) {
        $fields = $table->getFields();
        $newfields = [];
        foreach ($fields as $i => $field) {
            $newfields[$i] = self::create_field($field);
        }
        $table->setFields($newfields);
    }

    /**
     * Validates the field restrictions.
     *
     * Relaxes some checks from the parent class
     *
     * @param xmldb_table|null $xmldbtable optional when object is table
     * @return string null if ok, error message if problem found
     */
    public function validateDefinition(xmldb_table $xmldbtable = null) {
        $origlength = $this->getLength();
        if ($this->getType() == XMLDB_TYPE_CHAR && $origlength > self::CHAR_MAX_LENGTH) {
            $this->setLength(self::CHAR_MAX_LENGTH);
        }
        if ($this->getType() == XMLDB_TYPE_NUMBER && $origlength > self::NUMBER_MAX_LENGTH) {
            $this->setLength(self::NUMBER_MAX_LENGTH);
        }
        if ($this->getType() == XMLDB_TYPE_FLOAT && $origlength > self::FLOAT_MAX_LENGTH) {
            $this->setLength(self::FLOAT_MAX_LENGTH);
        }
        $res = parent::validateDefinition($xmldbtable);
        if ($this->getLength() !== $origlength) {
            $this->setLength($origlength);
        }
        return $res;
    }
}
