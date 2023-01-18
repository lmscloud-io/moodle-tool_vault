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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin upgrade steps are defined here.
 *
 * @package     tool_vault
 * @category    upgrade
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute tool_vault upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_tool_vault_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2022070900) {

        // Define table tool_vault_checks to be created.
        $table = new xmldb_table('tool_vault_checks');

        // Adding fields to table tool_vault_checks.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('type', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('details', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table tool_vault_checks.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for tool_vault_checks.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Vault savepoint reached.
        upgrade_plugin_savepoint(true, 2022070900, 'tool', 'vault');
    }

    if ($oldversion < 2022071500) {

        // Define table tool_vault_operation to be created.
        $table = new xmldb_table('tool_vault_operation');

        // Adding fields to table tool_vault_operation.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('type', XMLDB_TYPE_CHAR, '32', null, null, null, null);
        $table->add_field('backupkey', XMLDB_TYPE_CHAR, '120', null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('remotedetails', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('details', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table tool_vault_operation.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table tool_vault_operation.
        $table->add_index('typestatustime', XMLDB_INDEX_NOTUNIQUE, ['type', 'status', 'timecreated']);
        $table->add_index('backupkey', XMLDB_INDEX_NOTUNIQUE, ['backupkey', 'type']);

        // Conditionally launch create table for tool_vault_operation.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Vault savepoint reached.
        upgrade_plugin_savepoint(true, 2022071500, 'tool', 'vault');
    }

    if ($oldversion < 2022071501) {

        // Define table tool_vault_log to be created.
        $table = new xmldb_table('tool_vault_log');

        // Adding fields to table tool_vault_log.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('operationid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('loglevel', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('message', XMLDB_TYPE_CHAR, '1333', null, null, null, null);

        // Adding keys to table tool_vault_log.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('operationid', XMLDB_KEY_FOREIGN, ['operationid'], 'tool_vault_operation', ['id']);

        // Adding indexes to table tool_vault_log.
        $table->add_index('operationtime', XMLDB_INDEX_NOTUNIQUE, ['operationid', 'timecreated']);

        // Conditionally launch create table for tool_vault_log.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Vault savepoint reached.
        upgrade_plugin_savepoint(true, 2022071501, 'tool', 'vault');
    }

    if ($oldversion < 2022071502) {

        // Define field accesskey to be added to tool_vault_operation.
        $table = new xmldb_table('tool_vault_operation');
        $field = new xmldb_field('accesskey', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'details');

        // Conditionally launch add field accesskey.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define index accesskey (unique) to be added to tool_vault_operation.
        $table = new xmldb_table('tool_vault_operation');
        $index = new xmldb_index('accesskey', XMLDB_INDEX_UNIQUE, ['accesskey']);

        // Conditionally launch add index accesskey.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Vault savepoint reached.
        upgrade_plugin_savepoint(true, 2022071502, 'tool', 'vault');
    }

    if ($oldversion < 2022071510) {

        // Define table tool_vault_backups to be dropped.
        $table = new xmldb_table('tool_vault_backups');

        // Conditionally launch drop table for tool_vault_backups.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Define table tool_vault_restores to be dropped.
        $table = new xmldb_table('tool_vault_restores');

        // Conditionally launch drop table for tool_vault_restores.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Define table tool_vault_checks to be dropped.
        $table = new xmldb_table('tool_vault_checks');

        // Conditionally launch drop table for tool_vault_checks.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Vault savepoint reached.
        upgrade_plugin_savepoint(true, 2022071510, 'tool', 'vault');
    }

    if ($oldversion < 2022071702) {

        \tool_vault\api::insert_default_config();

        // Vault savepoint reached.
        upgrade_plugin_savepoint(true, 2022071702, 'tool', 'vault');
    }

    if ($oldversion < 2022072303) {

        // Define table tool_vault_backup_files to be dropped.
        $table = new xmldb_table('tool_vault_backup_files');

        // Conditionally launch drop table for tool_vault_backup_files.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Define table tool_vault_backup_file to be created.
        $table = new xmldb_table('tool_vault_backup_file');

        // Adding fields to table tool_vault_backup_file.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('operationid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('filetype', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('seq', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $table->add_field('filesize', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('origsize', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('details', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('etag', XMLDB_TYPE_CHAR, '40', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table tool_vault_backup_file.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('operation', XMLDB_KEY_FOREIGN, ['operationid'], 'tool_vault_operation', ['id']);

        // Conditionally launch create table for tool_vault_backup_file.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Vault savepoint reached.
        upgrade_plugin_savepoint(true, 2022072303, 'tool', 'vault');
    }

    if ($oldversion < 2022072304) {

        // Changing type of field loglevel on table tool_vault_log to char.
        $table = new xmldb_table('tool_vault_log');
        $field = new xmldb_field('loglevel', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'info', 'timecreated');

        // Launch change of type for field loglevel.
        $dbman->change_field_type($table, $field);

        // Define field pid to be added to tool_vault_log.
        $table = new xmldb_table('tool_vault_log');
        $field = new xmldb_field('pid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'message');

        // Conditionally launch add field pid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Vault savepoint reached.
        upgrade_plugin_savepoint(true, 2022072304, 'tool', 'vault');
    }

    if ($oldversion < 2022073000) {

        // Define field parentid to be added to tool_vault_operation.
        $table = new xmldb_table('tool_vault_operation');
        $field = new xmldb_field('parentid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'status');

        // Conditionally launch add field parentid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define key parentid (foreign) to be added to tool_vault_operation.
        $table = new xmldb_table('tool_vault_operation');
        $key = new xmldb_key('parentid', XMLDB_KEY_FOREIGN, ['parentid'], 'tool_vault_operation', ['id']);

        // Launch add key parentid.
        $dbman->add_key($table, $key);

        // Vault savepoint reached.
        upgrade_plugin_savepoint(true, 2022073000, 'tool', 'vault');
    }

    if ($oldversion < 2023011800) {

        // Define table tool_vault_table_files_data to be created.
        $table = new xmldb_table('tool_vault_table_files_data');

        // Adding fields to table tool_vault_table_files_data.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('restoreid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('contenthash', XMLDB_TYPE_CHAR, '40', null, null, null, null);

        // Adding keys to table tool_vault_table_files_data.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table tool_vault_table_files_data.
        $table->add_index('contenthash', XMLDB_INDEX_UNIQUE, ['restoreid', 'contenthash']);

        // Conditionally launch create table for tool_vault_table_files_data.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Vault savepoint reached.
        upgrade_plugin_savepoint(true, 2023011800, 'tool', 'vault');
    }

    return true;
}
