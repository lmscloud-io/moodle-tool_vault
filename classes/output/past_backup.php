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

namespace tool_vault\output;

use tool_vault\api;
use tool_vault\constants;
use tool_vault\local\models\backup_model;
use tool_vault\local\uiactions\restore_dryrun;
use tool_vault\local\uiactions\restore_restore;

/**
 * Past backup
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class past_backup implements \templatable {
    /** @var backup_model */
    protected $backup;

    /**
     * Constructor
     *
     * @param backup_model $backup
     */
    public function __construct(backup_model $backup) {
        $this->backup = $backup;
    }

    /**
     * Export for output
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template($output) {
        $rv = [
            'backupkey' => $this->backup->backupkey,
            'encrypted' => (bool)($this->backup->get_details()['encrypted'] ?? false),
        ];

        $rv['started'] = userdate($this->backup->timecreated, get_string('strftimedatetimeshort', 'langconfig'));
        $finished = userdate($this->backup->timemodified, get_string('strftimedatetimeshort', 'langconfig'));
        $performedby = $this->backup->get_details()['fullname'] ?? '';
        if (!empty($this->backup->get_details()['email'])) {
            $performedby .= " <{$this->backup->get_details()['email']}>";
        }
        $rv['status'] = $this->backup->status;
        $rv['description'] = $this->backup->get_details()['description'];
        $rv['performedby'] = s($performedby);
        if (!in_array($this->backup->status, [constants::STATUS_INPROGRESS, constants::STATUS_SCHEDULED])) {
            $rv['finished'] = $finished;
        }
        $rv['detailsurl'] = \tool_vault\local\uiactions\backup_details::url(['id' => $this->backup->id])->out(false);

        if ($this->backup->status == constants::STATUS_FINISHED && api::is_registered()) {
            $remotebackups = api::get_remote_backups(api::get_remote_backups_time() > $this->backup->get_last_modified());
            if (isset($remotebackups[$this->backup->backupkey])) {
                $rv['restoreurl'] = restore_restore::url(['backupkey' => $this->backup->backupkey])->out(false);
                $rv['dryrunurl'] = restore_dryrun::url(['backupkey' => $this->backup->backupkey])->out(false);
                $rv['showactions'] = true;
            }
        }

        return $rv;
    }
}
