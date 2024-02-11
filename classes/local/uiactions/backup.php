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
use tool_vault\local\models\operation_model;
use tool_vault\output\check_display;
use tool_vault\output\last_operation;

/**
 * Tab backup
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup extends base {

    /**
     * Display name of the section (for the breadcrumb)
     *
     * @return string
     */
    public static function get_display_name(): string {
        return get_string('sitebackup', 'tool_vault');
    }

    /**
     * Export for output
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template($output): array {
        global $CFG, $USER;
        $whybackupdisabled = null;
        $activeprocesses = operation_model::get_active_processes(true);
        if (!api::is_registered()) {
            // TODO add language strings.
            $whybackupdisabled = get_string('backupdisablednoapikey', 'tool_vault');
        } else if ($activeprocesses) {
            $whybackupdisabled = get_string('backupdisabledanotherinprogress', 'tool_vault');
        }
        $lastbackup = backup_model::get_last_of([backup_model::class]);
        $result = [
            'canstartbackup' => !$whybackupdisabled,
            'lastoperation' => ($lastbackup && $lastbackup->show_as_last_operation()) ?
                (new last_operation($lastbackup))->export_for_template($output) : null,
            'whybackupdisabled' => $whybackupdisabled,
        ];

        $result['startbackupurl'] = backup_startbackup::url()->out(false);
        $result['contextid'] = \context_system::instance()->id;

        if (!api::is_registered()) {
            $result['registrationform'] = $this->registration_form($output);
            $result['canstartbackup'] = false;
        }

        $backups = backup_model::get_records(null, null, 0, 20); // TODO pagination?
        $result['backups'] = [];
        foreach ($backups as $backup) {
            $result['backups'][] = (new \tool_vault\output\backup_details($backup, null, false))->export_for_template($output);
        }
        $result['haspastbackups'] = !empty($result['backups']);
        $result['restoreallowed'] = api::are_restores_allowed();
        return $result;
    }

    /**
     * Display
     *
     * @param \renderer_base $output
     * @return string
     */
    public function display(\renderer_base $output) {
        $rv = $output->render_from_template('tool_vault/section_backup',
            $this->export_for_template($output));

        foreach (\tool_vault\local\checks\check_base::get_all_checks() as $check) {
            $data = (new check_display($check))->export_for_template($output);
            $rv .= $output->render_from_template('tool_vault/check_summary', $data);
        }

        return $rv;
    }
}
