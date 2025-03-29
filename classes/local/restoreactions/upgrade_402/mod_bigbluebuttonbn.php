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
 * Upgrade logic.
 *
 * @package   mod_bigbluebuttonbn
 * @copyright 2010 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 * @author    Fred Dixon  (ffdixon [at] blindsidenetworks [dt] com)
 */

use mod_bigbluebuttonbn\plugin;
use mod_bigbluebuttonbn\local\config;
use mod_bigbluebuttonbn\task\upgrade_recordings_task;

/**
 * Performs data migrations and updates on upgrade.
 *
 * @param int $oldversion
 * @return bool
 */
function tool_vault_402_xmldb_bigbluebuttonbn_upgrade($oldversion = 0) {
    global $DB;
    $dbman = $DB->get_manager();

    // Automatically generated Moodle v4.1.0 release upgrade line.
    // Put any upgrade step following this.
    if ($oldversion < 2023011800) {

        // Define index course_bbbid_ix (not unique) to be added to bigbluebuttonbn_logs.
        $table = new xmldb_table('bigbluebuttonbn_logs');
        $index = new xmldb_index('course_bbbid_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'bigbluebuttonbnid']);

        // Conditionally launch add index course_bbbid_ix.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Bigbluebuttonbn savepoint reached.
        upgrade_mod_savepoint(true, 2023011800, 'bigbluebuttonbn');
    }
    if ($oldversion < 2023021300) {

        // Define field lockedlayout to be dropped from bigbluebuttonbn.
        $table = new xmldb_table('bigbluebuttonbn');
        $field = new xmldb_field('lockedlayout');

        // Conditionally launch drop field lockedlayout.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Bigbluebuttonbn savepoint reached.
        upgrade_mod_savepoint(true, 2023021300, 'bigbluebuttonbn');
    }

    // Automatically generated Moodle v4.2.0 release upgrade line.
    // Put any upgrade step following this.

    return true;
}
