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
 * This file keeps track of upgrades to Moodle.
 *
 * Sometimes, changes between versions involve
 * alterations to database structures and other
 * major things that may break installations.
 *
 * The upgrade function in this file will attempt
 * to perform all the necessary actions to upgrade
 * your older installation to the current version.
 *
 * If there's something it cannot do itself, it
 * will tell you what you need to do.
 *
 * The commands in here will all be database-neutral,
 * using the methods of database_manager class
 *
 * Please do not forget to use upgrade_set_timeout()
 * before any action that may take longer time to finish.
 *
 * @package   core_install
 * @category  upgrade
 * @copyright 2006 onwards Martin Dougiamas  http://dougiamas.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_vault\local\restoreactions\upgrade_311\helpers\general_helper;
use tool_vault\local\restoreactions\upgrade_311\helpers\profilefield_helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Main upgrade tasks to be executed on Moodle version bump
 *
 * @param int $oldversion
 * @return bool always true
 */
function tool_vault_311_core_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    // Automatically generated Moodle v3.9.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2020061500.02) {
        // Update default digital age consent map according to the current legislation on each country.

        // The default age of digital consent map for 38 and below.
        $oldageofdigitalconsentmap = implode(PHP_EOL, [
            '*, 16',
            'AT, 14',
            'ES, 14',
            'US, 13'
        ]);

        // Check if the current age of digital consent map matches the old one.
        if (get_config('moodle', 'agedigitalconsentmap') === $oldageofdigitalconsentmap) {
            // If the site is still using the old defaults, upgrade to the new default.
            $ageofdigitalconsentmap = implode(PHP_EOL, [
                '*, 16',
                'AT, 14',
                'BE, 13',
                'BG, 14',
                'CY, 14',
                'CZ, 15',
                'DK, 13',
                'EE, 13',
                'ES, 14',
                'FI, 13',
                'FR, 15',
                'GB, 13',
                'GR, 15',
                'IT, 14',
                'LT, 14',
                'LV, 13',
                'MT, 13',
                'NO, 13',
                'PT, 13',
                'SE, 13',
                'US, 13'
            ]);
            set_config('agedigitalconsentmap', $ageofdigitalconsentmap);
        }

        upgrade_main_savepoint(true, 2020061500.02);
    }

    if ($oldversion < 2020062600.01) {
        // Add index to the token field in the external_tokens table.
        $table = new xmldb_table('external_tokens');
        $index = new xmldb_index('token', XMLDB_INDEX_NOTUNIQUE, ['token']);

        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_main_savepoint(true, 2020062600.01);
    }

    if ($oldversion < 2020071100.01) {
        // Clean up completion criteria records referring to NULL course prerequisites.
        $select = 'criteriatype = :type AND courseinstance IS NULL';
        $params = ['type' => 8]; // COMPLETION_CRITERIA_TYPE_COURSE.

        $DB->delete_records_select('course_completion_criteria', $select, $params);

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2020071100.01);
    }

    if ($oldversion < 2020072300.01) {
        // Restore and set the guest user if it has been previously removed via GDPR, or set to an nonexistent
        // user account.
        $currentguestuser = $DB->get_record('user', array('id' => $CFG->siteguest));

        if (!$currentguestuser) {
            if (!$guest = $DB->get_record('user', array('username' => 'guest', 'mnethostid' => $CFG->mnet_localhost_id))) {
                // Create a guest user account.
                $guest = new stdClass();
                $guest->auth        = 'manual';
                $guest->username    = 'guest';
                $guest->password    = hash_internal_user_password('guest');
                $guest->firstname   = get_string('guestuser');
                $guest->lastname    = ' ';
                $guest->email       = 'root@localhost';
                $guest->description = get_string('guestuserinfo');
                $guest->mnethostid  = $CFG->mnet_localhost_id;
                $guest->confirmed   = 1;
                $guest->lang        = $CFG->lang;
                $guest->timemodified= time();
                $guest->id = $DB->insert_record('user', $guest);
            }
            // Set the guest user.
            set_config('siteguest', $guest->id);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2020072300.01);
    }

    if ($oldversion < 2020081400.01) {
        // Delete all user evidence files from users that have been deleted.
        $sql = "SELECT DISTINCT f.*
                  FROM {files} f
             LEFT JOIN {context} c ON f.contextid = c.id
                 WHERE f.component = :component
                   AND f.filearea = :filearea
                   AND c.id IS NULL";
        $stalefiles = $DB->get_records_sql($sql, ['component' => 'core_competency', 'filearea' => 'userevidence']);

        $fs = get_file_storage();
        foreach ($stalefiles as $stalefile) {
            $fs->get_file_instance($stalefile)->delete();
        }

        upgrade_main_savepoint(true, 2020081400.01);
    }

    if ($oldversion < 2020081400.02) {

        // Define field timecreated to be added to task_adhoc.
        $table = new xmldb_table('task_adhoc');
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'blocking');

        // Conditionally launch add field timecreated.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2020081400.02);
    }

    if ($oldversion < 2020082200.01) {
        // Define field metadatasettings to be added to h5p_libraries.
        $table = new xmldb_table('h5p_libraries');
        $field = new xmldb_field('metadatasettings', XMLDB_TYPE_TEXT, null, null, null, null, null, 'coreminor');

        // Conditionally launch add field metadatasettings.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Get installed library files that have no metadata settings value.
        $params = [
            'component' => 'core_h5p',
            'filearea' => 'libraries',
            'filename' => 'library.json',
        ];
        $sql = "SELECT l.id, f.id as fileid
                  FROM {files} f
             LEFT JOIN {h5p_libraries} l ON f.itemid = l.id
                 WHERE f.component = :component
                       AND f.filearea = :filearea
                       AND f.filename = :filename";
        $libraries = $DB->get_records_sql($sql, $params);

        // Update metadatasettings field when the attribute is present in the library.json file.
        $fs = get_file_storage();
        foreach ($libraries as $library) {
            $jsonfile = $fs->get_file_by_id($library->fileid);
            $jsoncontent = json_decode($jsonfile->get_content());
            if (isset($jsoncontent->metadataSettings)) {
                unset($library->fileid);
                $library->metadatasettings = json_encode($jsoncontent->metadataSettings);
                $DB->update_record('h5p_libraries', $library);
            }
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2020082200.01);
    }

    if ($oldversion < 2020082200.02) {
        // Define fields to be added to task_scheduled.
        $table = new xmldb_table('task_scheduled');
        $field = new xmldb_field('timestarted', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'disabled');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('hostname', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'timestarted');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('pid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'hostname');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define fields to be added to task_adhoc.
        $table = new xmldb_table('task_adhoc');
        $field = new xmldb_field('timestarted', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'blocking');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('hostname', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'timestarted');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('pid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'hostname');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define fields to be added to task_log.
        $table = new xmldb_table('task_log');
        $field = new xmldb_field('hostname', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'output');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('pid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'hostname');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2020082200.02);
    }

    if ($oldversion < 2020082200.03) {
        // Define table to store virus infected details.
        $table = new xmldb_table('infected_files');

        // Adding fields to table infected_files.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('filename', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('quarantinedfile', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('reason', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table infected_files.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Conditionally launch create table for infected_files.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_main_savepoint(true, 2020082200.03);
    }

    if ($oldversion < 2020091000.02) {
        // Remove all the files with component='core_h5p' and filearea='editor' because they won't be used anymore.
        $fs = get_file_storage();
        $syscontext = context_system::instance();
        $fs->delete_area_files($syscontext->id, 'core_h5p', 'editor');

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2020091000.02);
    }

    if ($oldversion < 2020091800.01) {
        // Copy From id captures the id of the source course when a new course originates from a restore
        // of another course on the same site.
        $table = new xmldb_table('course');
        $field = new xmldb_field('originalcourseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2020091800.01);
    }

    if ($oldversion < 2020100200.01) {
        // Define table oauth2_refresh_token to be created.
        $table = new xmldb_table('oauth2_refresh_token');

        // Adding fields to table oauth2_refresh_token.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('issuerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('token', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('scopehash', XMLDB_TYPE_CHAR, 40, null, XMLDB_NOTNULL, null, null);

        // Adding keys to table oauth2_refresh_token.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('issueridkey', XMLDB_KEY_FOREIGN, ['issuerid'], 'oauth2_issuer', ['id']);
        $table->add_key('useridkey', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Adding indexes to table oauth2_refresh_token.
        $table->add_index('userid-issuerid-scopehash', XMLDB_INDEX_UNIQUE, array('userid', 'issuerid', 'scopehash'));

        // Conditionally launch create table for oauth2_refresh_token.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2020100200.01);
    }

    if ($oldversion < 2020100700.00) {

        // Define index modulename-instance-eventtype (not unique) to be added to event.
        $table = new xmldb_table('event');
        $index = new xmldb_index('modulename-instance-eventtype', XMLDB_INDEX_NOTUNIQUE, ['modulename', 'instance', 'eventtype']);

        // Conditionally launch add index modulename-instance-eventtype.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index modulename-instance (not unique) to be dropped form event.
        $table = new xmldb_table('event');
        $index = new xmldb_index('modulename-instance', XMLDB_INDEX_NOTUNIQUE, ['modulename', 'instance']);

        // Conditionally launch drop index modulename-instance.
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2020100700.00);
    }

    if ($oldversion < 2020101300.01) {
        // Define fields tutorial and example to be added to h5p_libraries.
        $table = new xmldb_table('h5p_libraries');

        // Add tutorial field.
        $field = new xmldb_field('tutorial', XMLDB_TYPE_TEXT, null, null, null, null, null, 'metadatasettings');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add example field.
        $field = new xmldb_field('example', XMLDB_TYPE_TEXT, null, null, null, null, null, 'tutorial');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2020101300.01);
    }

    if ($oldversion < 2020101600.01) {
        // Delete orphaned course_modules_completion rows; these were not deleted properly
        // by remove_course_contents function.
        $DB->delete_records_select('course_modules_completion', "
                NOT EXISTS (
                        SELECT 1
                          FROM {course_modules} cm
                         WHERE cm.id = {course_modules_completion}.coursemoduleid
                )");
        upgrade_main_savepoint(true, 2020101600.01);
    }

    if ($oldversion < 2020101600.02) {
        // Script to fix incorrect records of "hidden" field in existing grade items.
        $sql = "SELECT cm.instance, cm.course
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE m.name = :module AND cm.visible = :visible";
        $hidequizlist = $DB->get_recordset_sql($sql, ['module' => 'quiz', 'visible' => 0]);

        foreach ($hidequizlist as $hidequiz) {
            $params = [
                'itemmodule'    => 'quiz',
                'courseid'      => $hidequiz->course,
                'iteminstance'  => $hidequiz->instance,
            ];

            $DB->set_field('grade_items', 'hidden', 1, $params);
        }
        $hidequizlist->close();

        upgrade_main_savepoint(true, 2020101600.02);
    }

    if ($oldversion < 2020102100.01) {
        // Get the current guest user which is also set as 'deleted'.
        $guestuser = $DB->get_record('user', ['id' => $CFG->siteguest, 'deleted' => 1]);
        // If there is a deleted guest user, reset the user to not be deleted and make sure the related
        // user context exists.
        if ($guestuser) {
            $guestuser->deleted = 0;
            $DB->update_record('user', $guestuser);

            // Get the guest user context.
            $guestusercontext = $DB->get_record('context',
                ['contextlevel' => CONTEXT_USER, 'instanceid' => $guestuser->id]);

            // If the guest user context does not exist, create it.
            if (!$guestusercontext) {
                $record = new stdClass();
                $record->contextlevel = CONTEXT_USER;
                $record->instanceid = $guestuser->id;
                $record->depth = 0;
                // The path is not known before insert.
                $record->path = null;
                $record->locked = 0;

                $record->id = $DB->insert_record('context', $record);

                // Update the path.
                $record->path = '/' . SYSCONTEXTID . '/' . $record->id;
                $record->depth = substr_count($record->path, '/');
                $DB->update_record('context', $record);
            }
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2020102100.01);
    }

    if ($oldversion < 2020102100.02) {
        // Reset analytics model output dir if it's the default value.
        $modeloutputdir = get_config('analytics', 'modeloutputdir');
        if (strcasecmp($modeloutputdir, $CFG->dataroot . DIRECTORY_SEPARATOR . 'models') == 0) {
            set_config('modeloutputdir', '', 'analytics');
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2020102100.02);
    }

    if ($oldversion < 2020102300.01) {
        // Define field downloadcontent to be added to course.
        $table = new xmldb_table('course');
        $field = new xmldb_field('downloadcontent', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'visibleold');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2020102300.01);
    }

    if ($oldversion < 2020102300.02) {
        $table = new xmldb_table('badge_backpack');

        // There is no key_exists, so test the equivalent index.
        $oldindex = new xmldb_index('backpackcredentials', XMLDB_KEY_UNIQUE, ['userid', 'externalbackpackid']);
        if (!$dbman->index_exists($table, $oldindex)) {
            // All external backpack providers/hosts are now exclusively stored in badge_external_backpack.
            // All credentials are stored in badge_backpack and are unique per user, backpack.
            $uniquekey = new xmldb_key('backpackcredentials', XMLDB_KEY_UNIQUE, ['userid', 'externalbackpackid']);
            $dbman->add_key($table, $uniquekey);
        }

        // Drop the password field as this is moved to badge_backpack.
        $table = new xmldb_table('badge_external_backpack');
        $field = new xmldb_field('password', XMLDB_TYPE_CHAR, '50');
        if ($dbman->field_exists($table, $field)) {
            // If there is a current backpack set then copy it across to the new structure.
            if ($CFG->badges_defaultissuercontact) {
                // Get the currently used site backpacks.
                $records = $DB->get_records_select('badge_external_backpack', "password IS NOT NULL AND password != ''");
                $backpack = [
                    'userid' => '0',
                    'email' => $CFG->badges_defaultissuercontact,
                    'backpackuid' => -1
                ];

                // Create records corresponding to the site backpacks.
                foreach ($records as $record) {
                    $backpack['password'] = $record->password;
                    $backpack['externalbackpackid'] = $record->id;
                    $DB->insert_record('badge_backpack', (object) $backpack);
                }
            }

            $dbman->drop_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2020102300.02);
    }

    if ($oldversion < 2020102700.04) {

        // Define table payment_accounts to be created.
        $table = new xmldb_table('payment_accounts');

        // Adding fields to table payment_accounts.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('idnumber', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('archived', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table payment_accounts.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for payment_accounts.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table payment_gateways to be created.
        $table = new xmldb_table('payment_gateways');

        // Adding fields to table payment_gateways.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('accountid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('gateway', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('config', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table payment_gateways.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('accountid', XMLDB_KEY_FOREIGN, ['accountid'], 'payment_accounts', ['id']);

        // Conditionally launch create table for payment_gateways.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table payments to be created.
        $table = new xmldb_table('payments');

        // Adding fields to table payments.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('paymentarea', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('amount', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('currency', XMLDB_TYPE_CHAR, '3', null, XMLDB_NOTNULL, null, null);
        $table->add_field('accountid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('gateway', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table payments.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('accountid', XMLDB_KEY_FOREIGN, ['accountid'], 'payment_accounts', ['id']);

        // Adding indexes to table payments.
        $table->add_index('gateway', XMLDB_INDEX_NOTUNIQUE, ['gateway']);
        $table->add_index('component-paymentarea-itemid', XMLDB_INDEX_NOTUNIQUE, ['component', 'paymentarea', 'itemid']);

        // Conditionally launch create table for payments.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2020102700.04);
    }

    // Automatically generated Moodle v3.10.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2020111500.01) {
        // Get all lessons that are set with a completion criteria of 'requires grade' but with no grade type set.
        $sql = "SELECT cm.id
                  FROM {course_modules} cm
                  JOIN {lesson} l ON l.id = cm.instance
                  JOIN {modules} m ON m.id = cm.module
                 WHERE m.name = :name AND cm.completiongradeitemnumber IS NOT NULL AND l.grade = :grade";

        do {
            if ($invalidconfigrations = $DB->get_records_sql($sql, ['name' => 'lesson', 'grade' => 0], 0, 1000)) {
                list($insql, $inparams) = $DB->get_in_or_equal(array_keys($invalidconfigrations), SQL_PARAMS_NAMED);
                $DB->set_field_select('course_modules', 'completiongradeitemnumber', null, "id $insql", $inparams);
            }
        } while ($invalidconfigrations);

        upgrade_main_savepoint(true, 2020111500.01);
    }

    if ($oldversion < 2021013100.00) {
        $DB->delete_records_select('event', "eventtype = 'category' AND categoryid = 0 AND userid <> 0");

        upgrade_main_savepoint(true, 2021013100.00);
    }

    if ($oldversion < 2021021100.01) {
        // Define field visibility to be added to contentbank_content.
        $table = new xmldb_table('contentbank_content');
        $field = new xmldb_field('visibility', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'contextid');

        // Conditionally launch add field visibility.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021021100.01);
    }

    if ($oldversion < 2021021600.00) {

        // We are going to remove the field 'hidepicture' from the groups
        // so we need to remove the pictures from those groups. But we prevent
        // the execution twice because this could be executed again when upgrading
        // to different versions.
        if ($dbman->field_exists('groups', 'hidepicture')) {

            $sql = "SELECT g.id, g.courseid, ctx.id AS contextid
                       FROM {groups} g
                       JOIN {context} ctx
                         ON ctx.instanceid = g.courseid
                        AND ctx.contextlevel = :contextlevel
                      WHERE g.hidepicture = 1";

            // Selecting all the groups that have hide picture enabled, and organising them by context.
            $groupctx = [];
            $records = $DB->get_recordset_sql($sql, ['contextlevel' => CONTEXT_COURSE]);
            foreach ($records as $record) {
                if (!isset($groupctx[$record->contextid])) {
                    $groupctx[$record->contextid] = [];
                }
                $groupctx[$record->contextid][] = $record->id;
            }
            $records->close();

            // Deleting the group files.
            $fs = get_file_storage();
            foreach ($groupctx as $contextid => $groupids) {
                list($in, $inparams) = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED);
                $fs->delete_area_files_select($contextid, 'group', 'icon', $in, $inparams);
            }

            // Updating the database to remove picture from all those groups.
            $sql = "UPDATE {groups} SET picture = :pic WHERE hidepicture = :hide";
            $DB->execute($sql, ['pic' => 0, 'hide' => 1]);
        }

        // Define field hidepicture to be dropped from groups.
        $table = new xmldb_table('groups');
        $field = new xmldb_field('hidepicture');

        // Conditionally launch drop field hidepicture.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021021600.00);
    }

    if ($oldversion < 2021022600.01) {
        // Get all the external backpacks and update the sortorder column, to avoid repeated/wrong values. As sortorder was not
        // used since now, the id column will be the criteria to follow for re-ordering them with a valid value.
        $i = 1;
        $records = $DB->get_records('badge_external_backpack', null, 'id ASC');
        foreach ($records as $record) {
            $record->sortorder = $i++;
            $DB->update_record('badge_external_backpack', $record);
        }

        upgrade_main_savepoint(true, 2021022600.01);
    }

    if ($oldversion < 2021030500.01) {
        // The $CFG->badges_site_backpack setting has been removed because it's not required anymore. From now, the default backpack
        // will be the one with lower sortorder value.
        unset_config('badges_site_backpack');

        upgrade_main_savepoint(true, 2021030500.01);
    }

    if ($oldversion < 2021031200.01) {

        // Define field type to be added to oauth2_issuer.
        $table = new xmldb_table('oauth2_issuer');
        $field = new xmldb_field('servicetype', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'requireconfirmation');

        // Conditionally launch add field type.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Set existing values to the proper servicetype value.
        // It's not critical if the servicetype column doesn't contain the proper value for Google, Microsoft, Facebook or
        // Nextcloud services because, for now, this value is used for services using different discovery method.
        // However, let's try to upgrade it using the default value for the baseurl or image. If any of these default values
        // have been changed, the servicetype column will remain NULL.
        $recordset = $DB->get_recordset('oauth2_issuer');
        foreach ($recordset as $record) {
            if ($record->baseurl == 'https://accounts.google.com/') {
                $record->servicetype = 'google';
                $DB->update_record('oauth2_issuer', $record);
            } else if ($record->image == 'https://www.microsoft.com/favicon.ico') {
                $record->servicetype = 'microsoft';
                $DB->update_record('oauth2_issuer', $record);
            } else if ($record->image == 'https://facebookbrand.com/wp-content/uploads/2016/05/flogo_rgb_hex-brc-site-250.png') {
                $record->servicetype = 'facebook';
                $DB->update_record('oauth2_issuer', $record);
            } else if ($record->image == 'https://nextcloud.com/wp-content/themes/next/assets/img/common/favicon.png?x16328') {
                $record->servicetype = 'nextcloud';
                $DB->update_record('oauth2_issuer', $record);
            }
        }
        $recordset->close();

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021031200.01);
    }

    if ($oldversion < 2021033100.00) {
        // Define field 'showactivitydates' to be added to course table.
        $table = new xmldb_table('course');
        $field = new xmldb_field('showactivitydates', XMLDB_TYPE_INTEGER, '1', null,
            XMLDB_NOTNULL, null, '0', 'originalcourseid');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021033100.00);
    }

    if ($oldversion < 2021033100.01) {
        // Define field 'showcompletionconditions' to be added to course.
        $table = new xmldb_table('course');
        $field = new xmldb_field('showcompletionconditions', XMLDB_TYPE_INTEGER, '1', null,
            XMLDB_NOTNULL, null, '1', 'completionnotify');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021033100.01);
    }

    if ($oldversion < 2021041300.01) {

        // Define field enabled to be added to h5p_libraries.
        $table = new xmldb_table('h5p_libraries');
        $field = new xmldb_field('enabled', XMLDB_TYPE_INTEGER, '1', null, null, null, '1', 'example');

        // Conditionally launch add field enabled.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021041300.01);
    }

    if ($oldversion < 2021042100.00) {

        // Define field loginpagename to be added to oauth2_issuer.
        $table = new xmldb_table('oauth2_issuer');
        $field = new xmldb_field('loginpagename', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'servicetype');

        // Conditionally launch add field loginpagename.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021042100.00);
    }

    if ($oldversion < 2021042100.01) {
        $table = new xmldb_table('user');
        $tablecolumns = ['icq', 'skype', 'aim', 'yahoo', 'msn', 'url'];

        foreach ($tablecolumns as $column) {
            $field = new xmldb_field($column);
            if ($dbman->field_exists($table, $field)) {
                profilefield_helper::user_profile_social_moveto_profilefield($column);
                $dbman->drop_field($table, $field);
            }
        }

        // Update all module availability if it relies on the old user fields.
        profilefield_helper::user_profile_social_update_module_availability();

        // Remove field mapping for oauth2.
        $DB->delete_records('oauth2_user_field_mapping', array('internalfield' => 'url'));

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021042100.01);
    }

    if ($oldversion < 2021042100.02) {

        // Check if this site has executed the problematic upgrade steps.
        $needsfixing = general_helper::upgrade_calendar_site_status(false);

        // Only queue the task if this site has been affected by the problematic upgrade step.
        if ($needsfixing) {

            // Create adhoc task to search and recover orphaned calendar events.
            $record = new \stdClass();
            $record->classname = '\core\task\calendar_fix_orphaned_events';

            // Next run time based from nextruntime computation in \core\task\manager::queue_adhoc_task().
            $nextruntime = time() - 1;
            $record->nextruntime = $nextruntime;
            $DB->insert_record('task_adhoc', $record);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021042100.02);
    }

    if ($oldversion < 2021042400.00) {
        // Changing the default of field showcompletionconditions on table course to 0.
        $table = new xmldb_table('course');
        $field = new xmldb_field('showcompletionconditions', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'showactivitydates');

        // Launch change of nullability for field showcompletionconditions.
        $dbman->change_field_notnull($table, $field);

        // Launch change of default for field showcompletionconditions.
        $dbman->change_field_default($table, $field);

        // Set showcompletionconditions to null for courses which don't track completion.
        $sql = "UPDATE {course}
                   SET showcompletionconditions = null
                 WHERE enablecompletion <> 1";
        $DB->execute($sql);

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021042400.00);
    }

    if ($oldversion < 2021043000.01) {
        // Remove usemodchooser user preference for every user.
        $DB->delete_records('user_preferences', ['name' => 'usemodchooser']);

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021043000.01);
    }

    // Automatically generated Moodle v3.11.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2021051700.03) {

        // Define index name (not unique) to be added to user_preferences.
        $table = new xmldb_table('user_preferences');
        $index = new xmldb_index('name', XMLDB_INDEX_NOTUNIQUE, ['name']);

        // Conditionally launch add index name.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021051700.03);
    }

    if ($oldversion < 2021051700.05) {
        // Update the externalfield to be larger.
        $table = new xmldb_table('oauth2_user_field_mapping');
        $field = new xmldb_field('externalfield', XMLDB_TYPE_CHAR, '500', null, XMLDB_NOTNULL, false, null, 'issuerid');
        $dbman->change_field_type($table, $field);

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021051700.05);
    }

    if ($oldversion < 2021051706.12) {
        // Social custom fields could had been created linked to category id = 1. Let's check category 1 exists.
        if (!$DB->get_record('user_info_category', ['id' => 1])) {
            // Let's check if we have any custom field linked to category id = 1.
            $fields = $DB->get_records('user_info_field', ['categoryid' => 1]);
            if (!empty($fields)) {
                $categoryid = $DB->get_field_sql('SELECT min(id) from {user_info_category}');
                foreach ($fields as $field) {
                    $field->categoryid = $categoryid;
                    $DB->update_record('user_info_field', $field);
                }
            }
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021051706.12);
    }

    if ($oldversion < 2021051707.05) {

        // Changing precision of field hidden on table grade_categories to (10).
        $table = new xmldb_table('grade_categories');
        $field = new xmldb_field('hidden', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timemodified');

        // Launch change of precision for field hidden.
        $dbman->change_field_precision($table, $field);

        // Changing precision of field hidden on table grade_categories_history to (10).
        $table = new xmldb_table('grade_categories_history');
        $field = new xmldb_field('hidden', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'aggregatesubcats');

        // Launch change of precision for field hidden.
        $dbman->change_field_precision($table, $field);

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021051707.05);
    }

    return true;
}
