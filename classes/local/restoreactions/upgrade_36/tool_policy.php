<?php
// This file is part of Moodle - https://moodle.org/
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
 * Plugin upgrade steps are defined here.
 *
 * @package     tool_policy
 * @category    upgrade
 * @copyright   2018 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute the plugin upgrade steps from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function tool_vault_36_xmldb_tool_policy_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2018082900) {
        // Add field agreementstyle to the table tool_policy_versions.
        $table = new xmldb_table('tool_policy_versions');
        $field = new xmldb_field('agreementstyle', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0', 'policyid');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2018082900, 'tool', 'policy');
    }

    if ($oldversion < 2018091800) {
        // Add field "optional" to the table "tool_policy_versions".
        $table = new xmldb_table('tool_policy_versions');
        $field = new xmldb_field('optional', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0', 'agreementstyle');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2018091800, 'tool', 'policy');
    }

    // Automatically generated Moodle v3.6.0 release upgrade line.
    // Put any upgrade step following this.

    return true;
}
