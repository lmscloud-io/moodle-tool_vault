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

namespace tool_vault\local\models;

/**
 * Model for check
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_model extends operation_model {
    /** @var string */
    protected static $defaulttypeprefix = 'check:';

    /**
     * Constructor
     *
     * @param \stdClass|null $record
     * @param string|null $type
     */
    public function __construct($record = null, $type = null) {
        if ($record && isset($record->type) && !self::validate_type($record->type)) {
            throw new \coding_exception('Type '.$record->type.' is invalid for a check');
        }
        if ($type) {
            $record = $record ?? new \stdClass();
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
    public static function validate_type(string $type) {
        return substr($type, 0, strlen(self::$defaulttypeprefix)) === self::$defaulttypeprefix;
    }

    /**
     * Get checks by type
     *
     * @param string $type
     * @return array
     */
    public static function get_checks_by_type(string $type): array {
        return static::get_records_select('type=:type', ['type' => self::$defaulttypeprefix.$type]);
    }

    /**
     * Retrieve record by id
     *
     * @param int $id
     * @return static|null
     */
    public static function get_by_id(int $id) {
        if (($model = parent::get_by_id($id)) && self::validate_type($model->type)) {
            return $model;
        }
        return null;
    }

    /**
     * Get class name of the corresponding check
     *
     * @return string
     */
    public function get_check_name(): string {
        return substr($this->type, strlen(self::$defaulttypeprefix));
    }

    /**
     * Get all checks with specific parentid
     *
     * @param int $operationid
     * @return static[]
     */
    public static function get_all_checks_for_operation(int $operationid): array {
        return self::get_records_select('parentid = ? AND type LIKE ?', [$operationid, 'check:%'], 'id');
    }
}
