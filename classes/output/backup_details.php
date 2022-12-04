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
use tool_vault\local\models\remote_backup;
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

    /**
     * Constructor
     *
     * @param backup_model $backup
     */
    public function __construct(?backup_model $backup, ?remote_backup $remotebackup = null) {
        $this->backup = $backup;
        $this->remotebackup = $remotebackup;
        if (!$backup && !$remotebackup) {
            throw new \coding_exception('Missing arguments');
        }
    }

    protected function get_property(string $key, bool $frominfo = false) {
        if ($frominfo) {
            return $this->remotebackup ? ($this->remotebackup->info[$key] ?? null) :
                ($this->backup->get_details()[$key] ?? null);
        } else {
            return $this->remotebackup ? ($this->remotebackup->$key ?? null) : ($this->backup->$key ?? null);
        }
    }

    protected function get_description() {
        if ($this->remotebackup) {
            return $this->remotebackup->info['description'] ?? '';
        } else if ($this->backup) {
            return $this->backup->get_details()['description'] ?? '';
        }
        return '';
    }

    /**
     * Export for output
     *
     * @param \tool_vault\output\renderer|\renderer_base $output
     * @return array
     */
    public function export_for_template($output) {
        $rv = [
            'sectionurl' => ui::backupurl()->out(false),
            'title' => 'Backup '.$this->get_property('backupkey'), // TODO string.
            'metadata' => [],
            'samesite' => $this->get_property('samesite', true),
        ];
        if ($logs = ($this->backup ? $this->backup->get_logs() : [])) {
            $rv['logsshort'] = $this->backup->get_logs_shortened();
            $rv['logs'] = $logs;
            $rv['haslogs'] = !empty($rv['logs']);
        }

        $started = userdate($this->get_property('timecreated'), get_string('strftimedatetimeshort', 'langconfig'));
        $finished = userdate($this->get_property('timemodified'), get_string('strftimedatetimeshort', 'langconfig'));
        $rv['metadata'][] = ['name' => 'Description', 'value' => s($this->get_property('description', true))];
        $status = $this->get_property('status');
        if ($this->backup && $this->backup->status === 'finished' && !$this->remotebackup) {
            $status .= ', Not available for restore'; // TODO string, help tip.
        }
        $rv['metadata'][] = ['name' => 'Status', 'value' => $status];
        if ($this->backup) {
            $performedby = $this->backup->get_details()['fullname'] ?? '';
            if (!empty($this->backup->get_details()['email'])) {
                $performedby .= " <{$this->backup->get_details()['email']}>";
            }
            $rv['metadata'][] = ['name' => 'Performed by', 'value' => s($performedby)];
        }
        $rv['metadata'][] = ['name' => 'Started on', 'value' => $started];
        if (!in_array($this->get_property('status'), [constants::STATUS_INPROGRESS, constants::STATUS_SCHEDULED])) {
            $rv['metadata'][] = ['name' => 'Finished on', 'value' => $finished];
        }
        $rv['metadata'][] = ['name' => 'Encrypted', 'value' =>
            $this->get_property('encrypted', true) ? get_string('yes') :  get_string('no')];
        if ($totalsize = $this->get_property('totalsize', true)) {
            $rv['metadata'][] = ['name' => 'Total size (archived)', 'value' => display_size($totalsize)];
        }
        if ($this->remotebackup && ($tags = $this->get_property('tags', true))) {
            $rv['metadata'][] = ['name' => 'Tags', 'value' => s(join(', ', $tags))];
        }

        if ($this->remotebackup) {
            if (!api::are_restores_allowed()) {
                $error = get_string('restoresnotallowed', 'tool_vault');
            }
            if ($dryrun = site_restore_dryrun::get_last_dryrun($this->remotebackup->backupkey)) {
                $rv['lastdryrun'] = (new last_operation($dryrun->get_model()))->export_for_template($output);
                if (!isset($error) && !$dryrun->prechecks_succeeded()) {
                    $error = 'Restore can not be performed until all pre-checkes have passed';
                }
                $rv['errormessage'] = error_with_backtrace::create_from_model($dryrun->get_model())->export_for_template($output);
            }
            $rv['isfinished'] = ($this->remotebackup->status === \tool_vault\constants::STATUS_FINISHED);
            $rv['showactions'] = $rv['isfinished'] && empty($rv['lastdryrun']['inprogress']);
            $restoreurl = ui::restoreurl(['action' => 'restore',
                'backupkey' => $this->remotebackup->backupkey, 'sesskey' => sesskey()]);
            $dryrunurl = ui::restoreurl(['action' => 'dryrun',
                'backupkey' => $this->remotebackup->backupkey, 'sesskey' => sesskey()]);
            $rv['showactions'] = $rv['isfinished'] && empty($rv['lastdryrun']['inprogress']);
            $rv += [
                'dryrunurl' => $dryrunurl->out(false),
                'restoreurl' => $restoreurl->out(false),
            ];
            if (isset($error)) {
                $rv['restorenotallowedreason'] = (new \core\output\notification($error, null, false))->export_for_template($output);
            } else {
                $rv['restoreallowed'] = true;
            }
        }

        return $rv;
    }
}
