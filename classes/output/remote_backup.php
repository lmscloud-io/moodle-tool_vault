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
use stdClass;
use tool_vault\api;
use tool_vault\local\helpers\ui;
use tool_vault\local\models\dryrun_model;
use tool_vault\site_restore_dryrun;

/**
 * Remote backup
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remote_backup implements \templatable {

    /** @var \tool_vault\local\models\remote_backup */
    protected $backup;
    /** @var bool */
    protected $extradetails;

    /**
     * Constructor
     *
     * @param \tool_vault\local\models\remote_backup $backup
     * @param bool $extradetails
     */
    public function __construct(\tool_vault\local\models\remote_backup $backup, bool $extradetails = false) {
        $this->backup = $backup;
        $this->extradetails = $extradetails;
    }

    /**
     * Prepare metadata
     *
     * @param site_restore_dryrun|null $dryrun
     * @return array
     */
    protected function get_metadata( ?\tool_vault\site_restore_dryrun $dryrun) {
        $metadata = [];
        $started = userdate($this->backup->timecreated, get_string('strftimedatetimeshort', 'langconfig'));
        $metadata[] = ['name' => 'Time started', 'value' => $started];
        if ($this->backup->status !== \tool_vault\constants::STATUS_FINISHED) {
            if ($faileddetails = ($this->backup->info['faileddetails'] ?? '')) {
                $metadata[] = ['name' => 'Reason for failure', 'value' => $faileddetails];
            }
            return $metadata;
        }
        $finished = userdate($this->backup->timemodified, get_string('strftimedatetimeshort', 'langconfig'));
        $metadata[] = ['name' => 'Time finished', 'value' => $finished];
        $metadata[] = ['name' => 'Total size (archived)',
            'value' => $this->backup->info['totalsize'] ? display_size($this->backup->info['totalsize']) : ''];
        $metadata[] = ['name' => 'Encrypted',
            'value' => !empty($this->backup->info['encrypted']) ? get_string('yes') : get_string('no')];
        $other = $dryrun ? $dryrun->get_model()->get_metadata() : [];
        foreach ($other as $key => $value) {
            if (in_array($key, ['description', 'totalsize', 'tool_vault_version', 'version', 'branch', 'email',
                    'dbtotalsize', 'encrypted'])) {
                // Only used for pre-checks or displayed elsewhere.
                continue;
            } else if ($key === 'wwwroot') {
                $name = 'Original site URL';
            } else if ($key === 'dbengine') {
                $name = 'Original database type';
            } else if ($key === 'name') {
                $name = 'Performed by';
                $value = $value . (!empty($other['email']) ? " &lt;{$other['email']}&gt;" : '');
            } else if (is_array($value)) {
                continue;
            } else {
                $name = $key;
            }
            $metadata[] = ['name' => $name, 'value' => $value];
        }
        return $metadata;
    }

    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template. This means:
     * 1. No complex types - only stdClass, array, int, string, float, bool
     * 2. Any additional info that is required for the template is pre-calculated (e.g. capability checks).
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return stdClass|array
     */
    public function export_for_template(renderer_base $output) {
        $started = userdate($this->backup->timecreated, get_string('strftimedatetimeshort', 'langconfig'));
        $viewurl = ui::restoreurl(['action' => 'remotedetails', 'backupkey' => $this->backup->backupkey]);
        $restoreurl = ui::restoreurl(['action' => 'restore',
                'backupkey' => $this->backup->backupkey, 'sesskey' => sesskey()]);
        $dryrunurl = ui::restoreurl(['action' => 'dryrun',
                'backupkey' => $this->backup->backupkey, 'sesskey' => sesskey()]);
        $rv = [
            'sectionurl' => ui::restoreurl()->out(false),
            'showdetails' => $this->extradetails,
            'status' => $this->backup->status,
            'backupkey' => $this->backup->backupkey,
            'started' => $started,
            'info' => $this->backup->info,
            'isfinished' => ($this->backup->status === \tool_vault\constants::STATUS_FINISHED),
            'viewurl' => $viewurl->out(false),
            'dryrunurl' => $dryrunurl->out(false),
            'restoreurl' => $restoreurl->out(false),
        ];
        if (!api::are_restores_allowed()) {
            $error = get_string('restoresnotallowed', 'tool_vault');
        }
        if ($dryrun = site_restore_dryrun::get_last_dryrun($this->backup->backupkey)) {
            $rv['lastdryrun'] = (new dryrun($dryrun))->export_for_template($output);
            if (!isset($error) && !$dryrun->prechecks_succeeded()) {
                $error = 'Restore can not be performed until all pre-checkes have passed';
            }
            $rv['errormessage'] = error_with_backtrace::create_from_model($dryrun->get_model())->export_for_template($output);
        }
        $rv['showactions'] = $rv['isfinished'] && empty($rv['lastdryrun']['inprogress']);
        $rv['metadata'] = $this->get_metadata($dryrun);
        if (isset($error)) {
            $rv['restorenotallowedreason'] = (new \core\output\notification($error, null, false))->export_for_template($output);
        } else {
            $rv['restoreallowed'] = true;
        }
        return $rv;
    }
}
