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
use tool_vault\constants;
use tool_vault\local\helpers\ui;
use tool_vault\local\models\backup_model;

/**
 * Backup details
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_details implements \templatable {
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
     * @param \tool_vault\output\renderer $output
     * @return array
     */
    public function export_for_template($output) {
        $rv = [
            'sectionurl' => ui::backupurl()->out(false),
            'title' => $this->backup->get_title(),
            'subtitle' => $this->backup->get_subtitle(),
            'metadata' => [],
            'logs' => $this->backup->get_logs(),
        ];

        $started = userdate($this->backup->timecreated, get_string('strftimedatetimeshort', 'langconfig'));
        $finished = userdate($this->backup->timemodified, get_string('strftimedatetimeshort', 'langconfig'));
        $performedby = $this->backup->get_details()['fullname'] ?? '';
        if (!empty($this->backup->get_details()['email'])) {
            $performedby .= " <{$this->backup->get_details()['email']}>";
        }
        $rv['metadata'][] = ['name' => 'Status', 'value' => $this->backup->status];
        $rv['metadata'][] = ['name' => 'Description', 'value' => $this->backup->get_details()['description']];
        $rv['metadata'][] = ['name' => 'Performed by', 'value' => s($performedby)];
        $rv['metadata'][] = ['name' => 'Started on', 'value' => $started];
        if (!in_array($this->backup->status, [constants::STATUS_INPROGRESS, constants::STATUS_SCHEDULED])) {
            $rv['metadata'][] = ['name' => 'Finished on', 'value' => $finished];
        }

        return $rv;
    }
}
