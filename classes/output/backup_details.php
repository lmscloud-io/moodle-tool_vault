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
use tool_vault\local\helpers\ui;
use tool_vault\local\models\backup_model;
use tool_vault\local\models\remote_backup;

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
    /** @var remote_backup */
    protected $remotebackup;
    /** @var bool */
    protected $fulldetails;
    /** @var bool */
    protected $isprogresspage;

    /**
     * Constructor
     *
     * @param backup_model|null $backup
     * @param remote_backup|null $remotebackup ALWAYS NULL
     * @param bool $fulldetails
     * @param bool $isprogresspage
     */
    public function __construct($backup, $remotebackup = null,
                                bool $fulldetails = true, bool $isprogresspage = false) {
        $this->backup = $backup;
        $this->remotebackup = $remotebackup;
        if (!$backup && !$remotebackup) {
            throw new \coding_exception('Missing arguments');
        }
        $this->fulldetails = $fulldetails;
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
        $backupkey = $this->remotebackup->backupkey ?? $this->backup->backupkey ?? '';
        $status = $this->remotebackup->status ?? $this->backup->status ?? '';
        $encrypted = $this->remotebackup ? $this->remotebackup->get_encrypted() : $this->backup->get_encrypted();
        $timestarted = $this->remotebackup->timecreated ?? $this->backup->timecreated ?? 0;
        $timefinished = $this->remotebackup ? $this->remotebackup->get_finished_time() : $this->backup->get_finished_time();
        $description = $this->remotebackup ? $this->remotebackup->get_description() : $this->backup->get_description();
        $totalsizestr = $this->remotebackup ? display_size($this->remotebackup->get_total_size()) :
            ($this->backup->get_details()['totalsize'] ?? '');
        $rv = [
            'backupkey' => $backupkey,
            'statusstr' => ui::format_status($status),
            'encrypted' => $encrypted,
            'encryptedstr' => ui::format_encrypted($encrypted),
            'totalsizestr' => $totalsizestr,
            'title' => get_string('backuptitle', 'tool_vault', $backupkey),
            'timestarted' => ui::format_time($timestarted),
            'timefinished' => ui::format_time($timefinished),
            'description' => ui::format_description($description),
            'isprogresspage' => $this->isprogresspage,
            'siteurl' => $CFG->wwwroot,
        ];

        if ($this->isprogresspage && $status == constants::STATUS_INPROGRESS) {
            $lastmodified = $this->backup->get_last_modified();
            $elapsedtime = time() - $lastmodified;
            if ($elapsedtime > constants::LOCK_WARNING) {
                $rv['timeoutwarning'] = [
                    'elapsedtime' => ui::format_duration($elapsedtime),
                    'locktimeout' => get_string('numminutes', 'moodle', constants::LOCK_TIMEOUT / 60),
                ];
            }
        }

        if ($this->backup) {
            $rv['performedby'] = s($this->backup->get_performedby());
            $rv['backupdetailsurl'] = \tool_vault\local\uiactions\backup_details::url(['id' => $this->backup->id])->out(false);
            if ($this->fulldetails && $this->backup->has_logs()) {
                $rv += [
                    'logs' => $this->backup->get_logs(),
                    'haslogs' => $this->backup->has_logs(),
                    'logsshort' => $this->backup->get_logs_shortened(),
                    'haslogsshort' => $this->backup->has_logs_shortneded(),
                ];
            }
        }
        return $rv;
    }
}
