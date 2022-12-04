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

namespace tool_vault\local\uiactions;

use tool_vault\api;
use tool_vault\form\general_settings_form;
use tool_vault\local\helpers\ui;
use tool_vault\local\models\backup_model;
use tool_vault\local\models\operation_model;
use tool_vault\output\last_operation;
use tool_vault\output\past_backup;

/**
 * Tab backup
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup extends base {

    /**
     * Export for output
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template($output): array {
        global $CFG, $USER;
        $activeprocesses = operation_model::get_active_processes(true);
        $lastbackup = backup_model::get_last();
        $result = [
            'canstartbackup' => empty($activeprocesses),
            'lastoperation' => ($lastbackup && $lastbackup->show_as_last_operation()) ?
                (new last_operation($lastbackup))->export_for_template($output) : null,
        ];

        $result['startbackupurl'] = backup_startbackup::url()->out(false);
        $result['defaultbackupdescription'] = $CFG->wwwroot.' by '.fullname($USER); // TODO string?

        if (!api::is_registered()) {
            $form = new general_settings_form(false);
            $result['registrationform'] = $form->render();
            $result['canstartbackup'] = false;
        }

        $backups = backup_model::get_records(null, null, 1, 20);
        $result['backups'] = [];
        foreach ($backups as $backup) {
            $result['backups'][] = (new past_backup($backup))->export_for_template($output);
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
        return $output->render_from_template('tool_vault/section_backup',
            $this->export_for_template($output));
    }
}
