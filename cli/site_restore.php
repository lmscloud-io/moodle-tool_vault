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
define('TOOL_VAULT_CLI_SCRIPT', true);

use tool_vault\api;
use tool_vault\local\cli_helper;
use tool_vault\local\exceptions\api_exception;
use tool_vault\local\helpers\tempfiles;

require_once(__DIR__ . '/../../../../config.php');

// Increase time and memory limit.
@set_time_limit(0);
raise_memory_limit(MEMORY_HUGE);

$clihelper = new cli_helper(cli_helper::SCRIPT_RESTORE, basename(__FILE__));

if ($clihelper->get_cli_option('help')) {
    $clihelper->print_help();
    die();
}

// Validate all arguments.
$clihelper->validate_cli_options();

$params = [
    'backupkey' => $clihelper->get_cli_option('backupkey'),
    'passphrase' => $clihelper->get_cli_option('passphrase'),
];

try {
    api::validate_backup($params['backupkey'] ?? '', $params['passphrase'] ?? '');
} catch (api_exception $e) {
    cli_error($e->getMessage());
}

// Run restore.
if ($clihelper->get_cli_option('dryrun')) {
    $operation = \tool_vault\site_restore_dryrun::schedule($params);
} else {
    if (!api::are_restores_allowed()) {
        cli_error('Restores are not allowed on this site. ' .
            'You can enable site restore for this CLI script by adding the option --allow-restore');
    }
    $operation = \tool_vault\site_restore::schedule($params);
}

try {
    $operation->start((int)getmypid());
    $operation->execute();
} catch (\Throwable $t) {
    $operation->mark_as_failed($t);
    tempfiles::cleanup();
    die(1);
}

// TODO for dry-run - do not fail, print results of pre-checks.
