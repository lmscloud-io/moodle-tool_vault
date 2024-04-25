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
 * CLI script to list remote backups
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

use tool_vault\local\cli_helper;

require_once(__DIR__ . '/../../../../config.php');

// Increase time and memory limit.
core_php_time_limit::raise();
raise_memory_limit(MEMORY_HUGE);

$clihelper = new cli_helper(cli_helper::SCRIPT_LIST, basename(__FILE__));

if ($clihelper->get_cli_option('help')) {
    $clihelper->print_help();
    die();
}

// Validate all arguments.
$clihelper->validate_cli_options();

$PAGE->set_url(\tool_vault\local\helpers\ui::baseurl());
$renderer = $PAGE->get_renderer('tool_vault');

$backups = \tool_vault\api::get_remote_backups(false);
$table = [];
foreach ($backups as $backup) {
    $timestarted = \tool_vault\local\helpers\ui::format_time_cli($backup->timecreated);
    $description = $backup->get_description();
    $table[$backup->backupkey] = trim($timestarted . "\n" . $description) .
        ($backup->get_encrypted() ? "\n" . get_string('withpassphrase', 'tool_vault') : '');
}
$table = array_reverse($table, true);
echo $clihelper->print_table($table);
