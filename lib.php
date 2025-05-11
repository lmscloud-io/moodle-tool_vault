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
