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

namespace tool_vault\local\uiactions;

use tool_vault\api;
use tool_vault\local\models\dryrun_model;
use tool_vault\local\models\restore_model;
use tool_vault\output\dryrun;
use tool_vault\site_restore_dryrun;

/**
 * Details of a restore operation
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_details extends base {

    /**
     * Display
     *
     * @param \renderer_base $output
     * @return string
     */
    public function display(\renderer_base $output) {
        $id = optional_param('id', null, PARAM_INT);

        if ($restore = restore_model::get_by_id($id)) {
            $data = (new \tool_vault\output\restore_details($restore))->export_for_template($output);
            return $output->render_from_template('tool_vault/restore_details', $data);
        } else if ($dryrun = dryrun_model::get_by_id($id)) {
            $remotebackup = api::get_remote_backups()[$dryrun->backupkey] ?? null;
            $data = (new dryrun(new site_restore_dryrun($dryrun), $remotebackup))->export_for_template($output);
            return $output->render_from_template('tool_vault/dryrun', $data);
        } else {
            // Neither restore nor dryrun were found.
            // TODO display a relevant error message.
            $data = ['title' => 'Operation '.$id];
            return $output->render_from_template('tool_vault/backup_details', $data);
        }
    }
}
