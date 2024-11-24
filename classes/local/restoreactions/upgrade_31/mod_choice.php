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
// Mdlcode-disable missing-docblock.

// This file keeps track of upgrades to
// the choice module
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installation to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the methods of database_manager class
//
// Please do not forget to use upgrade_set_timeout()
// before any action that may take longer time to finish.

defined('MOODLE_INTERNAL') || die();

function tool_vault_31_xmldb_choice_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2014051201) {

        // Define field allowmultiple to be added to choice.
        $table = new xmldb_table('choice');
        $field = new xmldb_field('allowmultiple', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'allowupdate');

        // Conditionally launch add field allowmultiple.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Choice savepoint reached.
        upgrade_mod_savepoint(true, 2014051201, 'choice');
    }

    // Moodle v2.8.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2014111001) {

        // Define field showpreview to be added to choice.
        $table = new xmldb_table('choice');
        $field = new xmldb_field('showpreview', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'timeclose');

        // Conditionally launch add field showpreview.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Choice savepoint reached.
        upgrade_mod_savepoint(true, 2014111001, 'choice');
    }

    if ($oldversion < 2014111002) {

        // Define field includeinactive to be added to choice.
        $table = new xmldb_table('choice');
        $field = new xmldb_field('includeinactive', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'showunanswered');

        // Conditionally launch add field includeactive.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Choice savepoint reached.
        upgrade_mod_savepoint(true, 2014111002, 'choice');
    }

    // Moodle v2.9.0 release upgrade line.
    // Put any upgrade step following this.

    // Moodle v3.0.0 release upgrade line.
    // Put any upgrade step following this.

    // Moodle v3.1.0 release upgrade line.
    // Put any upgrade step following this.

    return true;
}
