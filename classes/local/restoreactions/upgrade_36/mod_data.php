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

defined('MOODLE_INTERNAL') || die();

function tool_vault_36_xmldb_data_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2016090600) {

        // Define field config to be added to data.
        $table = new xmldb_table('data');
        $field = new xmldb_field('config', XMLDB_TYPE_TEXT, null, null, null, null, null, 'timemodified');

        // Conditionally launch add field config.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Data savepoint reached.
        upgrade_mod_savepoint(true, 2016090600, 'data');
    }

    // Automatically generated Moodle v3.2.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2017032800) {

        // Define field completionentries to be added to data. Require a number of entries to be considered complete.
        $table = new xmldb_table('data');
        $field = new xmldb_field('completionentries', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'config');

        // Conditionally launch add field timemodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Data savepoint reached.
        upgrade_mod_savepoint(true, 2017032800, 'data');
    }

    // Automatically generated Moodle v3.3.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.4.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.5.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.6.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2018120301) {

        $columns = $DB->get_columns('data');

        $oldclass = "mod-data-default-template ##approvalstatus##";
        $newclass = "mod-data-default-template ##approvalstatusclass##";

        // Update existing classes.
        $DB->replace_all_text('data', $columns['singletemplate'], $oldclass, $newclass);
        $DB->replace_all_text('data', $columns['listtemplate'], $oldclass, $newclass);
        $DB->replace_all_text('data', $columns['addtemplate'], $oldclass, $newclass);
        $DB->replace_all_text('data', $columns['rsstemplate'], $oldclass, $newclass);
        $DB->replace_all_text('data', $columns['asearchtemplate'], $oldclass, $newclass);

        // Data savepoint reached.
        upgrade_mod_savepoint(true, 2018120301, 'data');
    }

    return true;
}
