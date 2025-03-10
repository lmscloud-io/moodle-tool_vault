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

use core\exception\moodle_exception;
use tool_vault\constants;
use tool_vault\local\helpers\ui;

/**
 * Model for local restore
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_model extends restore_base_model {
    /** @var string */
    protected static $defaulttype = 'restore';

    /**
     * Get display title
     *
     * @return string
     */
    public function get_title() {
        $title = get_string('restorefrombackup', 'tool_vault', s($this->backupkey));
        if ($this->status === constants::STATUS_SCHEDULED) {
            $title .= ' (' . ui::format_status($this->status) . ')';
        }
        return $title;
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
     * Save record
     *
     * @return operation_model
     */
    public function save(): operation_model {
        if (!$this->accesskey) {
            $this->generate_access_key();
        }
        return parent::save();
    }

    /**
     * Finds the last restore and checks that it has started and failed
     *
     * @throws \moodle_exception
     * @return restore_model
     */
    public static function get_restore_to_resume(): restore_model {
        /** @var restore_model|null $model */
        $model = self::get_last_of([self::class]);
        if (!$model || $model->status === constants::STATUS_FINISHED) {
            throw new moodle_exception('error_nothingtorestore', 'tool_vault');
        }
        if ($model->status === constants::STATUS_FAILED) {
            $restorekey = $model->get_details()['restorekey'] ?? null;
            if (!$restorekey) {
                // This should never happen, if the restorekey is not present, the status would be
                // failed to start.
                throw new moodle_exception('Last restore process does not have restorekey');
            }
            return $model;
        } else {
            // Other statuses - failed to start, inprogress, scheduled.
            throw new moodle_exception('error_cannotresumerestore', 'tool_vault', '',
                ui::format_status($model->status));
        }
    }

    /**
     * Can the restore be resumed?
     *
     * The restore can be resumed if:
     * - it has failed
     * - it failed after the restorekey was obtained
     * - it is the last restore we made on this site
     * - the DB dump has been fully restored (resume is only possible on the dataroot and files stages)
     *
     * @return bool
     */
    public function can_resume(): bool {
        if ($this->status !== constants::STATUS_FAILED) {
            return false;
        }
        $restorekey = $this->get_details()['restorekey'] ?? null;
        if (!$restorekey) {
            return false;
        }
        /** @var restore_model|null $lastmodel */
        $lastmodel = self::get_last_of([self::class]);
        return $lastmodel && $lastmodel->id === $this->id && $this->is_db_restored();
    }

    /**
     * Extra check when resuming - is the DB restored?
     *
     * @return bool
     */
    public function is_db_restored(): bool {
        global $DB;
        $sql = "SELECT COUNT(*) AS totalcnt, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS finishedcnt
            FROM {" . backup_file::TABLE . "}
            WHERE operationid = ? AND filetype = ?";
        $params = [constants::STATUS_FINISHED, $this->id, constants::FILENAME_DBDUMP];
        $record = $DB->get_record_sql($sql, $params);
        return !empty($record->totalcnt) && $record->totalcnt == $record->finishedcnt;
    }
}
