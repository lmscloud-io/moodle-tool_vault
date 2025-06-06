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
// Mdlcode-disable unknown-db-tablename.
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

use tool_vault\local\restoreactions\upgrade_401\helpers\adminpresets_helper;
use tool_vault\local\restoreactions\upgrade_401\helpers\general_helper;
use tool_vault\task\after_upgrade_task;

defined('MOODLE_INTERNAL') || die();

/**
 * Main upgrade tasks to be executed on Moodle version bump
 *
 * This function is automatically executed after one bump in the Moodle core
 * version is detected. It's in charge of performing the required tasks
 * to raise core from the previous version to the next one.
 *
 * It's a collection of ordered blocks of code, named "upgrade steps",
 * each one performing one isolated (from the rest of steps) task. Usually
 * tasks involve creating new DB objects or performing manipulation of the
 * information for cleanup/fixup purposes.
 *
 * Each upgrade step has a fixed structure, that can be summarised as follows:
 *
 * if ($oldversion < XXXXXXXXXX.XX) {
 *     // Explanation of the update step, linking to issue in the Tracker if necessary
 *     upgrade_set_timeout(XX); // Optional for big tasks
 *     // Code to execute goes here, usually the XMLDB Editor will
 *     // help you here. See {@link http://docs.moodle.org/dev/XMLDB_editor}.
 *     upgrade_main_savepoint(true, XXXXXXXXXX.XX);
 * }
 *
 * All plugins within Moodle (modules, blocks, reports...) support the existence of
 * their own upgrade.php file, using the "Frankenstyle" component name as
 * defined at {@link http://docs.moodle.org/dev/Frankenstyle}, for example:
 *     - {@see xmldb_page_upgrade($oldversion)}. (modules don't require the plugintype ("mod_") to be used.
 *     - {@see xmldb_auth_manual_upgrade($oldversion)}.
 *     - {@see xmldb_workshopform_accumulative_upgrade($oldversion)}.
 *     - ....
 *
 * In order to keep the contents of this file reduced, it's allowed to create some helper
 * functions to be used here in the upgradelib.php file at the same directory. Note
 * that such a file must be manually included from upgrade.php, and there are some restrictions
 * about what can be used within it.
 *
 * For more information, take a look to the documentation available:
 *     - Data definition API: {@link http://docs.moodle.org/dev/Data_definition_API}
 *     - Upgrade API: {@link http://docs.moodle.org/dev/Upgrade_API}
 *
 * @param int $oldversion
 * @return bool always true
 */
function tool_vault_401_core_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    if ($oldversion < 2021072800.01) {
        // Define table reportbuilder_report to be created.
        $table = new xmldb_table('reportbuilder_report');

        // Adding fields to table reportbuilder_report.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('source', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('type', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('area', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table reportbuilder_report.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usercreated', XMLDB_KEY_FOREIGN, ['usercreated'], 'user', ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        $table->add_key('contextid', XMLDB_KEY_FOREIGN, ['contextid'], 'context', ['id']);

        // Conditionally launch create table for reportbuilder_report.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021072800.01);
    }

    if ($oldversion < 2021090200.01) {
        // Remove qformat_webct (unless it has manually been added back).
        if (!file_exists($CFG->dirroot . '/question/format/webct/format.php')) {
            unset_all_config_for_plugin('qformat_webct');
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021090200.01);
    }

    if ($oldversion < 2021091100.01) {
        // If message_jabber is no longer present, remove it.
        if (!file_exists($CFG->dirroot . '/message/output/jabber/message_output_jabber.php')) {
            // Remove Jabber from the notification plugins list.
            $DB->delete_records('message_processors', ['name' => 'jabber']);

            // Remove user preference settings.
            $DB->delete_records('user_preferences', ['name' => 'message_processor_jabber_jabberid']);
            $sql = 'SELECT *
                    FROM {user_preferences} up
                    WHERE ' . $DB->sql_like('up.name', ':name', false, false) . ' AND ' .
                        $DB->sql_like('up.value', ':value', false, false);
            $params = [
                'name' => 'message_provider_%',
                'value' => '%jabber%',
            ];
            $jabbersettings = $DB->get_recordset_sql($sql, $params);
            foreach ($jabbersettings as $jabbersetting) {
                // Remove 'jabber' from the value.
                $jabbersetting->value = implode(',', array_diff(explode(',', $jabbersetting->value), ['jabber']));
                $DB->update_record('user_preferences', $jabbersetting);
            }
            $jabbersettings->close();

            // Clean config settings.
            unset_config('jabberhost');
            unset_config('jabberserver');
            unset_config('jabberusername');
            unset_config('jabberpassword');
            unset_config('jabberport');

            // Remove default notification preferences.
            $like = $DB->sql_like('name', '?', true, true, false, '|');
            $params = [$DB->sql_like_escape('jabber_provider_', '|') . '%'];
            $DB->delete_records_select('config_plugins', $like, $params);

            // Clean config config settings.
            unset_all_config_for_plugin('message_jabber');
        }

        upgrade_main_savepoint(true, 2021091100.01);
    }

    if ($oldversion < 2021091100.02) {
        // Set the description field to HTML format for the Default course category.
        $category = $DB->get_record('course_categories', ['id' => 1]);

        if (!empty($category) && $category->descriptionformat == FORMAT_MOODLE) {
            // Format should be changed only if it's still set to FORMAT_MOODLE.
            if (!is_null($category->description)) {
                // If description is not empty, format the content to HTML.
                $category->description = format_text($category->description, FORMAT_MOODLE);
            }
            $category->descriptionformat = FORMAT_HTML;
            $DB->update_record('course_categories', $category);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021091100.02);
    }

    if ($oldversion < 2021091700.01) {
        // Default 'off' for existing sites as this is the behaviour they had earlier.
        set_config('enroladminnewcourse', false);

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021091700.01);
    }

    if ($oldversion < 2021091700.02) {
        // If portfolio_picasa is no longer present, remove it.
        if (!file_exists($CFG->dirroot . '/portfolio/picasa/version.php')) {
            $instance = $DB->get_record('portfolio_instance', ['plugin' => 'picasa']);
            if (!empty($instance)) {
                // Remove all records from portfolio_instance_config.
                $DB->delete_records('portfolio_instance_config', ['instance' => $instance->id]);
                // Remove all records from portfolio_instance_user.
                $DB->delete_records('portfolio_instance_user', ['instance' => $instance->id]);
                // Remove all records from portfolio_log.
                $DB->delete_records('portfolio_log', ['portfolio' => $instance->id]);
                // Remove all records from portfolio_tempdata.
                $DB->delete_records('portfolio_tempdata', ['instance' => $instance->id]);
                // Remove the record from the portfolio_instance table.
                $DB->delete_records('portfolio_instance', ['id' => $instance->id]);
            }

            // Clean config.
            unset_all_config_for_plugin('portfolio_picasa');
        }

        upgrade_main_savepoint(true, 2021091700.02);
    }

    if ($oldversion < 2021091700.03) {
        // If repository_picasa is no longer present, remove it.
        if (!file_exists($CFG->dirroot . '/repository/picasa/version.php')) {
            $instance = $DB->get_record('repository', ['type' => 'picasa']);
            if (!empty($instance)) {
                // Remove all records from repository_instance_config table.
                $DB->delete_records('repository_instance_config', ['instanceid' => $instance->id]);
                // Remove all records from repository_instances table.
                $DB->delete_records('repository_instances', ['typeid' => $instance->id]);
                // Remove the record from the repository table.
                $DB->delete_records('repository', ['id' => $instance->id]);
            }

            // Clean config.
            unset_all_config_for_plugin('picasa');

            // Remove orphaned files.
            general_helper::upgrade_delete_orphaned_file_records();
        }

        upgrade_main_savepoint(true, 2021091700.03);
    }

    if ($oldversion < 2021091700.04) {
        // Remove media_swf (unless it has manually been added back).
        if (!file_exists($CFG->dirroot . '/media/player/swf/classes/plugin.php')) {
            unset_all_config_for_plugin('media_swf');
        }

        upgrade_main_savepoint(true, 2021091700.04);
    }

    if ($oldversion < 2021092400.01) {
        // If tool_health is no longer present, remove it.
        if (!file_exists($CFG->dirroot . '/admin/tool/health/version.php')) {
            // Clean config.
            unset_all_config_for_plugin('tool_health');
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021092400.01);
    }

    if ($oldversion < 2021092400.03) {
        // Remove repository_picasa configuration (unless it has manually been added back).
        if (!file_exists($CFG->dirroot . '/repository/picasa/version.php')) {
            unset_all_config_for_plugin('repository_picasa');
        }

        upgrade_main_savepoint(true, 2021092400.03);
    }

    if ($oldversion < 2021100300.01) {
        // Remove repository_skydrive (unless it has manually been added back).
        if (!file_exists($CFG->dirroot . '/repository/skydrive/lib.php')) {
            unset_all_config_for_plugin('repository_skydrive');
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021100300.01);
    }

    if ($oldversion < 2021100300.02) {
        // Remove filter_censor (unless it has manually been added back).
        if (!file_exists($CFG->dirroot . '/filter/censor/filter.php')) {
            unset_all_config_for_plugin('filter_censor');
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021100300.02);
    }

    if ($oldversion < 2021100600.01) {
        // Remove qformat_examview (unless it has manually been added back).
        if (!file_exists($CFG->dirroot . '/question/format/examview/format.php')) {
            unset_all_config_for_plugin('qformat_examview');
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021100600.01);
    }

    if ($oldversion < 2021100600.02) {
        $table = new xmldb_table('course_completion_defaults');

        // Adding fields to table course_completion_defaults.
        $field = new xmldb_field('completionpassgrade', XMLDB_TYPE_INTEGER, '1', null,
            XMLDB_NOTNULL, null, '0', 'completionusegrade');

        // Conditionally launch add field for course_completion_defaults.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_main_savepoint(true, 2021100600.02);
    }

    if ($oldversion < 2021100600.03) {
        $table = new xmldb_table('course_modules');

        // Adding new fields to table course_module table.
        $field = new xmldb_field('completionpassgrade', XMLDB_TYPE_INTEGER, '1', null,
            XMLDB_NOTNULL, null, '0', 'completionexpected');
        // Conditionally launch create table for course_completion_defaults.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_main_savepoint(true, 2021100600.03);
    }

    if ($oldversion < 2021100600.04) {
        // Define index itemtype-mod-inst-course (not unique) to be added to grade_items.
        $table = new xmldb_table('grade_items');
        $index = new xmldb_index('itemtype-mod-inst-course', XMLDB_INDEX_NOTUNIQUE,
            ['itemtype', 'itemmodule', 'iteminstance', 'courseid']);

        // Conditionally launch add index itemtype-mod-inst-course.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021100600.04);
    }

    if ($oldversion < 2021101900.01) {
        $table = new xmldb_table('reportbuilder_report');

        // Define field name to be added to reportbuilder_report.
        $field = new xmldb_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'id');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field conditiondata to be added to reportbuilder_report.
        $field = new xmldb_field('conditiondata', XMLDB_TYPE_TEXT, null, null, null, null, null, 'type');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define table reportbuilder_column to be created.
        $table = new xmldb_table('reportbuilder_column');

        // Adding fields to table reportbuilder_column.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('reportid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('uniqueidentifier', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('aggregation', XMLDB_TYPE_CHAR, '32', null, null, null, null);
        $table->add_field('heading', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('columnorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sortenabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sortdirection', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table reportbuilder_column.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('reportid', XMLDB_KEY_FOREIGN, ['reportid'], 'reportbuilder_report', ['id']);
        $table->add_key('usercreated', XMLDB_KEY_FOREIGN, ['usercreated'], 'user', ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Conditionally launch create table for reportbuilder_column.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table reportbuilder_filter to be created.
        $table = new xmldb_table('reportbuilder_filter');

        // Adding fields to table reportbuilder_filter.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('reportid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('uniqueidentifier', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('heading', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('iscondition', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('filterorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table reportbuilder_filter.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('reportid', XMLDB_KEY_FOREIGN, ['reportid'], 'reportbuilder_report', ['id']);
        $table->add_key('usercreated', XMLDB_KEY_FOREIGN, ['usercreated'], 'user', ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Conditionally launch create table for reportbuilder_filter.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021101900.01);
    }

    if ($oldversion < 2021102600.01) {
        // Remove block_quiz_results (unless it has manually been added back).
        if (!file_exists($CFG->dirroot . '/blocks/quiz_result/block_quiz_results.php')) {
            // Delete instances.
            $instances = $DB->get_records_list('block_instances', 'blockname', ['quiz_results']);
            $instanceids = array_keys($instances);

            if (!empty($instanceids)) {
                blocks_delete_instances($instanceids);
            }

            // Delete the block from the block table.
            $DB->delete_records('block', array('name' => 'quiz_results'));

            // Remove capabilities.
            capabilities_cleanup('block_quiz_results');
            // Clean config.
            unset_all_config_for_plugin('block_quiz_results');

            // Remove Moodle-level quiz_results based capabilities.
            $capabilitiestoberemoved = ['block/quiz_results:addinstance'];
            // Delete any role_capabilities for the old roles.
            $DB->delete_records_list('role_capabilities', 'capability', $capabilitiestoberemoved);
            // Delete the capability itself.
            $DB->delete_records_list('capabilities', 'name', $capabilitiestoberemoved);
        }

        upgrade_main_savepoint(true, 2021102600.01);
    }

    if ($oldversion < 2021102900.02) {
        // If portfolio_boxnet is no longer present, remove it.
        if (!file_exists($CFG->dirroot . '/portfolio/boxnet/version.php')) {
            $instance = $DB->get_record('portfolio_instance', ['plugin' => 'boxnet']);
            if (!empty($instance)) {
                // Remove all records from portfolio_instance_config.
                $DB->delete_records('portfolio_instance_config', ['instance' => $instance->id]);
                // Remove all records from portfolio_instance_user.
                $DB->delete_records('portfolio_instance_user', ['instance' => $instance->id]);
                // Remove all records from portfolio_log.
                $DB->delete_records('portfolio_log', ['portfolio' => $instance->id]);
                // Remove all records from portfolio_tempdata.
                $DB->delete_records('portfolio_tempdata', ['instance' => $instance->id]);
                // Remove the record from the portfolio_instance table.
                $DB->delete_records('portfolio_instance', ['id' => $instance->id]);
            }

            // Clean config.
            unset_all_config_for_plugin('portfolio_boxnet');
        }

        // If repository_boxnet is no longer present, remove it.
        if (!file_exists($CFG->dirroot . '/repository/boxnet/version.php')) {
            $instance = $DB->get_record('repository', ['type' => 'boxnet']);
            if (!empty($instance)) {
                // Remove all records from repository_instance_config table.
                $DB->delete_records('repository_instance_config', ['instanceid' => $instance->id]);
                // Remove all records from repository_instances table.
                $DB->delete_records('repository_instances', ['typeid' => $instance->id]);
                // Remove the record from the repository table.
                $DB->delete_records('repository', ['id' => $instance->id]);
            }

            // Clean config.
            unset_all_config_for_plugin('repository_boxnet');

            // The boxnet repository plugin stores some config in 'boxnet' incorrectly.
            unset_all_config_for_plugin('boxnet');

            // Remove orphaned files.
            general_helper::upgrade_delete_orphaned_file_records();
        }

        upgrade_main_savepoint(true, 2021102900.02);
    }

    if ($oldversion < 2021110100.00) {

        // Define table reportbuilder_audience to be created.
        $table = new xmldb_table('reportbuilder_audience');

        // Adding fields to table reportbuilder_audience.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('reportid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('classname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('configdata', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table reportbuilder_audience.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('reportid', XMLDB_KEY_FOREIGN, ['reportid'], 'reportbuilder_report', ['id']);
        $table->add_key('usercreated', XMLDB_KEY_FOREIGN, ['usercreated'], 'user', ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Conditionally launch create table for reportbuilder_audience.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021110100.00);
    }

    if ($oldversion < 2021110800.02) {
        // Define a field 'downloadcontent' in the 'course_modules' table.
        $table = new xmldb_table('course_modules');
        $field = new xmldb_field('downloadcontent', XMLDB_TYPE_INTEGER, '1', null, null, null, 1, 'deletioninprogress');

        // Conditionally launch add field 'downloadcontent'.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021110800.02);
    }

    if ($oldversion < 2021110800.03) {

        // Define field settingsdata to be added to reportbuilder_report.
        $table = new xmldb_table('reportbuilder_report');
        $field = new xmldb_field('settingsdata', XMLDB_TYPE_TEXT, null, null, null, null, null, 'conditiondata');

        // Conditionally launch add field settingsdata.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021110800.03);
    }

    if ($oldversion < 2021111700.00) {
        $mycoursespage = new stdClass();
        $mycoursespage->userid = null;
        $mycoursespage->name = '__courses';
        $mycoursespage->private = 0;
        $mycoursespage->sortorder  = 0;
        $DB->insert_record('my_pages', $mycoursespage);

        upgrade_main_savepoint(true, 2021111700.00);
    }

    if ($oldversion < 2021111700.01) {

        // Define field uniquerows to be added to reportbuilder_report.
        $table = new xmldb_table('reportbuilder_report');
        $field = new xmldb_field('uniquerows', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'type');

        // Conditionally launch add field uniquerows.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021111700.01);
    }

    if ($oldversion < 2021120100.01) {

        // Get current configuration data.
        $currentcustomusermenuitems = str_replace(["\r\n", "\r"], "\n", $CFG->customusermenuitems);
        $lines = explode("\n", $currentcustomusermenuitems);
        $lines = array_map('trim', $lines);
        $calendarcustomusermenu = 'calendar,core_calendar|/calendar/view.php?view=month|i/calendar';

        if (!in_array($calendarcustomusermenu, $lines)) {
            // Add Calendar item to the menu.
            array_splice($lines, 1, 0, [$calendarcustomusermenu]);
            set_config('customusermenuitems', implode("\n", $lines));
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021120100.01);
    }

    if ($oldversion < 2021121400.01) {
        // The $CFG->grade_navmethod setting has been removed because it's not required anymore. This setting was used
        // to set the type of navigation (tabs or dropdown box) which will be displayed in gradebook. However, these
        // navigation methods are no longer used and replaced with tertiary navigation.
        unset_config('grade_navmethod');

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021121400.01);
    }

    if ($oldversion < 2021121700.01) {
        // Get current support email setting value.
        $config = get_config('moodle', 'supportemail');

        // Check if support email setting is empty and then set it to null.
        // We must do that so the setting is displayed during the upgrade.
        if (empty($config)) {
            set_config('supportemail', null);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021121700.01);
    }

    if ($oldversion < 2021122100.00) {
        // Get current configuration data.
        $currentcustomusermenuitems = str_replace(["\r\n", "\r"], "\n", $CFG->customusermenuitems);

        // The old default customusermenuitems config for 3.11 and below.
        $oldcustomusermenuitems = 'grades,grades|/grade/report/mygrades.php|t/grades
calendar,core_calendar|/calendar/view.php?view=month|i/calendar
messages,message|/message/index.php|t/message
preferences,moodle|/user/preferences.php|t/preferences';

        // Check if the current customusermenuitems config matches the old customusermenuitems config.
        $samecustomusermenuitems = $currentcustomusermenuitems == $oldcustomusermenuitems;
        if ($samecustomusermenuitems) {
            // If the site is still using the old defaults, upgrade to the new default.
            $newcustomusermenuitems = 'profile,moodle|/user/profile.php
grades,grades|/grade/report/mygrades.php
calendar,core_calendar|/calendar/view.php?view=month
privatefiles,moodle|/user/files.php';
            // Set the new configuration back.
            set_config('customusermenuitems', $newcustomusermenuitems);
        } else {
            // If the site is not using the old defaults, only add necessary entries.
            $lines = preg_split('/\n/', $currentcustomusermenuitems, -1, PREG_SPLIT_NO_EMPTY);
            $lines = array_map(static function(string $line): string {
                // Previous format was "<langstring>|<url>[|<pixicon>]" - pix icon is no longer supported.
                $lineparts = explode('|', trim($line), 3);
                // Return first two parts of line.
                return implode('|', array_slice($lineparts, 0, 2));
            }, $lines);

            // Remove the Preference entry from the menu to prevent duplication
            // since it will be added again in user_get_user_navigation_info().
            $lines = array_filter($lines, function($value) {
                return strpos($value, 'preferences,moodle|/user/preferences.php') === false;
            });

            $matches = preg_grep('/\|\/user\/files.php/i', $lines);
            if (!$matches) {
                // Add the Private files entry to the menu.
                $lines[] = 'privatefiles,moodle|/user/files.php';
            }

            $matches = preg_grep('/\|\/user\/profile.php/i', $lines);
            if (!$matches) {
                // Add the Profile entry to top of the menu.
                array_unshift($lines, 'profile,moodle|/user/profile.php');
            }

            // Set the new configuration back.
            set_config('customusermenuitems', implode("\n", $lines));
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021122100.00);
    }


    if ($oldversion < 2021122100.01) {

        // Define field heading to be added to reportbuilder_audience.
        $table = new xmldb_table('reportbuilder_audience');
        $field = new xmldb_field('heading', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'reportid');

        // Conditionally launch add field heading.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021122100.01);
    }

    if ($oldversion < 2021122100.02) {

        // Define table reportbuilder_schedule to be created.
        $table = new xmldb_table('reportbuilder_schedule');

        // Adding fields to table reportbuilder_schedule.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('reportid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('audiences', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('format', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('subject', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('message', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('messageformat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userviewas', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timescheduled', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('recurrence', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('reportempty', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timelastsent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timenextsend', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table reportbuilder_schedule.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('reportid', XMLDB_KEY_FOREIGN, ['reportid'], 'reportbuilder_report', ['id']);
        $table->add_key('userviewas', XMLDB_KEY_FOREIGN, ['userviewas'], 'user', ['id']);
        $table->add_key('usercreated', XMLDB_KEY_FOREIGN, ['usercreated'], 'user', ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Conditionally launch create table for reportbuilder_schedule.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021122100.02);
    }

    if ($oldversion < 2021123000.01) {
        // The tool_admin_presets tables have been moved to core, because core_adminpresets component has been created, so
        // it can interact with the rest of core.
        // So the tool_admin_presetsXXX tables will be renamed to adminipresetsXXX if they exists; otherwise, they will be created.

        $tooltable = new xmldb_table('tool_admin_presets');
        $table = new xmldb_table('adminpresets');
        if ($dbman->table_exists($tooltable)) {
            $dbman->rename_table($tooltable, 'adminpresets');
        } else if (!$dbman->table_exists($table)) {
            // Adding fields to table adminpresets.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('comments', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('site', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('author', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('moodleversion', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('moodlerelease', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('iscore', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timeimported', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            // Adding keys to table adminpresets.
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            // Launch create table for adminpresets.
            $dbman->create_table($table);
        }

        $tooltable = new xmldb_table('tool_admin_presets_it');
        $table = new xmldb_table('adminpresets_it');
        if ($dbman->table_exists($tooltable)) {
            $dbman->rename_table($tooltable, 'adminpresets_it');
        } else if (!$dbman->table_exists($table)) {
            // Adding fields to table adminpresets_it.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('adminpresetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('plugin', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('value', XMLDB_TYPE_TEXT, null, null, null, null, null);

            // Adding keys to table adminpresets_it.
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            // Adding indexes to table adminpresets_it.
            $table->add_index('adminpresetid', XMLDB_INDEX_NOTUNIQUE, ['adminpresetid']);

            // Launch create table for adminpresets_it.
            $dbman->create_table($table);
        }

        $tooltable = new xmldb_table('tool_admin_presets_it_a');
        $table = new xmldb_table('adminpresets_it_a');
        if ($dbman->table_exists($tooltable)) {
            $dbman->rename_table($tooltable, 'adminpresets_it_a');
        } else if (!$dbman->table_exists($table)) {
            // Adding fields to table adminpresets_it_a.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('value', XMLDB_TYPE_TEXT, null, null, null, null, null);

            // Adding keys to table adminpresets_it_a.
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            // Adding indexes to table adminpresets_it_a.
            $table->add_index('itemid', XMLDB_INDEX_NOTUNIQUE, ['itemid']);

            // Launch create table for adminpresets_it_a.
            $dbman->create_table($table);
        }

        $tooltable = new xmldb_table('tool_admin_presets_app');
        $table = new xmldb_table('adminpresets_app');
        if ($dbman->table_exists($tooltable)) {
            $dbman->rename_table($tooltable, 'adminpresets_app');
        } else if (!$dbman->table_exists($table)) {
            // Adding fields to table adminpresets_app.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('adminpresetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('time', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            // Adding keys to table adminpresets_app.
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            // Adding indexes to table adminpresets_app.
            $table->add_index('adminpresetid', XMLDB_INDEX_NOTUNIQUE, ['adminpresetid']);

            // Launch create table for adminpresets_app.
            $dbman->create_table($table);
        }

        $tooltable = new xmldb_table('tool_admin_presets_app_it');
        $table = new xmldb_table('adminpresets_app_it');
        if ($dbman->table_exists($tooltable)) {
            $dbman->rename_table($tooltable, 'adminpresets_app_it');
        } else if (!$dbman->table_exists($table)) {
            // Adding fields to table adminpresets_app_it.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('adminpresetapplyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('configlogid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            // Adding keys to table adminpresets_app_it.
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            // Adding indexes to table adminpresets_app_it.
            $table->add_index('configlogid', XMLDB_INDEX_NOTUNIQUE, ['configlogid']);
            $table->add_index('adminpresetapplyid', XMLDB_INDEX_NOTUNIQUE, ['adminpresetapplyid']);

            // Launch create table for adminpresets_app_it.
            $dbman->create_table($table);
        }

        $tooltable = new xmldb_table('tool_admin_presets_app_it_a');
        $table = new xmldb_table('adminpresets_app_it_a');
        if ($dbman->table_exists($tooltable)) {
            $dbman->rename_table($tooltable, 'adminpresets_app_it_a');
        } else if (!$dbman->table_exists($table)) {
            // Adding fields to table adminpresets_app_it_a.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('adminpresetapplyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('configlogid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('itemname', XMLDB_TYPE_CHAR, '100', null, null, null, null);

            // Adding keys to table adminpresets_app_it_a.
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            // Adding indexes to table adminpresets_app_it_a.
            $table->add_index('configlogid', XMLDB_INDEX_NOTUNIQUE, ['configlogid']);
            $table->add_index('adminpresetapplyid', XMLDB_INDEX_NOTUNIQUE, ['adminpresetapplyid']);

            // Launch create table for adminpresets_app_it_a.
            $dbman->create_table($table);
        }

        $tooltable = new xmldb_table('tool_admin_presets_plug');
        $table = new xmldb_table('adminpresets_plug');
        if ($dbman->table_exists($tooltable)) {
            $dbman->rename_table($tooltable, 'adminpresets_plug');
        } else if (!$dbman->table_exists($table)) {
            // Adding fields to table adminpresets_plug.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('adminpresetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('plugin', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('enabled', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');

            // Adding keys to table adminpresets_plug.
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            // Adding indexes to table adminpresets_plug.
            $table->add_index('adminpresetid', XMLDB_INDEX_NOTUNIQUE, ['adminpresetid']);

            // Launch create table for adminpresets_plug.
            $dbman->create_table($table);
        }

        $tooltable = new xmldb_table('tool_admin_presets_app_plug');
        $table = new xmldb_table('adminpresets_app_plug');
        if ($dbman->table_exists($tooltable)) {
            $dbman->rename_table($tooltable, 'adminpresets_app_plug');
        } else if (!$dbman->table_exists($table)) {
            // Adding fields to table adminpresets_app_plug.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('adminpresetapplyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('plugin', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('value', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('oldvalue', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');

            // Adding keys to table adminpresets_app_plug.
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            // Adding indexes to table adminpresets_app_plug.
            $table->add_index('adminpresetapplyid', XMLDB_INDEX_NOTUNIQUE, ['adminpresetapplyid']);

            // Launch create table for adminpresets_app_plug.
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }
        }

        if ($DB->count_records('adminpresets', ['iscore' => 1]) == 0) {
            // Create default core site admin presets.
            adminpresets_helper::create_default_presets();
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021123000.01);
    }

    if ($oldversion < 2021123000.02) {
        // If exists, migrate sensiblesettings admin settings from tool_admin_preset to adminpresets.
        if (get_config('tool_admin_presets', 'sensiblesettings') !== false) {
            set_config('sensiblesettings', get_config('tool_admin_presets', 'sensiblesettings'), 'adminpresets');
            unset_config('sensiblesettings', 'tool_admin_presets');
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021123000.02);
    }

    if ($oldversion < 2021123000.03) {
        // If exists, migrate lastpresetapplied setting from tool_admin_preset to adminpresets.
        if (get_config('tool_admin_presets', 'lastpresetapplied') !== false) {
            set_config('lastpresetapplied', get_config('tool_admin_presets', 'lastpresetapplied'), 'adminpresets');
            unset_config('lastpresetapplied', 'tool_admin_presets');
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2021123000.03);
    }

    if ($oldversion < 2022011100.01) {
        // The following blocks have been hidden by default, so they shouldn't be enabled in the Full core preset: Course/site
        // summary, RSS feeds, Self completion and Feedback.
        $params = ['name' => get_string('fullpreset', 'core_adminpresets')];
        $fullpreset = $DB->get_record_select('adminpresets', 'name = :name and iscore > 0', $params);

        if (!$fullpreset) {
            // Full admin preset might have been created using the English name.
            $name = get_string_manager()->get_string('fullpreset', 'core_adminpresets', null, 'en');
            $params['name'] = $name;
            $fullpreset = $DB->get_record_select('adminpresets', 'name = :name and iscore > 0', $params);
        }
        if (!$fullpreset) {
            // We tried, but we didn't find full by name. Let's find a core preset that sets 'usecomments' setting to 1.
            $sql = "SELECT preset.*
                      FROM {adminpresets} preset
                INNER JOIN {adminpresets_it} it ON preset.id = it.adminpresetid
                     WHERE it.name = :name AND it.value = :value AND preset.iscore > 0";
            $params = ['name' => 'usecomments', 'value' => '1'];
            $fullpreset = $DB->get_record_sql($sql, $params);
        }

        if ($fullpreset) {
            $blocknames = ['course_summary', 'feedback', 'rss_client', 'selfcompletion'];
            list($blocksinsql, $blocksinparams) = $DB->get_in_or_equal($blocknames);

            // Remove entries from the adminpresets_app_plug table (in case the preset has been applied).
            $appliedpresets = $DB->get_records('adminpresets_app', ['adminpresetid' => $fullpreset->id], '', 'id');
            if ($appliedpresets) {
                list($appsinsql, $appsinparams) = $DB->get_in_or_equal(array_keys($appliedpresets));
                $sql = "adminpresetapplyid $appsinsql AND plugin='block' AND name $blocksinsql";
                $params = array_merge($appsinparams, $blocksinparams);
                $DB->delete_records_select('adminpresets_app_plug', $sql, $params);
            }

            // Remove entries for these blocks from the adminpresets_plug table.
            $sql = "adminpresetid = ? AND plugin='block' AND name $blocksinsql";
            $params = array_merge([$fullpreset->id], $blocksinparams);
            $DB->delete_records_select('adminpresets_plug', $sql, $params);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022011100.01);
    }

    if ($oldversion < 2022012100.02) {
        // Migrate default message output config.
        $preferences = get_config('message');

        $treatedprefs = [];

        foreach ($preferences as $preference => $value) {
            // Extract provider and preference name from the setting name.
            // Example name: airnotifier_provider_enrol_imsenterprise_imsenterprise_enrolment_permitted
            // Provider: airnotifier
            // Preference: enrol_imsenterprise_imsenterprise_enrolment_permitted.
            $providerparts = explode('_provider_', $preference);
            if (count($providerparts) <= 1) {
                continue;
            }

            $provider = $providerparts[0];
            $preference = $providerparts[1];

            // Extract and remove last part of the preference previously extracted: ie. permitted.
            $parts = explode('_', $preference);
            $key = array_pop($parts);

            if (in_array($key, ['permitted', 'loggedin', 'loggedoff'])) {
                if ($key == 'permitted') {
                    // We will use provider name instead of permitted.
                    $key = $provider;
                } else {
                    // Logged in and logged off values are a csv of the enabled providers.
                    $value = explode(',', $value);
                }

                // Join the rest of the parts: ie enrol_imsenterprise_imsenterprise_enrolment.
                $prefname = implode('_', $parts);

                if (!isset($treatedprefs[$prefname])) {
                    $treatedprefs[$prefname] = [];
                }

                // Save the value with the selected key.
                $treatedprefs[$prefname][$key] = $value;
            }
        }

        // Now take every preference previous treated and its values.
        foreach ($treatedprefs as $prefname => $values) {
            $enabled = []; // List of providers enabled for each preference.

            // Enable if one of those is enabled.
            $loggedin = isset($values['loggedin']) ? $values['loggedin'] : [];
            foreach ($loggedin as $provider) {
                $enabled[$provider] = 1;
            }
            $loggedoff = isset($values['loggedoff']) ? $values['loggedoff'] : [];
            foreach ($loggedoff as $provider) {
                $enabled[$provider] = 1;
            }

            // Do not treat those values again.
            unset($values['loggedin']);
            unset($values['loggedoff']);

            // Translate rest of values coming from permitted "key".
            foreach ($values as $provider => $value) {
                $locked = false;

                switch ($value) {
                    case 'forced':
                        // Provider is enabled by force.
                        $enabled[$provider] = 1;
                        $locked = true;
                        break;
                    case 'disallowed':
                        // Provider is disabled by force.
                        unset($enabled[$provider]);
                        $locked = true;
                        break;
                    default:
                        // Provider is not forced (permitted) or invalid values.
                }

                // Save locked.
                if ($locked) {
                    set_config($provider.'_provider_'.$prefname.'_locked', 1, 'message');
                } else {
                    set_config($provider.'_provider_'.$prefname.'_locked', 0, 'message');
                }
                // Remove old value.
                unset_config($provider.'_provider_'.$prefname.'_permitted', 'message');
            }

            // Save the new values.
            $value = implode(',', array_keys($enabled));
            set_config('message_provider_'.$prefname.'_enabled', $value, 'message');
            // Remove old values.
            unset_config('message_provider_'.$prefname.'_loggedin', 'message');
            unset_config('message_provider_'.$prefname.'_loggedoff', 'message');
        }

        // Migrate user preferences. ie merging message_provider_moodle_instantmessage_loggedoff with
        // message_provider_moodle_instantmessage_loggedin to message_provider_moodle_instantmessage_enabled.

        $allrecordsloggedoff = $DB->sql_like('name', ':loggedoff');
        $total = $DB->count_records_select(
            'user_preferences',
            $allrecordsloggedoff,
            ['loggedoff' => 'message_provider_%_loggedoff']
        );
        $i = 0;
        if ($total == 0) {
            $total = 1; // Avoid division by zero.
        }

        // We're migrating provider per provider to reduce memory usage.
        $providers = $DB->get_records('message_providers', null, 'name');
        foreach ($providers as $provider) {
            // 60 minutes to migrate each provider.
            upgrade_set_timeout(3600);
            $componentproviderbase = 'message_provider_'.$provider->component.'_'.$provider->name;

            $loggedinname = $componentproviderbase.'_loggedin';
            $loggedoffname = $componentproviderbase.'_loggedoff';

            // Change loggedin to enabled.
            $enabledname = $componentproviderbase.'_enabled';
            $DB->set_field('user_preferences', 'name', $enabledname, ['name' => $loggedinname]);

            $selectparams = [
                'enabled' => $enabledname,
                'loggedoff' => $loggedoffname,
            ];
            $sql = 'SELECT m1.id loggedoffid, m1.value as loggedoff, m2.value as enabled, m2.id as enabledid
                FROM
                    (SELECT id, userid, value FROM {user_preferences} WHERE name = :loggedoff) m1
                LEFT JOIN
                    (SELECT id, userid, value FROM {user_preferences} WHERE name = :enabled) m2
                    ON m1.userid = m2.userid';

            while (($rs = $DB->get_recordset_sql($sql, $selectparams, 0, 1000)) && $rs->valid()) {
                // 10 minutes for every chunk.
                upgrade_set_timeout(600);

                $deleterecords = [];
                $changename = [];
                $changevalue = []; // Multidimensional array with possible values as key to reduce SQL queries.
                foreach ($rs as $record) {
                    if (empty($record->enabledid)) {
                        // Enabled does not exists, change the name.
                        $changename[] = $record->loggedoffid;
                    } else if ($record->enabledid != $record->loggedoff) {
                        // Exist and values differ (checked on SQL), update the enabled record.

                        if ($record->enabled != 'none' && !empty($record->enabled)) {
                            $enabledvalues = explode(',', $record->enabled);
                        } else {
                            $enabledvalues = [];
                        }

                        if ($record->loggedoff != 'none' && !empty($record->loggedoff)) {
                            $loggedoffvalues = explode(',', $record->loggedoff);
                        } else {
                            $loggedoffvalues = [];
                        }

                        $values = array_unique(array_merge($enabledvalues, $loggedoffvalues));
                        sort($values);

                        $newvalue = empty($values) ? 'none' : implode(',', $values);
                        if (!isset($changevalue[$newvalue])) {
                            $changevalue[$newvalue] = [];
                        }
                        $changevalue[$newvalue][] = $record->enabledid;

                        $deleterecords[] = $record->loggedoffid;
                    } else {
                        // They are the same, just delete loggedoff one.
                        $deleterecords[] = $record->loggedoffid;
                    }
                    $i++;
                }
                $rs->close();

                // Commit the changes.
                if (!empty($changename)) {
                    $changenameparams = [
                        'name' => $loggedoffname,
                    ];
                    $changenameselect = 'name = :name AND id IN (' . implode(',', $changename) . ')';
                    $DB->set_field_select('user_preferences', 'name', $enabledname, $changenameselect, $changenameparams);
                }

                if (!empty($changevalue)) {
                    $changevalueparams = [
                        'name' => $enabledname,
                    ];
                    foreach ($changevalue as $value => $ids) {
                        $changevalueselect = 'name = :name AND id IN (' . implode(',', $ids) . ')';
                        $DB->set_field_select('user_preferences', 'value', $value, $changevalueselect, $changevalueparams);
                    }
                }

                if (!empty($deleterecords)) {
                    $deleteparams = [
                        'name' => $loggedoffname,
                    ];
                    $deleteselect = 'name = :name AND id IN (' . implode(',', $deleterecords) . ')';
                    $DB->delete_records_select('user_preferences', $deleteselect, $deleteparams);
                }
            }
            $rs->close();

            // Delete the rest of loggedoff values (that are equal than enabled).
            $deleteparams = [
                'name' => $loggedoffname,
            ];
            $deleteselect = 'name = :name';
            $i += $DB->count_records_select('user_preferences', $deleteselect, $deleteparams);
            $DB->delete_records_select('user_preferences', $deleteselect, $deleteparams);
        }

        core_plugin_manager::reset_caches();

        // Delete the orphan records.
        $allrecordsparams = ['loggedin' => 'message_provider_%_loggedin', 'loggedoff' => 'message_provider_%_loggedoff'];
        $allrecordsloggedin = $DB->sql_like('name', ':loggedin');
        $allrecordsloggedinoffsql = "$allrecordsloggedin OR $allrecordsloggedoff";
        $DB->delete_records_select('user_preferences', $allrecordsloggedinoffsql, $allrecordsparams);

        upgrade_main_savepoint(true, 2022012100.02);
    }

    // Introduce question versioning to core.
    // First, create the new tables.
    if ($oldversion < 2022020200.01) {
        // Define table question_bank_entries to be created.
        $table = new xmldb_table('question_bank_entries');

        // Adding fields to table question_bank_entries.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('questioncategoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('idnumber', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('ownerid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table question_bank_entries.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('questioncategoryid', XMLDB_KEY_FOREIGN, ['questioncategoryid'], 'question_categories', ['id']);
        $table->add_key('ownerid', XMLDB_KEY_FOREIGN, ['ownerid'], 'user', ['id']);

        // Conditionally launch create table for question_bank_entries.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Create category id and id number index.
        $index = new xmldb_index('categoryidnumber', XMLDB_INDEX_UNIQUE, ['questioncategoryid', 'idnumber']);

        // Conditionally launch add index categoryidnumber.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define table question_versions to be created.
        $table = new xmldb_table('question_versions');

        // Adding fields to table question_versions.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('questionbankentryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('version', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 1);
        $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('status', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'ready');

        // Adding keys to table question_versions.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('questionbankentryid', XMLDB_KEY_FOREIGN, ['questionbankentryid'], 'question_bank_entries', ['id']);
        $table->add_key('questionid', XMLDB_KEY_FOREIGN, ['questionid'], 'question', ['id']);

        // Conditionally launch create table for question_versions.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table question_references to be created.
        $table = new xmldb_table('question_references');

        // Adding fields to table question_references.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('usingcontextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('questionarea', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('questionbankentryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('version', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table question_references.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usingcontextid', XMLDB_KEY_FOREIGN, ['usingcontextid'], 'context', ['id']);
        $table->add_key('questionbankentryid', XMLDB_KEY_FOREIGN, ['questionbankentryid'], 'question_bank_entries', ['id']);

        // Conditionally launch create table for question_references.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table question_set_references to be created.
        $table = new xmldb_table('question_set_references');

        // Adding fields to table question_set_references.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('usingcontextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('questionarea', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('questionscontextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('filtercondition', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table question_set_references.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usingcontextid', XMLDB_KEY_FOREIGN, ['usingcontextid'], 'context', ['id']);
        $table->add_key('questionscontextid', XMLDB_KEY_FOREIGN, ['questionscontextid'], 'context', ['id']);

        // Conditionally launch create table for question_set_references.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022020200.01);
    }

    if ($oldversion < 2022020200.02) {
        // Define a new temporary field in the question_bank_entries tables.
        // Creating temporary field questionid to populate the data in question version table.
        // This will make sure the appropriate question id is inserted the version table without making any complex joins.
        $table = new xmldb_table('question_bank_entries');
        $field = new xmldb_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $transaction = $DB->start_delegated_transaction();
        upgrade_set_timeout(3600);
        // Create the data for the question_bank_entries table with, including the new temporary field.
        $sql = <<<EOF
            INSERT INTO {question_bank_entries}
                (questionid, questioncategoryid, idnumber, ownerid)
            SELECT id, category, idnumber, createdby
            FROM {question} q
            EOF;

        // Inserting question_bank_entries data.
        $DB->execute($sql);

        $transaction->allow_commit();

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022020200.02);
    }

    if ($oldversion < 2022020200.03) {
        $transaction = $DB->start_delegated_transaction();
        upgrade_set_timeout(3600);
        // Create the question_versions using that temporary field.
        $sql = <<<EOF
            INSERT INTO {question_versions}
                (questionbankentryid, questionid, status)
            SELECT
                qbe.id,
                q.id,
                CASE
                    WHEN q.hidden > 0 THEN 'hidden'
                    ELSE 'ready'
                END
            FROM {question_bank_entries} qbe
            INNER JOIN {question} q ON qbe.questionid = q.id
            EOF;

        // Inserting question_versions data.
        $DB->execute($sql);

        $transaction->allow_commit();

        // Dropping temporary field questionid.
        $table = new xmldb_table('question_bank_entries');
        $field = new xmldb_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022020200.03);
    }

    if ($oldversion < 2022020200.04) {
        $transaction = $DB->start_delegated_transaction();
        upgrade_set_timeout(3600);
        // Create the base data for the random questions in the set_references table.
        // This covers most of the hard work in one go.
        $concat = $DB->sql_concat("'{\"questioncategoryid\":\"'", 'q.category', "'\",\"includingsubcategories\":\"'",
            'qs.includingsubcategories', "'\"}'");
        $sql = <<<EOF
            INSERT INTO {question_set_references}
            (usingcontextid, component, questionarea, itemid, questionscontextid, filtercondition)
            SELECT
                c.id,
                'mod_quiz',
                'slot',
                qs.id,
                qc.contextid,
                $concat
            FROM {question} q
            INNER JOIN {quiz_slots} qs on q.id = qs.questionid
            INNER JOIN {course_modules} cm ON cm.instance = qs.quizid AND cm.module = :quizmoduleid
            INNER JOIN {context} c ON cm.id = c.instanceid AND c.contextlevel = :contextmodule
            INNER JOIN {question_categories} qc ON qc.id = q.category
            WHERE q.qtype = :random
            EOF;

        // Inserting question_set_references data.
        $DB->execute($sql, [
            'quizmoduleid' => $DB->get_field('modules', 'id', ['name' => 'quiz']),
            'contextmodule' => CONTEXT_MODULE,
            'random' => 'random',
        ]);

        $transaction->allow_commit();

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022020200.04);
    }

    if ($oldversion < 2022020200.05) {
        $transaction = $DB->start_delegated_transaction();
        upgrade_set_timeout(3600);

        // Updating slot_tags for random question tags.
        // Now fetch any quiz slot tags and update those slot details into the question_set_references.
        $slottags = $DB->get_recordset('quiz_slot_tags', [], 'slotid ASC');

        $tagstrings = [];
        $lastslot = null;
        $runinsert = function (int $lastslot, array $tagstrings) use ($DB) {
            $conditiondata = $DB->get_field('question_set_references', 'filtercondition',
                ['itemid' => $lastslot, 'component' => 'mod_quiz', 'questionarea' => 'slot']);

            // It is possible to have leftover tags in the database, without a corresponding
            // slot, because of an old bugs (e.g. MDL-76193). Therefore, if the slot is not found,
            // we can safely discard these tags.
            if (!empty($conditiondata)) {
                $condition = json_decode($conditiondata);
                $condition->tags = $tagstrings;
                $DB->set_field('question_set_references', 'filtercondition', json_encode($condition),
                        ['itemid' => $lastslot, 'component' => 'mod_quiz', 'questionarea' => 'slot']);
            }
        };

        foreach ($slottags as $tag) {
            upgrade_set_timeout(3600);
            if ($lastslot && $tag->slotid != $lastslot) {
                if (!empty($tagstrings)) {
                    // Insert the data.
                    $runinsert($lastslot, $tagstrings);
                }
                // Prepare for the next slot id.
                $tagstrings = [];
            }

            $lastslot = $tag->slotid;
            $tagstrings[] = "{$tag->tagid},{$tag->tagname}";
        }
        if ($tagstrings) {
            $runinsert($lastslot, $tagstrings);
        }
        $slottags->close();

        $transaction->allow_commit();
        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022020200.05);
    }

    if ($oldversion < 2022020200.06) {
        $transaction = $DB->start_delegated_transaction();
        upgrade_set_timeout(3600);
        // Create question_references record for each question.
        // Except if qtype is random. That case is handled by question_set_reference.
        $sql = "INSERT INTO {question_references}
                        (usingcontextid, component, questionarea, itemid, questionbankentryid)
                 SELECT c.id, 'mod_quiz', 'slot', qs.id, qv.questionbankentryid
                   FROM {question} q
                   JOIN {question_versions} qv ON q.id = qv.questionid
                   JOIN {quiz_slots} qs ON q.id = qs.questionid
                   JOIN {modules} m ON m.name = 'quiz'
                   JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = qs.quizid
                   JOIN {context} c ON c.instanceid = cm.id AND c.contextlevel = " . CONTEXT_MODULE . "
                  WHERE q.qtype <> 'random'";

        // Inserting question_references data.
        $DB->execute($sql);

        $transaction->allow_commit();
        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022020200.06);
    }

    // Finally, drop fields from question table.
    if ($oldversion < 2022020200.07) {
        // Define fields to be dropped from questions.
        $table = new xmldb_table('question');

        $field = new xmldb_field('version');
        // Conditionally launch drop field version.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        $field = new xmldb_field('hidden');
        // Conditionally launch drop field hidden.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define index categoryidnumber (not unique) to be dropped form question.
        $index = new xmldb_index('categoryidnumber', XMLDB_INDEX_UNIQUE, ['category', 'idnumber']);

        // Conditionally launch drop index categoryidnumber.
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        // Define key category (foreign) to be dropped form questions.
        $key = new xmldb_key('category', XMLDB_KEY_FOREIGN, ['category'], 'question_categories', ['id']);

        // Launch drop key category.
        $dbman->drop_key($table, $key);

        $field = new xmldb_field('idnumber');
        // Conditionally launch drop field idnumber.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        $field = new xmldb_field('category');
        // Conditionally launch drop field category.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022020200.07);
    }

    if ($oldversion < 2022021100.01) {
        $sql = "SELECT preset.*
                  FROM {adminpresets} preset
            INNER JOIN {adminpresets_it} it ON preset.id = it.adminpresetid
                 WHERE it.name = :name AND it.value = :value AND preset.iscore > 0";
        // Some settings and plugins have been added/removed to the Starter and Full preset. Add them to the core presets if
        // they haven't been included yet.
        $params = ['name' => get_string('starterpreset', 'core_adminpresets'), 'iscore' => 1];
        $starterpreset = $DB->get_record('adminpresets', $params);
        if (!$starterpreset) {
            // Starter admin preset might have been created using the English name.
            $name = get_string_manager()->get_string('starterpreset', 'core_adminpresets', null, 'en');
            $params['name'] = $name;
            $starterpreset = $DB->get_record('adminpresets', $params);
        }
        if (!$starterpreset) {
            // We tried, but we didn't find starter by name. Let's find a core preset that sets 'usecomments' setting to 0.
            $params = ['name' => 'usecomments', 'value' => '0'];
            $starterpreset = $DB->get_record_sql($sql, $params);
        }

        $params = ['name' => get_string('fullpreset', 'core_adminpresets')];
        $fullpreset = $DB->get_record_select('adminpresets', 'name = :name and iscore > 0', $params);
        if (!$fullpreset) {
            // Full admin preset might have been created using the English name.
            $name = get_string_manager()->get_string('fullpreset', 'core_adminpresets', null, 'en');
            $params['name'] = $name;
            $fullpreset = $DB->get_record_select('adminpresets', 'name = :name and iscore > 0', $params);
        }
        if (!$fullpreset) {
            // We tried, but we didn't find full by name. Let's find a core preset that sets 'usecomments' setting to 1.
            $params = ['name' => 'usecomments', 'value' => '1'];
            $fullpreset = $DB->get_record_sql($sql, $params);
        }

        $settings = [
            // Settings. Hide Guest login button for Starter preset (and back to show for Full).
            [
                'presetid' => $starterpreset->id,
                'plugin' => 'none',
                'name' => 'guestloginbutton',
                'value' => '0',
            ],
            [
                'presetid' => $fullpreset->id,
                'plugin' => 'none',
                'name' => 'guestloginbutton',
                'value' => '1',
            ],
            // Settings. Set Activity chooser tabs to "Starred, All, Recommended"(1) for Starter and back it to default(0) for Full.
            [
                'presetid' => $starterpreset->id,
                'plugin' => 'none',
                'name' => 'activitychoosertabmode',
                'value' => '1',
            ],
            [
                'presetid' => $fullpreset->id,
                'plugin' => 'none',
                'name' => 'activitychoosertabmode',
                'value' => '0',
            ],
        ];
        foreach ($settings as $notused => $setting) {
            $params = ['adminpresetid' => $setting['presetid'], 'plugin' => $setting['plugin'], 'name' => $setting['name']];
            if (!$DB->record_exists('adminpresets_it', $params)) {
                $record = new \stdClass();
                $record->adminpresetid = $setting['presetid'];
                $record->plugin = $setting['plugin'];
                $record->name = $setting['name'];
                $record->value = $setting['value'];
                $DB->insert_record('adminpresets_it', $record);
            }
        }

        $plugins = [
            // Plugins. Blocks. Disable/enable Online users, Recently accessed courses and Starred courses.
            [
                'presetid' => $starterpreset->id,
                'plugin' => 'block',
                'name' => 'online_users',
                'enabled' => '0',
            ],
            [
                'presetid' => $fullpreset->id,
                'plugin' => 'block',
                'name' => 'online_users',
                'enabled' => '1',
            ],
            [
                'presetid' => $starterpreset->id,
                'plugin' => 'block',
                'name' => 'recentlyaccessedcourses',
                'enabled' => '0',
            ],
            [
                'presetid' => $fullpreset->id,
                'plugin' => 'block',
                'name' => 'recentlyaccessedcourses',
                'enabled' => '1',
            ],
            [
                'presetid' => $starterpreset->id,
                'plugin' => 'block',
                'name' => 'starredcourses',
                'enabled' => '0',
            ],
            [
                'presetid' => $fullpreset->id,
                'plugin' => 'block',
                'name' => 'starredcourses',
                'enabled' => '1',
            ],
            // Plugins. Enrolments. Disable/enable Guest access.
            [
                'presetid' => $starterpreset->id,
                'plugin' => 'enrol',
                'name' => 'guest',
                'enabled' => '0',
            ],
            [
                'presetid' => $fullpreset->id,
                'plugin' => 'enrol',
                'name' => 'guest',
                'enabled' => '1',
            ],
        ];
        foreach ($plugins as $notused => $plugin) {
            $params = ['adminpresetid' => $plugin['presetid'], 'plugin' => $plugin['plugin'], 'name' => $plugin['name']];
            if (!$DB->record_exists('adminpresets_plug', $params)) {
                $record = new \stdClass();
                $record->adminpresetid = $plugin['presetid'];
                $record->plugin = $plugin['plugin'];
                $record->name = $plugin['name'];
                $record->enabled = $plugin['enabled'];
                $DB->insert_record('adminpresets_plug', $record);
            }
        }

        // Settings: Remove customusermenuitems setting from Starter and Full presets.
        $sql = "(adminpresetid = ? OR adminpresetid = ?) AND plugin = 'none' AND name = 'customusermenuitems'";
        $params = [$starterpreset->id, $fullpreset->id];
        $DB->delete_records_select('adminpresets_it', $sql, $params);

        // Plugins. Question types. Re-enable Description and Essay for Starter.
        $sql = "(adminpresetid = ? OR adminpresetid = ?) AND plugin = 'qtype' AND (name = 'description' OR name = 'essay')";
        $DB->delete_records_select('adminpresets_plug', $sql, $params);

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022021100.01);

    }

    if ($oldversion < 2022021100.02) {
        $table = new xmldb_table('task_scheduled');

        // Changing precision of field minute on table task_scheduled to (200).
        $field = new xmldb_field('minute', XMLDB_TYPE_CHAR, '200', null, XMLDB_NOTNULL, null, null, 'blocking');
        $dbman->change_field_precision($table, $field);
        // Changing precision of field hour on table task_scheduled to (70).
        $field = new xmldb_field('hour', XMLDB_TYPE_CHAR, '70', null, XMLDB_NOTNULL, null, null, 'minute');
        $dbman->change_field_precision($table, $field);
        // Changing precision of field day on table task_scheduled to (90).
        $field = new xmldb_field('day', XMLDB_TYPE_CHAR, '90', null, XMLDB_NOTNULL, null, null, 'hour');
        $dbman->change_field_precision($table, $field);
        // Changing precision of field month on table task_scheduled to (30).
        $field = new xmldb_field('month', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null, 'day');
        $dbman->change_field_precision($table, $field);

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022021100.02);
    }

    if ($oldversion < 2022022600.01) {
        // Get all processor and existing preferences.
        $processors = $DB->get_records('message_processors');
        $providers = $DB->get_records('message_providers', null, '', 'id, name, component');
        $existingpreferences = get_config('message');

        foreach ($processors as $processor) {
            foreach ($providers as $provider) {
                // Setting default preference name.
                $componentproviderbase = $provider->component . '_' . $provider->name;
                $preferencename = $processor->name.'_provider_'.$componentproviderbase.'_locked';
                // If we do not have this setting yet, set it to 0.
                if (!isset($existingpreferences->{$preferencename})) {
                    set_config($preferencename, 0, 'message');
                }
            }
        }

        upgrade_main_savepoint(true, 2022022600.01);
    }

    if ($oldversion < 2022030100.00) {
        $sql = "SELECT preset.*
                  FROM {adminpresets} preset
            INNER JOIN {adminpresets_it} it ON preset.id = it.adminpresetid
                 WHERE it.name = :name AND it.value = :value AND preset.iscore > 0";

        $name = get_string('starterpreset', 'core_adminpresets');
        $params = ['name' => $name, 'iscore' => 1];
        $starterpreset = $DB->get_record('adminpresets', $params);
        if (!$starterpreset) {
            // Starter admin preset might have been created using the English name. Let's change it to current language.
            $englishname = get_string_manager()->get_string('starterpreset', 'core_adminpresets', null, 'en');
            $params['name'] = $englishname;
            $starterpreset = $DB->get_record('adminpresets', $params);
        }
        if (!$starterpreset) {
            // We tried, but we didn't find starter by name. Let's find a core preset that sets 'usecomments' setting to 0.
            $params = ['name' => 'usecomments', 'value' => '0'];
            $starterpreset = $DB->get_record_sql($sql, $params);
        }
        // The iscore field is already 1 for starterpreset, so we don't need to change it.
        // We only need to update the name and comment in case they are different to current language strings.
        if ($starterpreset && $starterpreset->name != $name) {
            $starterpreset->name = $name;
            $starterpreset->comments = get_string('starterpresetdescription', 'core_adminpresets');
            $DB->update_record('adminpresets', $starterpreset);
        }

        // Let's mark Full admin presets with current FULL_PRESETS value and change the name to current language.
        $name = get_string('fullpreset', 'core_adminpresets');
        $params = ['name' => $name];
        $fullpreset = $DB->get_record_select('adminpresets', 'name = :name and iscore > 0', $params);
        if (!$fullpreset) {
            // Full admin preset might have been created using the English name.
            $englishname = get_string_manager()->get_string('fullpreset', 'core_adminpresets', null, 'en');
            $params['name'] = $englishname;
            $fullpreset = $DB->get_record_select('adminpresets', 'name = :name and iscore > 0', $params);
        }
        if (!$fullpreset) {
            // We tried, but we didn't find full by name. Let's find a core preset that sets 'usecomments' setting to 1.
            $params = ['name' => 'usecomments', 'value' => '1'];
            $fullpreset = $DB->get_record_sql($sql, $params);
        }
        if ($fullpreset) {
            // We need to update iscore field value, whether the name is the same or not.
            $fullpreset->name = $name;
            $fullpreset->comments = get_string('fullpresetdescription', 'core_adminpresets');
            $fullpreset->iscore = 2;
            $DB->update_record('adminpresets', $fullpreset);

            // We are applying again changes made on 2022011100.01 upgrading step because of MDL-73953 bug.
            $blocknames = ['course_summary', 'feedback', 'rss_client', 'selfcompletion'];
            list($blocksinsql, $blocksinparams) = $DB->get_in_or_equal($blocknames);

            // Remove entries from the adminpresets_app_plug table (in case the preset has been applied).
            $appliedpresets = $DB->get_records('adminpresets_app', ['adminpresetid' => $fullpreset->id], '', 'id');
            if ($appliedpresets) {
                list($appsinsql, $appsinparams) = $DB->get_in_or_equal(array_keys($appliedpresets));
                $sql = "adminpresetapplyid $appsinsql AND plugin='block' AND name $blocksinsql";
                $params = array_merge($appsinparams, $blocksinparams);
                $DB->delete_records_select('adminpresets_app_plug', $sql, $params);
            }

            // Remove entries for these blocks from the adminpresets_plug table.
            $sql = "adminpresetid = ? AND plugin='block' AND name $blocksinsql";
            $params = array_merge([$fullpreset->id], $blocksinparams);
            $DB->delete_records_select('adminpresets_plug', $sql, $params);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022030100.00);
    }

    if ($oldversion < 2022031100.01) {
        $reportsusermenuitem = 'reports,core_reportbuilder|/reportbuilder/index.php';
        general_helper::upgrade_add_item_to_usermenu($reportsusermenuitem);
        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022031100.01);
    }

    if ($oldversion < 2022032200.01) {

        // Define index to be added to question_references.
        $table = new xmldb_table('question_references');
        $index = new xmldb_index('context-component-area-itemid', XMLDB_INDEX_UNIQUE,
            ['usingcontextid', 'component', 'questionarea', 'itemid']);

        // Conditionally launch add field id.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022032200.01);
    }

    if ($oldversion < 2022032200.02) {

        // Define index to be added to question_references.
        $table = new xmldb_table('question_set_references');
        $index = new xmldb_index('context-component-area-itemid', XMLDB_INDEX_UNIQUE,
            ['usingcontextid', 'component', 'questionarea', 'itemid']);

        // Conditionally launch add field id.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022032200.02);
    }

    if ($oldversion < 2022041200.01) {

        // The original default admin presets "sensible settings" (those that should be treated as sensitive).
        $originalsensiblesettings = 'recaptchapublickey@@none, recaptchaprivatekey@@none, googlemapkey3@@none, ' .
            'secretphrase@@url, cronremotepassword@@none, smtpuser@@none, smtppass@none, proxypassword@@none, ' .
            'quizpassword@@quiz, allowedip@@none, blockedip@@none, dbpass@@logstore_database, messageinbound_hostpass@@none, ' .
            'bind_pw@@auth_cas, pass@@auth_db, bind_pw@@auth_ldap, dbpass@@enrol_database, bind_pw@@enrol_ldap, ' .
            'server_password@@search_solr, ssl_keypassword@@search_solr, alternateserver_password@@search_solr, ' .
            'alternatessl_keypassword@@search_solr, test_password@@cachestore_redis, password@@mlbackend_python';

        // Check if the current config matches the original default, upgrade to new default if so.
        if (get_config('adminpresets', 'sensiblesettings') === $originalsensiblesettings) {
            $newsensiblesettings = "{$originalsensiblesettings}, badges_badgesalt@@none, calendar_exportsalt@@none";
            set_config('sensiblesettings', $newsensiblesettings, 'adminpresets');
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022041200.01);
    }

    // Automatically generated Moodle v4.0.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2022042900.01) {
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
        upgrade_main_savepoint(true, 2022042900.01);
    }

    if ($oldversion < 2022051000.00) {
        // Add index to the sid field in the external_tokens table.
        $table = new xmldb_table('external_tokens');
        $index = new xmldb_index('sid', XMLDB_INDEX_NOTUNIQUE, ['sid']);

        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_main_savepoint(true, 2022051000.00);
    }

    if ($oldversion < 2022052500.00) {
        // Start an adhoc task to fix the file timestamps of restored files.
        after_upgrade_task::schedule(\core\task\fix_file_timestamps_task::class);

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022052500.00);
    }

    if ($oldversion < 2022052700.01) {

        // Define index timestarted_idx (not unique) to be added to task_adhoc.
        $table = new xmldb_table('task_adhoc');
        $index = new xmldb_index('timestarted_idx', XMLDB_INDEX_NOTUNIQUE, ['timestarted']);

        // Conditionally launch add index timestarted_idx.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022052700.01);
    }

    if ($oldversion < 2022052700.02) {

        // Define index filename (not unique) to be added to files.
        $table = new xmldb_table('files');
        $index = new xmldb_index('filename', XMLDB_INDEX_NOTUNIQUE, ['filename']);

        // Conditionally launch add index filename.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022052700.02);
    }

    if ($oldversion < 2022060300.01) {

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
        upgrade_main_savepoint(true, 2022060300.01);
    }

    if ($oldversion < 2022061000.01) {
        // Iterate over custom user menu items configuration, removing pix icon references.
        $customusermenuitems = str_replace(["\r\n", "\r"], "\n", $CFG->customusermenuitems);

        $lines = preg_split('/\n/', $customusermenuitems, -1, PREG_SPLIT_NO_EMPTY);
        $lines = array_map(static function(string $line): string {
            // Previous format was "<langstring>|<url>[|<pixicon>]" - pix icon is no longer supported.
            $lineparts = explode('|', trim($line), 3);
            // Return first two parts of line.
            return implode('|', array_slice($lineparts, 0, 2));
        }, $lines);

        set_config('customusermenuitems', implode("\n", $lines));

        upgrade_main_savepoint(true, 2022061000.01);
    }

    if ($oldversion < 2022061500.00) {
        // Remove drawer-open-nav user preference for every user.
        $DB->delete_records('user_preferences', ['name' => 'drawer-open-nav']);

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022061500.00);

    }

    if ($oldversion < 2022072900.00) {
        // Call the helper function that updates the foreign keys and indexes in MDL-49795.
        tool_vault_401_upgrade_add_foreign_key_and_indexes();

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022072900.00);
    }

    if ($oldversion < 2022081200.01) {

        // Define field lang to be added to course_modules.
        $table = new xmldb_table('course_modules');
        $field = new xmldb_field('lang', XMLDB_TYPE_CHAR, '30', null, null, null, null, 'downloadcontent');

        // Conditionally launch add field lang.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022081200.01);
    }

    if ($oldversion < 2022091000.01) {
        $table = new xmldb_table('h5p');
        $indexpathnamehash = new xmldb_index('pathnamehash_idx', XMLDB_INDEX_NOTUNIQUE, ['pathnamehash']);

        if (!$dbman->index_exists($table, $indexpathnamehash)) {
            $dbman->add_index($table, $indexpathnamehash);
        }
        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022091000.01);
    }

    if ($oldversion < 2022092200.01) {

        // Remove any orphaned tag instance records (pointing to non-existing context).
        $DB->delete_records_select('tag_instance', 'NOT EXISTS (
            SELECT ctx.id FROM {context} ctx WHERE ctx.id = {tag_instance}.contextid
        )');

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022092200.01);
    }

    if ($oldversion < 2022101400.01) {
        $table = new xmldb_table('competency_modulecomp');
        $field = new xmldb_field('overridegrade', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'ruleoutcome');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022101400.01);
    }

    if ($oldversion < 2022101400.03) {
        // Define table to store completion viewed.
        $table = new xmldb_table('course_modules_viewed');

        // Adding fields to table course_modules_viewed.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('coursemoduleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'coursemoduleid');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'userid');

        // Adding keys to table course_modules_viewed.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table course_modules_viewed.
        $table->add_index('coursemoduleid', XMLDB_INDEX_NOTUNIQUE, ['coursemoduleid']);
        $table->add_index('userid-coursemoduleid', XMLDB_INDEX_UNIQUE, ['userid', 'coursemoduleid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022101400.03);
    }

    if ($oldversion < 2022101400.04) {
        // Add legacy data to the new table.
        $transaction = $DB->start_delegated_transaction();
        upgrade_set_timeout(3600);
        $sql = "INSERT INTO {course_modules_viewed}
                            (userid, coursemoduleid, timecreated)
                     SELECT userid, coursemoduleid, timemodified
                       FROM {course_modules_completion}
                      WHERE viewed = 1";
        $DB->execute($sql);
        $transaction->allow_commit();

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022101400.04);
    }

    if ($oldversion < 2022101400.05) {
        // Define field viewed to be dropped from course_modules_completion.
        $table = new xmldb_table('course_modules_completion');
        $field = new xmldb_field('viewed');

        // Conditionally launch drop field viewed.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022101400.05);
    }

    if ($oldversion < 2022102800.01) {
        // For sites with "contact site support" already available (4.0.x), maintain existing functionality.
        if ($oldversion >= 2022041900.00) {
            set_config('supportavailability', CONTACT_SUPPORT_ANYONE);
        } else {
            // Sites which did not previously have the "contact site support" feature default to it requiring authentication.
            set_config('supportavailability', CONTACT_SUPPORT_AUTHENTICATED);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022102800.01);
    }

    if ($oldversion < 2022110600.00) {
        // If webservice_xmlrpc isn't any longer installed, remove its configuration,
        // capabilities and presence in other settings.
        if (!file_exists($CFG->dirroot . '/webservice/xmlrpc/version.php')) {
            // No DB structures to delete in this plugin.

            // Remove capabilities.
            capabilities_cleanup('webservice_xmlrpc');

            // Remove own configuration.
            unset_all_config_for_plugin('webservice_xmlrpc');

            // Remove it from the enabled protocols if it was there.
            $protos = get_config('core', 'webserviceprotocols');
            $protoarr = explode(',', $protos);
            $protoarr = array_filter($protoarr, function($ele) {
                return trim($ele) !== 'xmlrpc';
            });
            $protos = implode(',', $protoarr);
            set_config('webserviceprotocols', $protos);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022110600.00);
    }

    // Automatically generated Moodle v4.1.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2022112800.03) {

        // Remove any orphaned role assignment records (pointing to non-existing roles).
        $DB->delete_records_select('role_assignments', 'NOT EXISTS (
            SELECT r.id FROM {role} r WHERE r.id = {role_assignments}.roleid
        )');

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022112800.03);
    }

    return true;
}


/**
 * Upgrade helper to add foreign keys and indexes for MDL-49795
 */
function tool_vault_401_upgrade_add_foreign_key_and_indexes() {
    global $DB;

    $dbman = $DB->get_manager();
    // Define key originalcourseid (foreign) to be added to course.
    $table = new xmldb_table('course');
    $key = new xmldb_key('originalcourseid', XMLDB_KEY_FOREIGN, ['originalcourseid'], 'course', ['id']);
    // Launch add key originalcourseid.
    $dbman->add_key($table, $key);

    // Define key roleid (foreign) to be added to enrol.
    $table = new xmldb_table('enrol');
    $key = new xmldb_key('roleid', XMLDB_KEY_FOREIGN, ['roleid'], 'role', ['id']);
    // Launch add key roleid.
    $dbman->add_key($table, $key);

    // Define key userid (foreign) to be added to scale.
    $table = new xmldb_table('scale');
    $key = new xmldb_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
    // Launch add key userid.
    $dbman->add_key($table, $key);

    // Define key userid (foreign) to be added to scale_history.
    $table = new xmldb_table('scale_history');
    $key = new xmldb_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
    // Launch add key userid.
    $dbman->add_key($table, $key);

    // Define key courseid (foreign) to be added to post.
    $table = new xmldb_table('post');
    $key = new xmldb_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
    // Launch add key courseid.
    $dbman->add_key($table, $key);

    // Define key coursemoduleid (foreign) to be added to post.
    $table = new xmldb_table('post');
    $key = new xmldb_key('coursemoduleid', XMLDB_KEY_FOREIGN, ['coursemoduleid'], 'course_modules', ['id']);
    // Launch add key coursemoduleid.
    $dbman->add_key($table, $key);

    // Define key questionid (foreign) to be added to question_statistics.
    $table = new xmldb_table('question_statistics');
    $key = new xmldb_key('questionid', XMLDB_KEY_FOREIGN, ['questionid'], 'question', ['id']);
    // Launch add key questionid.
    $dbman->add_key($table, $key);

    // Define key questionid (foreign) to be added to question_response_analysis.
    $table = new xmldb_table('question_response_analysis');
    $key = new xmldb_key('questionid', XMLDB_KEY_FOREIGN, ['questionid'], 'question', ['id']);
    // Launch add key questionid.
    $dbman->add_key($table, $key);

    // Define index last_log_id (not unique) to be added to mnet_host.
    $table = new xmldb_table('mnet_host');
    $index = new xmldb_index('last_log_id', XMLDB_INDEX_NOTUNIQUE, ['last_log_id']);
    // Conditionally launch add index last_log_id.
    if (!$dbman->index_exists($table, $index)) {
        $dbman->add_index($table, $index);
    }

    // Define key userid (foreign) to be added to mnet_session.
    $table = new xmldb_table('mnet_session');
    $key = new xmldb_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
    // Launch add key userid.
    $dbman->add_key($table, $key);

    // Define key mnethostid (foreign) to be added to mnet_session.
    $table = new xmldb_table('mnet_session');
    $key = new xmldb_key('mnethostid', XMLDB_KEY_FOREIGN, ['mnethostid'], 'mnet_host', ['id']);
    // Launch add key mnethostid.
    $dbman->add_key($table, $key);

    // Define key userid (foreign) to be added to grade_import_values.
    $table = new xmldb_table('grade_import_values');
    $key = new xmldb_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
    // Launch add key userid.
    $dbman->add_key($table, $key);

    // Define key tempdataid (foreign) to be added to portfolio_log.
    $table = new xmldb_table('portfolio_log');
    $key = new xmldb_key('tempdataid', XMLDB_KEY_FOREIGN, ['tempdataid'], 'portfolio_tempdata', ['id']);
    // Launch add key tempdataid.
    $dbman->add_key($table, $key);

    // Define key usermodified (foreign) to be added to file_conversion.
    $table = new xmldb_table('file_conversion');
    $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
    // Launch add key usermodified.
    $dbman->add_key($table, $key);

    // Define key userid (foreign) to be added to repository_instances.
    $table = new xmldb_table('repository_instances');
    $key = new xmldb_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
    // Launch add key userid.
    $dbman->add_key($table, $key);

    // Define key contextid (foreign) to be added to repository_instances.
    $table = new xmldb_table('repository_instances');
    $key = new xmldb_key('contextid', XMLDB_KEY_FOREIGN, ['contextid'], 'context', ['id']);
    // Launch add key contextid.
    $dbman->add_key($table, $key);

    // Define key scaleid (foreign) to be added to rating.
    $table = new xmldb_table('rating');
    $key = new xmldb_key('scaleid', XMLDB_KEY_FOREIGN, ['scaleid'], 'scale', ['id']);
    // Launch add key scaleid.
    $dbman->add_key($table, $key);

    // Define key courseid (foreign) to be added to course_published.
    $table = new xmldb_table('course_published');
    $key = new xmldb_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
    // Launch add key courseid.
    $dbman->add_key($table, $key);

    // Define index hubcourseid (not unique) to be added to course_published.
    $table = new xmldb_table('course_published');
    $index = new xmldb_index('hubcourseid', XMLDB_INDEX_NOTUNIQUE, ['hubcourseid']);
    // Conditionally launch add index hubcourseid.
    if (!$dbman->index_exists($table, $index)) {
        $dbman->add_index($table, $index);
    }

    // Define key courseid (foreign) to be added to event_subscriptions.
    $table = new xmldb_table('event_subscriptions');
    $key = new xmldb_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
    // Launch add key courseid.
    $dbman->add_key($table, $key);

    // Define key userid (foreign) to be added to event_subscriptions.
    $table = new xmldb_table('event_subscriptions');
    $key = new xmldb_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
    // Launch add key userid.
    $dbman->add_key($table, $key);

    // Define key userid (foreign) to be added to task_log.
    $table = new xmldb_table('task_log');
    $key = new xmldb_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
    // Launch add key userid.
    $dbman->add_key($table, $key);

    // Define key scaleid (foreign) to be added to competency.
    $table = new xmldb_table('competency');
    $key = new xmldb_key('scaleid', XMLDB_KEY_FOREIGN, ['scaleid'], 'scale', ['id']);
    // Launch add key scaleid.
    $dbman->add_key($table, $key);

    // Define key usermodified (foreign) to be added to competency.
    $table = new xmldb_table('competency');
    $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
    // Launch add key usermodified.
    $dbman->add_key($table, $key);

    // Define key usermodified (foreign) to be added to competency_coursecompsetting.
    $table = new xmldb_table('competency_coursecompsetting');
    $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
    // Launch add key usermodified.
    $dbman->add_key($table, $key);

    // Define key contextid (foreign) to be added to competency_framework.
    $table = new xmldb_table('competency_framework');
    $key = new xmldb_key('contextid', XMLDB_KEY_FOREIGN, ['contextid'], 'context', ['id']);
    // Launch add key contextid.
    $dbman->add_key($table, $key);

    // Define key scaleid (foreign) to be added to competency_framework.
    $table = new xmldb_table('competency_framework');
    $key = new xmldb_key('scaleid', XMLDB_KEY_FOREIGN, ['scaleid'], 'scale', ['id']);
    // Launch add key scaleid.
    $dbman->add_key($table, $key);

    // Define key usermodified (foreign) to be added to competency_framework.
    $table = new xmldb_table('competency_framework');
    $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
    // Launch add key usermodified.
    $dbman->add_key($table, $key);

    // Define key usermodified (foreign) to be added to competency_coursecomp.
    $table = new xmldb_table('competency_coursecomp');
    $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
    // Launch add key usermodified.
    $dbman->add_key($table, $key);

    // Define key actionuserid (foreign) to be added to competency_evidence.
    $table = new xmldb_table('competency_evidence');
    $key = new xmldb_key('actionuserid', XMLDB_KEY_FOREIGN, ['actionuserid'], 'user', ['id']);
    // Launch add key actionuserid.
    $dbman->add_key($table, $key);

    // Define key contextid (foreign) to be added to competency_evidence.
    $table = new xmldb_table('competency_evidence');
    $key = new xmldb_key('contextid', XMLDB_KEY_FOREIGN, ['contextid'], 'context', ['id']);
    // Launch add key contextid.
    $dbman->add_key($table, $key);

    // Define key usermodified (foreign) to be added to competency_evidence.
    $table = new xmldb_table('competency_evidence');
    $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
    // Launch add key usermodified.
    $dbman->add_key($table, $key);

    // Define key usermodified (foreign) to be added to competency_userevidence.
    $table = new xmldb_table('competency_userevidence');
    $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
    // Launch add key usermodified.
    $dbman->add_key($table, $key);

    // Define key usermodified (foreign) to be added to competency_plan.
    $table = new xmldb_table('competency_plan');
    $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
    // Launch add key usermodified.
    $dbman->add_key($table, $key);

    // Define key usermodified (foreign) to be added to competency_template.
    $table = new xmldb_table('competency_template');
    $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
    // Launch add key usermodified.
    $dbman->add_key($table, $key);

    // Define key contextid (foreign) to be added to competency_template.
    $table = new xmldb_table('competency_template');
    $key = new xmldb_key('contextid', XMLDB_KEY_FOREIGN, ['contextid'], 'context', ['id']);
    // Launch add key contextid.
    $dbman->add_key($table, $key);

    // Define key usermodified (foreign) to be added to competency_templatecomp.
    $table = new xmldb_table('competency_templatecomp');
    $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
    // Launch add key usermodified.
    $dbman->add_key($table, $key);

    // Define key usermodified (foreign) to be added to competency_templatecohort.
    $table = new xmldb_table('competency_templatecohort');
    $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
    // Launch add key usermodified.
    $dbman->add_key($table, $key);

    // Define key competencyid (foreign) to be added to competency_relatedcomp.
    $table = new xmldb_table('competency_relatedcomp');
    $key = new xmldb_key('competencyid', XMLDB_KEY_FOREIGN, ['competencyid'], 'competency', ['id']);
    // Launch add key competencyid.
    $dbman->add_key($table, $key);

    // Define key relatedcompetencyid (foreign) to be added to competency_relatedcomp.
    $table = new xmldb_table('competency_relatedcomp');
    $key = new xmldb_key('relatedcompetencyid', XMLDB_KEY_FOREIGN, ['relatedcompetencyid'], 'competency', ['id']);
    // Launch add key relatedcompetencyid.
    $dbman->add_key($table, $key);

    // Define key usermodified (foreign) to be added to competency_relatedcomp.
    $table = new xmldb_table('competency_relatedcomp');
    $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
    // Launch add key usermodified.
    $dbman->add_key($table, $key);

    // Define key usermodified (foreign) to be added to competency_usercomp.
    $table = new xmldb_table('competency_usercomp');
    $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
    // Launch add key usermodified.
    $dbman->add_key($table, $key);

    // Define key usermodified (foreign) to be added to competency_usercompcourse.
    $table = new xmldb_table('competency_usercompcourse');
    $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
    // Launch add key usermodified.
    $dbman->add_key($table, $key);

    // Define key usermodified (foreign) to be added to competency_usercompplan.
    $table = new xmldb_table('competency_usercompplan');
    $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
    // Launch add key usermodified.
    $dbman->add_key($table, $key);

    // Define key usermodified (foreign) to be added to competency_plancomp.
    $table = new xmldb_table('competency_plancomp');
    $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
    // Launch add key usermodified.
    $dbman->add_key($table, $key);

    // Define key usermodified (foreign) to be added to competency_userevidencecomp.
    $table = new xmldb_table('competency_userevidencecomp');
    $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
    // Launch add key usermodified.
    $dbman->add_key($table, $key);

    // Define key usermodified (foreign) to be added to competency_modulecomp.
    $table = new xmldb_table('competency_modulecomp');
    $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
    // Launch add key usermodified.
    $dbman->add_key($table, $key);

    // Define key usermodified (foreign) to be added to oauth2_endpoint.
    $table = new xmldb_table('oauth2_endpoint');
    $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
    // Launch add key usermodified.
    $dbman->add_key($table, $key);

    // Define key usermodified (foreign) to be added to oauth2_system_account.
    $table = new xmldb_table('oauth2_system_account');
    $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
    // Launch add key usermodified.
    $dbman->add_key($table, $key);

    // Define key usermodified (foreign) to be added to oauth2_user_field_mapping.
    $table = new xmldb_table('oauth2_user_field_mapping');
    $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
    // Launch add key usermodified.
    $dbman->add_key($table, $key);

    // Define key usermodified (foreign) to be added to analytics_models.
    $table = new xmldb_table('analytics_models');
    $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
    // Launch add key usermodified.
    $dbman->add_key($table, $key);

    // Define key usermodified (foreign) to be added to analytics_models_log.
    $table = new xmldb_table('analytics_models_log');
    $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
    // Launch add key usermodified.
    $dbman->add_key($table, $key);

    // Define key usermodified (foreign) to be added to oauth2_access_token.
    $table = new xmldb_table('oauth2_access_token');
    $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
    // Launch add key usermodified.
    $dbman->add_key($table, $key);

    // Define key contextid (foreign) to be added to payment_accounts.
    $table = new xmldb_table('payment_accounts');
    $key = new xmldb_key('contextid', XMLDB_KEY_FOREIGN, ['contextid'], 'context', ['id']);
    // Launch add key contextid.
    $dbman->add_key($table, $key);
}
