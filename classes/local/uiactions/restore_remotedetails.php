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
use tool_vault\local\models\backup_model;

/**
 * Details of a remote backup
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_remotedetails extends base {

    /**
     * Display name of the section (for the breadcrumb)
     *
     * @return string
     */
    public static function get_display_name(): string {
        return 'Remote backup details';
    }

    /**
     * Display
     *
     * @param \renderer_base $output
     * @return string
     */
    public function display(\renderer_base $output) {
        $backupkey = optional_param('backupkey', null, PARAM_ALPHANUMEXT);
        $localbackup = backup_model::get_by_backup_key($backupkey);
        if (($backup = (api::get_remote_backups()[$backupkey] ?? null)) || $localbackup) {
            $data = (new \tool_vault\output\backup_details($localbackup, $backup))->export_for_template($output);
            return $output->render_from_template('tool_vault/backup_details', $data);
        } else {
            // Neither local nor remote backup is found.
            $data = [
                'title' => 'Backup '.s($backupkey),
            ];
            return $output->render_from_template('tool_vault/backup_details', $data);
        }
    }
}
