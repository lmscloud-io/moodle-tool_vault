<?php
// This file is part of Moodle - http://moodle.org/
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

// phpcs:ignoreFile
// Mdlcode-disable incorrect-package-name.

/**
 * Plugin upgrade code
 *
 * @package    tool_cohortroles
 * @copyright  2020 Paul Holden <paulh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Function to upgrade tool_cohortroles.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function tool_vault_402_xmldb_tool_cohortroles_upgrade($oldversion) {
    global $DB;

    // Automatically generated Moodle v4.1.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v4.2.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2023042401) {
        // Delete any tool_cohortroles mappings for users who no longer exist.
        $DB->delete_records_select('tool_cohortroles', 'userid NOT IN (SELECT id FROM {user} WHERE deleted = 0)');

        // Cohortroles savepoint reached.
        upgrade_plugin_savepoint(true, 2023042401, 'tool', 'cohortroles');
    }

    return true;
}
