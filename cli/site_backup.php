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
 * CLI script to backup a site
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 define('CLI_SCRIPT', true);
 define('TOOL_VAULT_CLI_SCRIPT', true);

use tool_vault\local\cli_helper;
use tool_vault\output\start_backup_popup;

require_once(__DIR__ . '/../../../../config.php');

require_once("{$CFG->dirroot}/{$CFG->admin}/tool/vault/autoloader.php");

if (moodle_needs_upgrading()) {
    cli_error("Moodle upgrade pending, execution suspended.");
}

if (!function_exists('cli_writeln')) {
    function cli_writeln($message) {
        echo $message . PHP_EOL;
    }
}

if (!function_exists('cli_write')) {
    function cli_write($message) {
        echo $message;
    }
}

// Increase time and memory limit.
@set_time_limit(0);
raise_memory_limit(MEMORY_HUGE);

$clihelper = new cli_helper(cli_helper::SCRIPT_BACKUP, basename(__FILE__));

if ($clihelper->get_cli_option('help')) {
    $clihelper->print_help();
    die();
}

// Validate all arguments.
$clihelper->validate_cli_options();
$precheckonly = $clihelper->get_cli_option('dryrun');

if ($precheckonly) {
    /** @var \tool_vault\site_backup_dryrun $operation */
    $operation = \tool_vault\site_backup_dryrun::schedule();
} else {
    /** @var \tool_vault\site_backup $operation */
    $operation = \tool_vault\site_backup::schedule([
        'description' => $clihelper->get_cli_option('description'),
        'passphrase' => $clihelper->get_cli_option('passphrase'),
        'bucket' => $clihelper->get_cli_option('storage'),
        'expiredays' => clean_param($clihelper->get_cli_option('expiredays'), PARAM_INT),
    ]);
}

if (!$operation->safe_start_and_execute((int)getmypid())) {
    die(1);
}

cli_writeln("");
$details = $operation->get_model()->get_details() + ['precheckresults' => []];
if ($precheckonly && ($precheckresults = $details['precheckresults'])) {
    (new start_backup_popup($precheckresults))->display_in_cli($clihelper);
} else {
    cli_writeln("Backup key: ".$operation->get_backup_key());
}
