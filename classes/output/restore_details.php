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

use tool_vault\constants;
use tool_vault\local\models\restore_model;

/**
 * Restore details
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_details implements \templatable {
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
        $url = new \moodle_url('/admin/tool/vault/index.php', ['section' => 'restore']);
        $rv = [
            'sectionurl' => $url->out(false),
            'title' => $this->restore->get_title(),
            'logs' => $this->restore->get_logs(),
            'metadata' => [],
            'errormessage' => error_with_backtrace::create_from_model($this->restore)->export_for_template($output),
        ];

        $started = userdate($this->restore->timecreated, get_string('strftimedatetimeshort', 'langconfig'));
        $finished = userdate($this->restore->timemodified, get_string('strftimedatetimeshort', 'langconfig'));
        $backupurl = new \moodle_url('/admin/tool/vault/index.php',
            ['section' => 'restore', 'action' => 'remotedetails', 'backupkey' => $this->restore->backupkey]);
        $performedby = $this->restore->get_details()['fullname'] ?? '';
        if (!empty($this->restore->get_details()['email'])) {
            $performedby .= " <{$this->restore->get_details()['email']}>";
        }

        $rv['metadata'][] = ['name' => 'Status', 'value' => $this->restore->status];
        $rv['metadata'][] = ['name' => 'Performed by', 'value' => s($performedby)];
        $rv['metadata'][] = ['name' => 'Started on', 'value' => $started];
        if (!in_array($this->restore->status, [constants::STATUS_INPROGRESS, constants::STATUS_SCHEDULED])) {
            $rv['metadata'][] = ['name' => 'Finished on', 'value' => $finished];
        }
        $rv['metadata'][] = ['name' => 'Remote backup', 'value' =>
            ($this->restore->get_metadata()['description'] ?? '').
            '<br>'.\html_writer::link($backupurl, $this->restore->backupkey)];

        return $rv;
    }
}
