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
 * TODO describe file addon_plugins
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

$clihelper->cli_writeln('Hello');

$names = $clihelper->get_cli_option('name');
if ($names !== null) {
    $names = preg_split('/\s*,\s*/', $names, -1, PREG_SPLIT_NO_EMPTY);
    // TODO validate each plugin name.
}

$backupkey = $clihelper->get_cli_option('backupkey');
// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
if ($backupkey !== null) {
    // Make sure we downloaded the dbstructure and codebase.
    /** @var plugins_restore $model */
    $model = plugins_restore::get_last_check_for_backup($backupkey, constants::RESTORE_PRECHECK_MAXAGE);
    if (!$model) {
        $maxage = round(constants::RESTORE_PRECHECK_MAXAGE / DAYSECS) . " days";
        cli_error("No recent restore pre-check for this backup was found. " .
            "You need to execute restore pre-check first. Plugins code from backup will be stored on this site for $maxage\n\n" .
            "Example (without passphrase):\n" .
            "    sudo -u www-data php admin/tool/vault/site_restore.php --backupkey={$backupkey} --dryrun");
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
        $clihelper->cli_writeln("\n=== Plugin $name ===");
        $pluginname = preg_split('/@/', $name)[0];
        $pluginversion = preg_split('/@/', $name)[1] ?? null;
        // If $names are specified, make sure they are present in the backup.
        if ($info = $model->is_plugin_code_included($pluginname)) {
            if ($pluginversion !== null && $pluginversion !== (string)$info['version']) {
                $clihelper->cli_writeln('ERROR. Plugin '.$pluginname.' has version '.$info['version'].
                    ' in the backup, it does not match requested '.$pluginversion);
            } else if (core_component::get_component_directory($pluginname) && !$overwrite) {
                $clihelper->cli_writeln('SKIP. Plugin ' . $pluginname .
                    ' is already installed and the --overwrite option is not specified.');
            } else {
                plugincode::install_addon_from_backup($pathtozip, $pluginname, (bool)$precheckonly);
            }
        } else {
            $clihelper->cli_writeln('ERROR. Plugin not found in the backup.');
        }
    }
    exit;
}

$currentversion = moodle_major_version();
foreach ($names as $name) {
    $clihelper->cli_writeln("\n=== Plugin $name ===");
    $pluginname = preg_split('/@/', $name)[0];
    if (core_component::get_component_directory($pluginname) && !$overwrite) {
        $clihelper->cli_writeln('SKIP. Plugin ' . $pluginname .
            ' is already installed and the --overwrite option is not specified.');
        continue;
    }
    try {
        $shortinfo = plugincode::check_on_moodle_org($name);
    } catch (moodle_exception $e) {
        $clihelper->cli_writeln('ERROR. Plugin '.$name.' is not found on moodle.org');
        continue;
    }

    $moodleversions = join(', ', $shortinfo['supportedmoodles']);
    $clihelper->cli_writeln('Plugin '.$pluginname.' for Moodle '.$moodleversions.' is found on moodle.org');
    if (!in_array($currentversion, $shortinfo['supportedmoodles'])) {
        $clihelper->cli_writeln('WARNING. Current Moodle version '.$currentversion.' is not supported!');
    }
    flush();
    plugincode::install_addon_from_moodleorg($shortinfo['downloadurl'], $pluginname, (bool)$precheckonly);
}
