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
 * This file keeps track of upgrades to the lti module
 *
 * @package mod_lti
 * @copyright  2009 Marc Alier, Jordi Piguillem, Nikolas Galanis
 *  marc.alier@upc.edu
 * @copyright  2009 Universitat Politecnica de Catalunya http://www.upc.edu
 * @author     Marc Alier
 * @author     Jordi Piguillem
 * @author     Nikolas Galanis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 defined('MOODLE_INTERNAL') || die;

/**
 * xmldb_lti_upgrade is the function that upgrades
 * the lti module database when is needed
 *
 * This function is automaticly called when version number in
 * version.php changes.
 *
 * @param int $oldversion New old version number.
 *
 * @return boolean
 */
function tool_vault_311_xmldb_lti_upgrade($oldversion) {
    global $CFG, $DB, $OUTPUT;

    $dbman = $DB->get_manager();

    // Automatically generated Moodle v3.6.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2019031300) {
        // Define table lti_access_tokens to be updated.
        $table = new xmldb_table('lti_types');

        // Define field ltiversion to be added to lti_types.
        $field = new xmldb_field('ltiversion', XMLDB_TYPE_CHAR, 10, null, XMLDB_NOTNULL, null, null, 'coursevisible');

        // Conditionally launch add field ltiversion.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $DB->set_field_select('lti_types', 'ltiversion', 'LTI-1p0', 'toolproxyid IS NULL');
            $DB->set_field_select('lti_types', 'ltiversion', 'LTI-2p0', 'toolproxyid IS NOT NULL');
        }

        // Define field clientid to be added to lti_types.
        $field = new xmldb_field('clientid', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'ltiversion');

        // Conditionally launch add field clientid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define index clientid (unique) to be added to lti_types.
        $index = new xmldb_index('clientid', XMLDB_INDEX_UNIQUE, array('clientid'));

        // Conditionally launch add index clientid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Lti savepoint reached.
        upgrade_mod_savepoint(true, 2019031300, 'lti');
    }

    if ($oldversion < 2019031301) {
        // Define table lti_access_tokens to be created.
        $table = new xmldb_table('lti_access_tokens');

        // Adding fields to table lti_access_tokens.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('typeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('scope', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('token', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
        $table->add_field('validuntil', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('lastaccess', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table lti_access_tokens.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('typeid', XMLDB_KEY_FOREIGN, array('typeid'), 'lti_types', array('id'));

        // Add an index.
        $table->add_index('token', XMLDB_INDEX_UNIQUE, array('token'));

        // Conditionally launch create table for lti_access_tokens.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Lti savepoint reached.
        upgrade_mod_savepoint(true, 2019031301, 'lti');
    }

    if ($oldversion < 2019031302) {
        // Define field typeid to be added to lti_tool_settings.
        $table = new xmldb_table('lti_tool_settings');
        $field = new xmldb_field('typeid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'toolproxyid');

        // Conditionally launch add field typeid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define key typeid (foreign) to be added to lti_tool_settings.
        $table = new xmldb_table('lti_tool_settings');
        $key = new xmldb_key('typeid', XMLDB_KEY_FOREIGN, ['typeid'], 'lti_types', ['id']);

        // Launch add key typeid.
        $dbman->add_key($table, $key);

        // Lti savepoint reached.
        upgrade_mod_savepoint(true, 2019031302, 'lti');
    }

    // Automatically generated Moodle v3.7.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.8.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.9.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2020061501) {

        // Changing type of field instructorcustomparameters on table lti to text.
        $table = new xmldb_table('lti');
        $field = new xmldb_field('instructorcustomparameters', XMLDB_TYPE_TEXT, null, null, null, null, null,
                'instructorchoiceallowsetting');

        // Launch change of type for field value.
        $dbman->change_field_type($table, $field);

        // Lti savepoint reached.
        upgrade_mod_savepoint(true, 2020061501, 'lti');
    }

    // Automatically generated Moodle v3.10.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.11.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2021051701) {
        // This option 'Public key type' was added in MDL-66920, but no value was set for existing 1.3 tools.
        // Set a default of 'RSA Key' for those LTI 1.3 tools without a value, representing the only key type they
        // could use at the time of their creation. Existing tools which have since been resaved will not be impacted.
        $sql = "SELECT t.id
                  FROM {lti_types} t
             LEFT JOIN {lti_types_config} tc
                    ON (tc.typeid = t.id AND tc.name = :typename)
                 WHERE t.ltiversion = :ltiversion
                   AND tc.value IS NULL";
        $params = ['typename' => 'keytype', 'ltiversion' => '1.3.0'];
        $recordset = $DB->get_recordset_sql($sql, $params);
        foreach ($recordset as $record) {
            $DB->insert_record('lti_types_config', (object) [
                'typeid' => $record->id,
                'name' => 'keytype',
                'value' => 'RSA_KEY'
            ]);
        }
        $recordset->close();

        // Lti savepoint reached.
        upgrade_mod_savepoint(true, 2021051701, 'lti');
    }

    return true;
}
