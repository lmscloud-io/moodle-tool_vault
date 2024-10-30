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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * CLI script to add code of add-on plugins either from moodle.org or from a vault backup
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

use tool_vault\local\cli_helper;
use tool_vault\local\helpers\plugincode;
use tool_vault\constants;
use tool_vault\local\checks\plugins_restore;
use tool_vault\local\helpers\ui;

require_once(__DIR__ . '/../../../../config.php');

// Increase time and memory limit.
@set_time_limit(0);
raise_memory_limit(MEMORY_HUGE);

$clihelper = new cli_helper(cli_helper::SCRIPT_ADDONS, basename(__FILE__),
    'When --backupkey is specified, the --name parameter is not required, the script will try to add the code for '.
    'the add-on plugins that were present in the backup but are missing or outdated on this site.' . "\n\n".
    'Note that this script should not be executed as a www user '.
    'but rather as a user that can write to the codebase.');

if ($clihelper->get_cli_option('help')) {
    $clihelper->print_help();
    die();
}

// Validate all arguments.
$clihelper->validate_cli_options();
$precheckonly = $clihelper->get_cli_option('dryrun');
$overwrite = $clihelper->get_cli_option('overwrite');

$clihelper->cli_writeln('Adding code for the missing add-on plugins.');

$names = $clihelper->get_cli_option('name');
if ($names !== null) {
    $names = preg_split('/\s*,\s*/', $names, -1, PREG_SPLIT_NO_EMPTY);
    // Validate each plugin name.
    foreach ($names as $name) {
        $parts = explode('@', $name);
        if (count($parts) > 2 ||
                trim($parts[0]) === '' ||
                (clean_param($parts[0], PARAM_COMPONENT) !== $parts[0]) ||
                (count($parts) == 2 && !preg_match('/^\d{10}$/', $parts[1]))) {
            cli_error('ERROR. Plugin name '.$name.' is not valid. Valid examples: "local_myplugin" or "local_myplugin@2024010100".');
        }
    }
}

$backupkey = $clihelper->get_cli_option('backupkey');
$installedcount = 0;

if ($backupkey !== null) {
    // Installing from the backup.

    // Make sure we downloaded the dbstructure and codebase.
    /** @var plugins_restore $model */
    $model = plugins_restore::get_last_check_for_parent(['backupkey' => $backupkey], constants::RESTORE_PRECHECK_MAXAGE);
    $precheckexample = "Example (without passphrase):\n" .
            "    sudo -u www-data php admin/tool/vault/cli/site_restore.php --backupkey={$backupkey} --dryrun";
    if (!$model) {
        $maxage = ui::format_duration(constants::RESTORE_PRECHECK_MAXAGE, false);
        cli_error("No recent restore pre-check for this backup was found. " .
            "You need to execute restore pre-check first. Plugins code from backup will be stored on this site for $maxage\n\n" .
            $precheckexample);
    } else {
        $age = ui::format_duration(time() - $model->get_parent()->get_finished_time(), false);
        $clihelper->cli_writeln("\nLast restore pre-check was performed $age ago.\n".
            "If your environment changed since then it is recommended to perform another restore pre-check.\n\n" .
            $precheckexample);
    }

    $files = $model->get_pluginscode_stored_files();
    if (!$files) {
        cli_error("Specified backup does not contain code for any plugins");
    }

    $pathtozip = $files[0]->copy_content_to_temp();

    // If $names are not specified, set it to the list of missing+problem plugins.
    if (!$names) {
        $names = $model->get_all_plugins_to_install();
    }

    foreach ($names as $name) {
        $pluginname = preg_split('/@/', $name)[0];
        $pluginversion = preg_split('/@/', $name)[1] ?? null;
        $clihelper->cli_writeln("\n=== Plugin $pluginname ===");
        // If $names are specified, make sure they are present in the backup.
        if ($info = $model->is_plugin_code_included($pluginname)) {
            if ($pluginversion !== null && $pluginversion !== (string)$info['version']) {
                $clihelper->cli_writeln('ERROR. Plugin '.$pluginname.' has version '.$info['version'].
                    ' in the backup, which does not match requested '.$pluginversion);
            } else if (core_component::get_component_directory($pluginname) && !$overwrite) {
                $clihelper->cli_writeln('SKIPPED. Plugin ' . $pluginname .
                    ' is already installed and the --overwrite option is not specified.');
            } else {
                $clihelper->cli_writeln($model->prepare_codeincluded_version_description($pluginname, $info, false));
                flush();
                $installedcount += (int)plugincode::install_addon_from_backup($model, $pluginname, (bool)$precheckonly);
            }
        } else {
            $clihelper->cli_writeln('ERROR. Plugin not found in the backup.');
        }
    }

    @unlink($pathtozip);

} else {

    // Installing from moodle.org.
    $currentversion = moodle_major_version();
    foreach ($names as $name) {
        $pluginname = preg_split('/@/', $name)[0];
        $clihelper->cli_writeln("\n=== Plugin $pluginname ===");
        if (core_component::get_component_directory($pluginname) && !$overwrite) {
            $clihelper->cli_writeln('SKIPPED. Plugin ' . $pluginname .
                ' is already installed and the --overwrite option is not specified.');
            continue;
        }
        try {
            $shortinfo = plugincode::check_on_moodle_org($name);
        } catch (moodle_exception $e) {
            $clihelper->cli_writeln('ERROR. Plugin '.$name.' is not found on moodle.org');
            continue;
        }

        $clihelper->cli_writeln(plugins_restore::prepare_moodleorg_version_description($pluginname, $shortinfo, '', false));
        flush();
        $installedcount += (int)plugincode::install_addon_from_moodleorg(
            $shortinfo['downloadurl'], $pluginname, (bool)$precheckonly);
    }

}

$clihelper->cli_writeln('');
if ($installedcount > 0 && !$precheckonly) {
    $clihelper->cli_writeln($installedcount." plugins were added to the codebase. Now you must run Moodle upgrade script ".
        "to complete installation.\nUpgrade will start if you visit 'Site administration' page on your site as an admin or ".
        "you can execute it from CLI.");
    $clihelper->cli_writeln('Unlike this script the upgrade script must be executed as a www user:');
    $clihelper->cli_writeln('');
    $clihelper->cli_writeln("    sudo -u www-data /usr/bin/php admin/cli/upgrade.php --non-interactive");
} else if ($installedcount) {
    $clihelper->cli_writeln($installedcount.' plugins can be added to the codebase.');
} else {
    $clihelper->cli_writeln('There were no changes to the codebase.');
}
