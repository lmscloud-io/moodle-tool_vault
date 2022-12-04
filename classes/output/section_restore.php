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
use tool_vault\local\models\dryrun_model;
use tool_vault\local\models\restore_model;
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
            $restore = \tool_vault\site_restore::schedule(['backupkey' => $backupkey, 'passphrase' => $passphrase]);
            redirect(ui::progressurl(['accesskey' => $restore->get_model()->accesskey]));
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

        $restore = restore_model::get_last();
        $dryrun = dryrun_model::get_last();
        if ($restore && $dryrun && $restore->show_as_last_operation() && $dryrun->show_as_last_operation()) {
            $lastoperation = $restore->get_last_modified() > $dryrun->get_last_modified() ? $restore : $dryrun;
        } else if (!$dryrun || !$dryrun->show_as_last_operation()) {
            $lastoperation = $restore;
        } else {
            $lastoperation = $dryrun;
        }

        if ($lastoperation && $lastoperation->show_as_last_operation()) {
            $result['lastoperation'] = (new last_operation($lastoperation))->export_for_template($output);
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
                    // TODO use class backup_details.
                    $result['remotebackups'][] = (new remote_backup($backup))->export_for_template($output);
                }
                $result['remotebackupstime'] = userdate($backupstime, get_string('strftimedatetimeshort', 'langconfig'));
            } catch (api_exception $e) {
                $result['errormessage'] = error_with_backtrace::create_from_exception($e)->export_for_template($output);
            }

            $url = ui::restoreurl(['action' => 'updateremote', 'sesskey' => sesskey()]);
            $result['remotebackupsupdateurl'] = $url->out(false);
        }

        $restores = restore_model::get_records(null, null, 1, 20);
        $result['restores'] = [];
        foreach ($restores as $restore) {
            $result['restores'][] = (new past_restore($restore))->export_for_template($output);
        }
        $result['haspastrestores'] = !empty($result['restores']);
        return $result;
    }
}
