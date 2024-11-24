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
// the data module
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

function tool_vault_31_xmldb_data_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    // Moodle v2.8.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2015030900) {
        // Define field required to be added to data_fields.
        $table = new xmldb_table('data_fields');
        $field = new xmldb_field('required', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'description');

        // Conditionally launch add field required.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2015030900, 'data');
    }

    // Moodle v2.9.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2015092200) {

        // Define field manageapproved to be added to data.
        $table = new xmldb_table('data');
        $field = new xmldb_field('manageapproved', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1', 'approval');

        // Conditionally launch add field manageapproved.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Data savepoint reached.
        upgrade_mod_savepoint(true, 2015092200, 'data');
    }

    // Moodle v3.0.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2016030300) {

        // Define field timemodified to be added to data.
        $table = new xmldb_table('data');
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'notification');

        // Conditionally launch add field timemodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Data savepoint reached.
        upgrade_mod_savepoint(true, 2016030300, 'data');
    }


    // Moodle v3.1.0 release upgrade line.
    // Put any upgrade step following this.

    return true;
}
