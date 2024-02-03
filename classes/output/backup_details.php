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
use tool_vault\local\helpers\ui;
use tool_vault\local\models\backup_model;
use tool_vault\local\models\dryrun_model;
use tool_vault\local\models\operation_model;
use tool_vault\local\models\remote_backup;
use tool_vault\local\models\restore_model;
use tool_vault\local\uiactions\restore;
use tool_vault\local\uiactions\restore_dryrun;
use tool_vault\local\uiactions\restore_remotedetails;
use tool_vault\local\uiactions\restore_restore;
use tool_vault\site_restore_dryrun;

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
     * @param remote_backup|null $remotebackup
     * @param bool $fulldetails
     * @param bool $isprogresspage
     */
    public function __construct(?backup_model $backup, ?remote_backup $remotebackup = null,
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
    public function export_for_template($output) {
        $backupkey = $this->remotebackup->backupkey ?? $this->backup->backupkey ?? '';
        $status = $this->remotebackup->status ?? $this->backup->status ?? '';
        $encrypted = $this->remotebackup ? $this->remotebackup->get_encrypted() : $this->backup->get_encrypted();
        $timestarted = $this->remotebackup->timecreated ?? $this->backup->timecreated ?? 0;
        $timefinished = $this->remotebackup ? $this->remotebackup->get_finished_time() : $this->backup->get_finished_time();
        $description = $this->remotebackup ? $this->remotebackup->get_description() : $this->backup->get_description();
        $rv = [
            'backupkey' => $backupkey,
            'statusstr' => ui::format_status($status),
            'encrypted' => $encrypted,
            'encryptedstr' => ui::format_encrypted($encrypted),
            'title' => 'Backup '.$backupkey, // TODO string.
            'timestarted' => ui::format_time($timestarted),
            'timefinished' => ui::format_time($timefinished),
            'description' => ui::format_description($description),
            'isprogresspage' => $this->isprogresspage,
        ];
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
        if ($this->remotebackup) {
            // If there is information in the remote backup AND in the local backup, the remote one overrides.
            // All remote backups have status 'finished' and are available for restore.
            $rv['backupdetailsurl'] = restore_remotedetails::url(['backupkey' => $backupkey])->out(false);
            $rv['totalsize'] = $this->remotebackup->get_total_size();
            $rv['totalsizestr'] = display_size($this->remotebackup->get_total_size());
            $rv['samesite'] = $this->remotebackup->is_same_site();
            if (api::are_restores_allowed()) {
                $rv['restoreallowed'] = true;
            } else {
                $error = get_string('restoresnotallowed', 'tool_vault');
            }
            if ($this->fulldetails) {
                $lastoperation = operation_model::get_last_of([restore_model::class, dryrun_model::class],
                    ['backupkey' => $backupkey]);
                if ($lastoperation) {
                    $rv['lastdryrun'] = (new last_operation($lastoperation))->export_for_template($output);
                }
            }
            $rv['showactions'] = true;
            $rv['dryrunurl'] = restore_dryrun::url(['backupkey' => $backupkey])->out(false);
            $rv['restoreurl'] = restore_restore::url(['backupkey' => $backupkey])->out(false);
        } else if ($this->fulldetails && !$this->isprogresspage && $this->backup->status === constants::STATUS_FINISHED) {
            $error = 'This backup is not available on the server';
            // TODO explanation why:
            // - expired
            // - was deleted
            // - performed in a different account
            // - API key in use does not allow restores.
        }
        if (isset($error)) {
            $rv['restorenotallowedreason'] = (new \core\output\notification($error, null, false))->export_for_template($output);
        }
        return $rv;
    }
}
