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

defined('MOODLE_INTERNAL') || die();

function tool_vault_311_xmldb_data_upgrade($oldversion) {
    global $CFG, $DB;

    // Automatically generated Moodle v3.6.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.7.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2019052001) {

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
        upgrade_mod_savepoint(true, 2019052001, 'data');
    }
    // Automatically generated Moodle v3.8.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.9.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.10.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.11.0 release upgrade line.
    // Put any upgrade step following this.

    return true;
}
