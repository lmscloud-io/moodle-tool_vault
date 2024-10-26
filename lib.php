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

/**
 * Callbacks in tool_vault
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_vault\api;
use tool_vault\constants;
use tool_vault\local\checks\check_base;
use tool_vault\local\checks\plugins_restore;
use tool_vault\local\helpers\plugincode;
use tool_vault\local\models\dryrun_model;
use tool_vault\local\models\restore_model;
use tool_vault\output\start_backup_popup;

/**
 * Callback executed from setup.php on every page.
 *
 * Put site in maintenance mode if there is an active backup/restore
 *
 * @return void
 */
function tool_vault_after_config() {
    // This is an implementation of a legacy callback that will only be called in older Moodle versions.
    // It will not be called in Moodle versions that contain the hook core\hook\after_config,
    // instead, the callback tool_vault\hook_callbacks::after_config will be executed.

    global $PAGE, $FULLME;
    if (during_initial_install()) {
        return;
    }

    if (class_exists(api::class) && api::is_maintenance_mode()) {
        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            echo "Site backup or restore is currently in progress. Aborting\n";
            exit(1);
        }
        $url = new moodle_url($FULLME);
        if (!$url->compare(new moodle_url('/admin/tool/vault/progress.php'), URL_MATCH_BASE)) {
            $PAGE->set_context(context_system::instance());
            $PAGE->set_url(new moodle_url('/'));
            header('X-Tool-Vault: true');
            print_maintenance_message();
        }
    }
}

/**
 * Fragment output for the start backup popup
 *
 * @param array $args
 * @return string
 */
function tool_vault_output_fragment_start_backup($args): string {
    global $OUTPUT, $CFG, $USER;

    $context = context_system::instance();
    require_capability('moodle/site:config', $context);

    $result = api::precheck_backup_allowed();
    $data = (new start_backup_popup($result))->export_for_template($OUTPUT);
    return $OUTPUT->render_from_template('tool_vault/start_backup_popup', $data);
}

/**
 * Serves a file
 *
 * @param mixed $course
 * @param mixed $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $sendfileoptions
 */
function tool_vault_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $sendfileoptions) {
    $itemid = clean_param(array_shift($args), PARAM_INT);
    $filename = array_pop($args); // The last item in the $args array.
    $filepath = '/' . ($args ? implode('/', $args) . '/' : '');

    if ($filearea === constants::FILENAME_PLUGINSCODE
            && $itemid
            && $filepath === '/'
            && $context->id == context_system::instance()->id) {
        require_capability('moodle/site:config', $context);
        $pluginname = basename($filename, '.zip');

        /** @var plugins_restore $model */
        $model = plugins_restore::get_last_check_for_parent(['id' => $itemid], constants::RESTORE_PRECHECK_MAXAGE);

        if ($model && $model->is_plugin_code_included($pluginname) &&
                ($filepath = plugincode::create_plugin_zip_from_backup($model, $pluginname))) {
            send_temp_file($filepath, basename($filepath));
        }
    }

    send_file_not_found();
}
