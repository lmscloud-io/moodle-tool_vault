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

namespace tool_vault\output;

use tool_vault\constants;
use tool_vault\local\checks\check_base;
use tool_vault\local\helpers\ui;
use tool_vault\local\models\restore_model;
use tool_vault\local\uiactions\restore_remotedetails;

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
    /** @var bool */
    protected $isprogresspage;

    /**
     * Constructor
     *
     * @param restore_model $restore
     * @param bool $isprogresspage
     */
    public function __construct(restore_model $restore, bool $isprogresspage = false) {
        $this->restore = $restore;
        $this->isprogresspage = $isprogresspage;
    }

    /**
     * Export for output
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output) {
        global $CFG;
        $rv = [
            'title' => $this->restore->get_title(),
            'backupkey' => $this->restore->backupkey,
            'description' => s($this->restore->get_description()),
            'logs' => $this->restore->get_logs(),
            'haslogs' => $this->restore->has_logs(),
            'logsshort' => $this->restore->get_logs_shortened(),
            'haslogsshort' => $this->restore->has_logs_shortneded(),
            'errormessage' => error_with_backtrace::create_from_model($this->restore)->export_for_template($output),
            'statusstr' => ui::format_status($this->restore->status),
            'encrypted' => (int)$this->restore->get_encrypted(),
            'encryptedstr' => ui::format_encrypted($this->restore->get_encrypted()),
            'timestarted' => ui::format_time($this->restore->timecreated),
            'timefinished' => ui::format_time($this->restore->get_finished_time()),
            'performedby' => s($this->restore->get_performedby()),
            'restoredetailsurl' => \tool_vault\local\uiactions\restore_details::url(['id' => $this->restore->id])->out(false),
            'backupdetailsurl' => restore_remotedetails::url(['backupkey' => $this->restore->backupkey])->out(false),
            'siteurl' => $CFG->wwwroot,
            'isprogresspage' => $this->isprogresspage,
            'prechecks' => [],
            'restoreid' => $this->restore->id,
            'resumeurl' => $this->restore->can_resume() ? \tool_vault\local\uiactions\restore_resume::url(['id' => $this->restore->id])->out(false) : null,
        ];

        if ($this->restore->status == constants::STATUS_INPROGRESS) {
            $lastmodified = $this->restore->get_last_modified();
            $elapsedtime = time() - $lastmodified;
            if ($elapsedtime > constants::LOCK_WARNING) {
                $rv['timeoutwarning'] = [
                    'elapsedtime' => ui::format_duration($elapsedtime),
                    'locktimeout' => get_string('numminutes', 'moodle', constants::LOCK_TIMEOUT / 60),
                ];
            }
        }

        // Add failed pre-checks to the output.
        if ($this->restore->status === constants::STATUS_FAILED) {
            $prechecks = check_base::get_all_checks_for_operation($this->restore->id);
            foreach ($prechecks as $check) {
                if (!$check->success()) {
                    $rv['prechecks'][] = (new check_display($check))->export_for_template($output);
                }
            }
        }
        return $rv;
    }
}
