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
 * CLI script to restore a site
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

use tool_vault\local\cli_helper;
use tool_vault\local\helpers\tempfiles;

require_once(__DIR__ . '/../../../../config.php');

if (moodle_needs_upgrading()) {
    cli_error("Moodle upgrade pending, execution suspended.");
}

// Increase time and memory limit.
core_php_time_limit::raise();
raise_memory_limit(MEMORY_HUGE);

$clihelper = new cli_helper(cli_helper::SCRIPT_BACKUP, basename(__FILE__));

if ($clihelper->get_cli_option('help')) {
    $clihelper->print_help();
    die();
}

// Validate all arguments.
$clihelper->validate_cli_options();

if ($clihelper->get_cli_option('dryrun')) {
    // TODO.
    cli_error('Option --dryrun is not yet implemented');
}

$operation = \tool_vault\site_backup::schedule([
    'description' => $clihelper->get_cli_option('description'),
    'passphrase' => $clihelper->get_cli_option('passphrase'),
]);
$operation->start((int)getmypid());
try {
    $operation->execute();
} catch (\Throwable $t) {
    $operation->mark_as_failed($t);
    tempfiles::cleanup();
    die(1);
}

cli_writeln("");
cli_writeln("Backup key: ".$operation->get_backup_key());
