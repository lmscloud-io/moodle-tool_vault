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
use tool_vault\constants;
use tool_vault\form\general_settings_form;
use tool_vault\local\exceptions\api_exception;
use tool_vault\local\helpers\ui;
use tool_vault\site_restore;

/**
 * Tab restore
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section_restore extends section_base implements \templatable {

    /**
     * Process tab actions
     */
    public function process() {
        $action = optional_param('action', null, PARAM_ALPHANUMEXT);

        if ($action === 'restore' && confirm_sesskey()) {
            $backupkey = required_param('backupkey', PARAM_ALPHANUMEXT);
            $passphrase = optional_param('passphrase', '', PARAM_RAW);
            try {
                api::validate_backup($backupkey, $passphrase);
            } catch (api_exception $e) {
                redirect(ui::restoreurl(), $e->getMessage(), 0, \core\output\notification::NOTIFY_ERROR);
            }
            \tool_vault\site_restore::schedule(['backupkey' => $backupkey, 'passphrase' => $passphrase]);
            redirect(ui::restoreurl());
        }

        if ($action === 'dryrun' && confirm_sesskey()) {
            $backupkey = required_param('backupkey', PARAM_ALPHANUMEXT);
            $passphrase = optional_param('passphrase', '', PARAM_RAW);
            $viewurl = ui::restoreurl(['action' => 'remotedetails', 'backupkey' => $backupkey]);
            try {
                api::validate_backup($backupkey, $passphrase);
            } catch (api_exception $e) {
                redirect(ui::restoreurl(), $e->getMessage(), 0, \core\output\notification::NOTIFY_ERROR);
            }
            \tool_vault\site_restore_dryrun::schedule(['backupkey' => $backupkey, 'passphrase' => $passphrase]);
            redirect($viewurl);
        }

        if ($action === 'updateremote' && confirm_sesskey()) {
            api::store_config('cachedremotebackups', null);
            redirect(ui::restoreurl());
        }
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
        $result = ['isregistered' => (int)api::is_registered()];

        if ($restore = site_restore::get_last_restore()) {
            $result['lastrestore'] = [
                'title' => $restore->get_title(),
                'subtitle' => $restore->get_subtitle(),
                'summary' => '',
                'logs' => $restore->get_logs_shortened(),
            ];
            if ($restore->status === constants::STATUS_INPROGRESS || $restore->status === constants::STATUS_SCHEDULED) {
                $url = new \moodle_url('/admin/tool/vault/progress.php', ['accesskey' => $restore->accesskey]);
                $result['lastrestore']['unauthlink'] = $url->out(false);
            } else {
                $url = ui::restoreurl(['action' => 'details', 'id' => $restore->id]);
                $result['lastrestore']['showdetailslink'] = true;
                $result['lastrestore']['fullreporturl'] = $url->out(false);
            }
        }

        if (!api::is_registered()) {
            $form = new general_settings_form(false);
            $result['registrationform'] = $form->render();
        } else {
            try {
                $backups = \tool_vault\api::get_remote_backups();
                $backupstime = \tool_vault\api::get_remote_backups_time();
                $result['remotebackups'] = [];
                foreach ($backups as $backup) {
                    $result['remotebackups'][] = (new remote_backup($backup))->export_for_template($output);
                }
                $result['remotebackupstime'] = userdate($backupstime, get_string('strftimedatetimeshort', 'langconfig'));
            } catch (api_exception $e) {
                $result['errormessage'] = error_with_backtrace::create_from_exception($e)->export_for_template($output);
            }

            $url = ui::restoreurl(['action' => 'updateremote', 'sesskey' => sesskey()]);
            $result['remotebackupsupdateurl'] = $url->out(false);
        }
        return $result;
    }
}
