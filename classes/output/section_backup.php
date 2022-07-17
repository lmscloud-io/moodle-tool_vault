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
use tool_vault\form\general_settings_form;
use tool_vault\local\models\backup;

/**
 * Tab backup
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section_backup extends section_base implements \templatable {

    /**
     * Process tab actions
     */
    public function process() {
        global $PAGE, $DB;
        $action = optional_param('action', null, PARAM_ALPHANUMEXT);

        if ($action === 'startbackup' && confirm_sesskey()) {
            \tool_vault\site_backup::schedule_backup();
            redirect($PAGE->url);
        }

        if ($action === 'forgetapikey' && confirm_sesskey()) {
            api::set_api_key(null);
            redirect($PAGE->url);
        }
    }

    /**
     * Export for output
     *
     * @param \tool_vault\output\renderer $output
     * @return false[]
     */
    public function export_for_template($output): array {
        global $PAGE;
        $result = [
            'canstartbackup' => false,
        ];
        if ($backup = backup::get_scheduled_backup()) {
            $result['lastbackup'] = [
                'title' => $backup->get_title(),
                'subtitle' => $backup->get_subtitle(),
                'summary' => 'You backup is now scheduled and will be executed during the next cron run',
            ];
        } else if ($backup = backup::get_backup_in_progress()) {
            $result['lastbackup'] = [
                'title' => $backup->get_title(),
                'subtitle' => $backup->get_subtitle(),
                'summary' => 'You have a backup in progress',
                'logs' => $backup->get_logs_shortened(),
            ];
            $result['showdetailslink'] = 1;
        } else if ($backup = backup::get_last_backup()) {
            $result['lastbackup'] = [
                'title' => $backup->get_title(),
                'subtitle' => $backup->get_subtitle(),
                'logs' => $backup->get_logs_shortened(),
            ];
            $result['canstartbackup'] = true;
            $result['showdetailslink'] = 1;
        } else {
            $result['canstartbackup'] = true;
        }

        $result['startbackupurl'] = (new \moodle_url($PAGE->url,
            ['section' => 'backup', 'action' => 'startbackup', 'sesskey' => sesskey()]))->out(false);
        $result['fullreporturl'] = (new \moodle_url($PAGE->url,
            ['section' => 'backup', 'action' => 'details', 'id' => $backup->id ?? 0]))->out(false);

        if (!api::is_registered()) {
            $form = new general_settings_form(false);
            $result['registrationform'] = $form->render();
        }
        return $result;
    }
}
