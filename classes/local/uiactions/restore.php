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

use renderer_base;
use tool_vault\api;
use tool_vault\local\exceptions\api_exception;
use tool_vault\local\models\dryrun_model;
use tool_vault\local\models\operation_model;
use tool_vault\local\models\restore_model;
use tool_vault\output\error_with_backtrace;
use tool_vault\output\last_operation;

/**
 * Tab restore
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore extends base {

    /**
     * Display name of the section (for the breadcrumb)
     *
     * @return string
     */
    public static function get_display_name(): string {
        return get_string('siterestore', 'tool_vault');
    }

    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template. This means:
     * 1. No complex types - only stdClass, array, int, string, float, bool
     * 2. Any additional info that is required for the template is pre-calculated (e.g. capability checks).
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $CFG;
        $result = ['isregistered' => (int)api::is_registered()];

        $lastoperation = operation_model::get_last_of([restore_model::class, dryrun_model::class]);
        if ($lastoperation && $lastoperation->show_as_last_operation()) {
            $result['lastoperation'] = (new last_operation($lastoperation))->export_for_template($output);
        }

        if (!api::is_registered()) {
            $result['registrationform'] = $this->registration_form($output);
        } else {
            try {
                $backups = api::get_remote_backups();
                $backupstime = api::get_remote_backups_time();
                $result['remotebackups'] = [];
                foreach ($backups as $backup) {
                    $result['remotebackups'][] =
                        (new \tool_vault\output\backup_details(null, $backup, false))->export_for_template($output);
                }
                $result['remotebackupstime'] = userdate($backupstime, get_string('strftimedatetimeshort', 'langconfig'));
            } catch (api_exception $e) {
                $result['errormessage'] = error_with_backtrace::create_from_exception($e)->export_for_template($output);
            }

            $result['remotebackupsupdateurl'] = restore_updateremote::url()->out(false);
        }
        $result['islegacy'] = (int)($CFG->branch) <= 39;
        return $result;
    }

    /**
     * Display
     *
     * @param renderer_base $output
     * @return string
     */
    public function display(renderer_base $output) {
        return $output->render_from_template('tool_vault/section_restore',
            $this->export_for_template($output));
    }
}
