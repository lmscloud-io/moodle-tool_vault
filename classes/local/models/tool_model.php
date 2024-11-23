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

namespace tool_vault\local\models;

/**
 * Class tool_model
 *
 * @package    tool_vault
 * @copyright  2024 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_model extends operation_model {
    /** @var string */
    protected static $defaulttypeprefix = 'tool:';

    /**
     * Constructor
     *
     * @param \stdClass|null $record
     * @param string|null $type
     */
    public function __construct($record = null, $type = null) {
        if ($record && isset($record->type) && !self::validate_type($record->type)) {
            throw new \coding_exception('Type '.$record->type.' is invalid for a tool');
        }
        if ($type) {
            if (!$record) {
                $record = new \stdClass();
            }
            $record->type = self::$defaulttypeprefix . $type;
        }
        parent::__construct($record);
    }

    /**
     * Validate type
     *
     * @param string $type
     * @return bool
     */
    public static function validate_type($type) {
        return substr($type, 0, strlen(self::$defaulttypeprefix)) === self::$defaulttypeprefix;
    }

    /**
     * Get class name of the corresponding tool
     *
     * @return string
     */
    public function get_tool_name() {
        return substr($this->type, strlen(self::$defaulttypeprefix));
    }
}
