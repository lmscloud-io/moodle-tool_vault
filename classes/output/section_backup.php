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

/**
 * Tab backup
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section_backup extends section_base {

    /** @var bool */
    protected $isregistered;
    /** @var \moodleform */
    protected $form;

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
            api::store_config('apikey', null);
            redirect($PAGE->url);
        }

        $this->isregistered = \tool_vault\api::is_registered();
        if (!$this->isregistered) {
            $this->form = new \tool_vault\form\register_form($PAGE->url);
            if ($this->form->get_data()) {
                $this->form->process();
                redirect($PAGE->url);
            }
        }
    }

    /**
     * Is registered
     *
     * @return bool
     */
    public function get_is_registered() {
        return $this->isregistered;
    }

    /**
     * Form
     *
     * @return \moodleform
     */
    public function get_form(): \moodleform {
        return $this->form;
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
        if ($backup = \tool_vault\site_backup::get_scheduled_backup()) {
            $result['lastbackup'] = [
                'title' => $backup->get_title(),
                'subtitle' => $backup->get_subtitle(),
                'summary' => 'You backup is now scheduled and will be executed during the next cron run',
            ];
        } else if ($backup = \tool_vault\site_backup::get_backup_in_progress()) {
            $result['lastbackup'] = [
                'title' => $backup->get_title(),
                'subtitle' => $backup->get_subtitle(),
                'summary' => 'You have a backup in progress',
                'logs' => $backup->get_logs_shortened(),
            ];
            $result['showdetailslink'] = 1;
        } else if ($backup = \tool_vault\site_backup::get_last_backup()) {
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

        if ($this->get_is_registered()) {
            $result['apikey'] = \tool_vault\api::get_api_key();
            $forgeturl = new \moodle_url('/admin/tool/vault/index.php',
                ['section' => 'backup', 'sesskey' => sesskey(), 'action' => 'forgetapikey']);
            $result['forgetapikeyurl'] = $forgeturl->out(false);
            // TODO allow to ditch the old API key and create/enter a new one.
        } else {
            $result['apikeyform'] = $this->get_form()->render();
        }
        return $result;
    }
}
