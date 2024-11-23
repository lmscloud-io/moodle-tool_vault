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

use tool_vault\constants;
use tool_vault\local\helpers\ui;

/**
 * Model for local backup
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_model extends operation_model {
    /** @var string */
    protected static $defaulttype = 'backup';

    /**
     * Get display title
     *
     * @return string
     */
    public function get_title() {
        if ($this->backupkey) {
            return get_string('backuptitle', 'tool_vault', s($this->backupkey));
        } else if ($this->status === constants::STATUS_SCHEDULED) {
            return get_string('backupscheduled', 'tool_vault');
        } else {
            return get_string('backup', 'tool_vault');
        }
    }

    /**
     * Get status and time modified
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_subtitle() {
        return get_string('status', 'moodle') . ' ' . ui::format_status($this->status) .
            ' : ' . userdate($this->timemodified, get_string('strftimedatetimeshort', 'langconfig'));
    }

    /**
     * Get backup by backupkey
     *
     * @param string $backupkey
     * @return static|null
     */
    public static function get_by_backup_key($backupkey) {
        /** @var backup_model[] $records */
        $records = self::get_records_select(
            "type = :type AND backupkey = :backupkey",
            ['type' => self::$defaulttype, 'backupkey' => $backupkey]);
        return $records ? reset($records) : null;
    }

    /**
     * If there is a scheduled backup, return it
     *
     * @return false|mixed
     */
    public static function get_scheduled_backup() {
        /** @var backup_model[] $backups */
        $backups = self::get_records([constants::STATUS_SCHEDULED]);
        return $backups ? reset($backups) : null;
    }

    /**
     * If there is a backup in progress, return it
     *
     * @return \stdClass|null
     */
    public static function get_backup_in_progress() {
        /** @var backup_model[] $backups */
        $backups = self::get_records([constants::STATUS_INPROGRESS]);
        return $backups ? reset($backups) : null;
    }

    /**
     * Get the last backup scheduled on this server
     *
     * @return ?backup_model
     */
    public static function get_last_backup() {
        /** @var backup_model[] $backups */
        $backups = self::get_records(null, null, 0, 1);
        return $backups ? reset($backups) : null;
    }

    /**
     * Save record
     *
     * @return operation_model
     */
    public function save() {
        if (!$this->accesskey) {
            $this->generate_access_key();
        }
        return parent::save();
    }

    /**
     * Get description
     *
     * @return string
     */
    public function get_description() {
        return isset($this->get_details()['description']) ? $this->get_details()['description'] : '';
    }

    /**
     * Get is encrypted
     *
     * @return bool
     */
    public function get_encrypted() {
        return isset($this->get_details()['encrypted']) ? $this->get_details()['encrypted'] : false;
    }

    /**
     * Get performed by
     *
     * @return string
     */
    public function get_performedby() {
        $performedby = isset($this->get_details()['fullname']) ? $this->get_details()['fullname'] : '';
        if (!empty($this->get_details()['email'])) {
            $performedby .= " <{$this->get_details()['email']}>";
        }
        return $performedby;
    }
}
