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
 * Logging support.
 *
 * @package    tool_log
 * @copyright  2014 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion
 * @return bool always true
 */
function tool_vault_27_xmldb_tool_log_upgrade($oldversion) {
    global $CFG, $DB, $OUTPUT;

    $dbman = $DB->get_manager();

    if ($oldversion < 2014040600) {
        // Reset logging defaults in dev branches,
        // in production upgrade the install.php is executed instead.
        tool_vault_27_xmldb_tool_log_install();
        upgrade_plugin_savepoint(true, 2014040600, 'tool', 'log');
    }

    // Moodle v2.7.0 release upgrade line.
    // Put any upgrade step following this.

    return true;
}

/**
 * Install the plugin.
 */
function tool_vault_27_xmldb_tool_log_install() {
    global $CFG, $DB;

    $enabled = array();

    // Add data to new log only from now on.
    if (file_exists("$CFG->dirroot/$CFG->admin/tool/log/store/standard")) {
        $enabled[] = 'logstore_standard';
    }

    // Enable legacy log reading, but only if there are existing data.
    if (file_exists("$CFG->dirroot/$CFG->admin/tool/log/store/legacy")) {
        unset_config('loglegacy', 'logstore_legacy');
        // Do not enabled legacy logging if somebody installed a new
        // site and in less than one day upgraded to 2.7.
        $params = array('yesterday' => time() - 60*60*24);
        if ($DB->record_exists_select('log', "time < :yesterday", $params)) {
            $enabled[] = 'logstore_legacy';
        }
    }

    set_config('enabled_stores', implode(',', $enabled), 'tool_log');
}
