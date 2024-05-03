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

use renderer_base;
use tool_vault\api;
use tool_vault\constants;
use tool_vault\local\helpers\ui;
use tool_vault\local\models\remote_backup;
use tool_vault\local\uiactions\restore_dryrun;
use tool_vault\local\uiactions\restore_remotedetails;
use tool_vault\local\uiactions\restore_restore;
use tool_vault\site_restore_dryrun;

/**
 * Output for restore dry-run (checks only)
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dryrun implements \templatable {
    /** @var site_restore_dryrun */
    protected $dryrun;
    /** @var remote_backup */
    protected $remotebackup;

    /**
     * Constructor
     *
     * @param site_restore_dryrun $dryrun
     * @param remote_backup|null $remotebackup
     */
    public function __construct(site_restore_dryrun $dryrun, ?remote_backup $remotebackup = null) {
        $this->dryrun = $dryrun;
        $this->remotebackup = $remotebackup;
    }

    /**
     * Get status and time modified
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_subtitle() {
        $model = $this->dryrun->get_model();
        return get_string('status', 'moodle') . ' ' . ui::format_status($model->status) .
            ' : ' . userdate($model->timemodified, get_string('strftimedatetimeshort', 'langconfig'));
    }

    /**
     * Function to export the renderer data in a format that is suitable for a mustache template.
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $model = $this->dryrun->get_model();
        $inprogress = $model->status === constants::STATUS_SCHEDULED || $model->status === constants::STATUS_INPROGRESS;
        $prechecks = [];
        foreach ($this->dryrun->get_prechecks() as $check) {
            $prechecks[] = (new check_display($check))->export_for_template($output);
        }
        $rv = [
            'backupkey' => $model->backupkey,
            'backupurl' => restore_remotedetails::url(['backupkey' => $model->backupkey])->out(false),
            'subtitle' => $this->get_subtitle(),
            'inprogress' => $inprogress,
            'prechecks' => $prechecks,
        ];
        $hidelogs = $model->status === constants::STATUS_FINISHED ||
            ($model->status === constants::STATUS_FAILED && $prechecks && !$model->has_error());
        if (!$hidelogs) {
            $rv += [
                'logs' => $model->get_logs(),
                'haslogs' => $model->has_logs(),
                'errormessage' => error_with_backtrace::create_from_model($model)->export_for_template($output),
            ];
        }
        if ($this->remotebackup) {
            $rv['showactions'] = true;
            $rv['restoreallowed'] = api::are_restores_allowed() && !api::is_cli_only();
            $rv['dryrunurl'] = restore_dryrun::url(['backupkey' => $model->backupkey])->out(false);
            $rv['restoreurl'] = restore_restore::url(['backupkey' => $model->backupkey])->out(false);
            $rv['startdryrunlabel'] = get_string('repeatprecheck', 'tool_vault');
            $rv['encrypted'] = (int)$this->remotebackup->get_encrypted();
        }
        return $rv;
    }


}
