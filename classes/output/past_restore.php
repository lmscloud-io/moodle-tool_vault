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

use renderer_base;
use tool_vault\api;
use tool_vault\constants;
use tool_vault\local\helpers\ui;
use tool_vault\local\models\backup_model;
use tool_vault\local\models\restore_model;

/**
 * Backup details
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class past_restore implements \templatable {
    /** @var restore_model */
    protected $restore;

    /**
     * Constructor
     *
     * @param restore_model $restore
     */
    public function __construct(restore_model $restore) {
        $this->restore = $restore;
    }

    /**
     * Export for output
     *
     * @param \tool_vault\output\renderer $output
     * @return array
     */
    public function export_for_template($output) {
        $rv = [
            'backupkey' => $this->restore->backupkey,
            'encrypted' => (bool)($this->restore->get_details()['encrypted'] ?? false),
        ];

        $rv['started'] = userdate($this->restore->timecreated, get_string('strftimedatetimeshort', 'langconfig'));
        $finished = userdate($this->restore->timemodified, get_string('strftimedatetimeshort', 'langconfig'));
        $performedby = $this->restore->get_details()['fullname'] ?? '';
        if (!empty($this->restore->get_details()['email'])) {
            $performedby .= " <{$this->restore->get_details()['email']}>";
        }
        $rv['status'] = $this->restore->status;
        $rv['description'] = $this->restore->get_details()['description'] ?? '';
        $rv['performedby'] = s($performedby);
        if (!in_array($this->restore->status, [constants::STATUS_INPROGRESS, constants::STATUS_SCHEDULED])) {
            $rv['finished'] = $finished;
        }
        $rv['detailsurl'] = ui::backupurl(['action' => 'details', 'id' => $this->restore->id])->out(false);

        if (api::is_registered()) {
            $remotebackups = api::get_remote_backups(api::get_remote_backups_time() > $this->restore->get_last_modified());
            if (isset($remotebackups[$this->restore->backupkey])) {
                $rv['restoreurl'] = ui::restoreurl(['action' => 'restore',
                    'backupkey' => $this->restore->backupkey, 'sesskey' => sesskey()])->out(false);
                $rv['dryrunurl'] = ui::restoreurl(['action' => 'dryrun',
                    'backupkey' => $this->restore->backupkey, 'sesskey' => sesskey()])->out(false);
                $rv['showactions'] = true;
            }
        }

        return $rv;
    }
}
