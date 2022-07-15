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

namespace tool_vault\local\models;

use tool_vault\constants;

/**
 * Model for local backup
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup extends operation {
    /** @var string */
    protected static $defaulttype = 'backup';

    /**
     * Get display title
     *
     * @return string
     */
    public function get_title() {
        if ($this->backupkey) {
            return 'Backup ' . $this->backupkey;
        } else if ($this->status === constants::STATUS_SCHEDULED) {
            return 'Backup (scheduled)';
        } else {
            return 'Backup';
        }
    }

    /**
     * Get status and time modified
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_subtitle() {
        return 'Status '.$this->status.' : '.userdate($this->timemodified, get_string('strftimedatetimeshort', 'langconfig'));
    }

    /**
     * Get backup by backupkey
     * 
     * @param string $backupkey
     * @return static|null
     */
    public static function get_by_backup_key(string $backupkey): ?self {
        $records = self::get_records_select(
            "type = :type AND backupkey = :backupkey",
            ['type' => self::$defaulttype, 'backupkey' => $backupkey]);
        return $records ? reset($records) : null;
    }
}
