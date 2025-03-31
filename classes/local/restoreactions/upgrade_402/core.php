<?php
// This file is part of plugin tool_vault - https://lmsvault.io
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

/**
 * All upgrade scripts between 4.1.2 (2022112802.00) and 4.2.3 (2023042403.00)
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
 *     // help you here. See {@link https://moodledev.io/general/development/tools/xmldb}.
 *     upgrade_main_savepoint(true, XXXXXXXXXX.XX);
 * }
 *
 * All plugins within Moodle (modules, blocks, reports...) support the existence of
 * their own upgrade.php file, using the "Frankenstyle" component name as
 * defined at {@link https://moodledev.io/general/development/policies/codingstyle/frankenstyle}, for example:
 *     - {@see xmldb_page_upgrade($oldversion)}. (modules don't require the plugintype ("mod_") to be used.
 *     - {@see xmldb_auth_manual_upgrade($oldversion)}.
 *     - {@see xmldb_workshopform_accumulative_upgrade($oldversion)}.
 *     - ....
 *
 * In order to keep the contents of this file reduced, it's allowed to create some helper
 * functions to be used here in the `upgradelib.php` file at the same directory. Note
 * that such a file must be manually included from upgrade.php, and there are some restrictions
 * about what can be used within it.
 *
 * For more information, take a look to the documentation available:
 *     - Data definition API: {@link https://moodledev.io/docs/apis/core/dml/ddl}
 *     - Upgrade API: {@link https://moodledev.io/docs/guides/upgrade}
 *
 * @param int $oldversion
 * @return bool always true
 */
function tool_vault_402_core_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    if ($oldversion < 2022121600.01) {
        // Define index blocknameindex (not unique) to be added to block_instances.
        $table = new xmldb_table('block_instances');
        $index = new xmldb_index('blocknameindex', XMLDB_INDEX_NOTUNIQUE, ['blockname']);

        // Conditionally launch add index blocknameindex.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        // Main savepoint reached.
        upgrade_main_savepoint(true, 2022121600.01);
    }

    if ($oldversion < 2023010300.00) {
        // The useexternalyui setting has been removed.
        unset_config('useexternalyui');

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023010300.00);
    }

    if ($oldversion < 2023020800.00) {
        // If cachestore_memcached is no longer present, remove it.
        if (!file_exists($CFG->dirroot . '/cache/stores/memcached/version.php')) {
            // Clean config.
            unset_all_config_for_plugin('cachestore_memcached');
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023020800.00);
    }

    if ($oldversion < 2023021700.01) {
        // Define field pdfexportfont to be added to course.
        $table = new xmldb_table('course');
        $field = new xmldb_field('pdfexportfont', XMLDB_TYPE_CHAR, '50', null, false, false, null, 'showcompletionconditions');

        // Conditionally launch add field pdfexportfont.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023021700.01);
    }

    if ($oldversion < 2023022000.00) {
        // Remove grade_report_showquickfeedback, grade_report_enableajax, grade_report_showeyecons,
        // grade_report_showlocks, grade_report_showanalysisicon preferences for every user.
        $DB->delete_records('user_preferences', ['name' => 'grade_report_showquickfeedback']);
        $DB->delete_records('user_preferences', ['name' => 'grade_report_enableajax']);
        $DB->delete_records('user_preferences', ['name' => 'grade_report_showeyecons']);
        $DB->delete_records('user_preferences', ['name' => 'grade_report_showlocks']);
        $DB->delete_records('user_preferences', ['name' => 'grade_report_showanalysisicon']);

        // The grade_report_showquickfeedback, grade_report_enableajax, grade_report_showeyecons,
        // grade_report_showlocks, grade_report_showanalysisicon settings have been removed.
        unset_config('grade_report_showquickfeedback');
        unset_config('grade_report_enableajax');
        unset_config('grade_report_showeyecons');
        unset_config('grade_report_showlocks');
        unset_config('grade_report_showanalysisicon');

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023022000.00);
    }

    if ($oldversion < 2023030300.01) {
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
            // Settings. Set Activity chooser tabs to "Starred, Recommended, All"(5) for Starter and back it to default(3) for Full.
            [
                'presetid' => $starterpreset->id,
                'plugin' => 'none',
                'name' => 'activitychoosertabmode',
                'value' => '4',
            ],
            [
                'presetid' => $fullpreset->id,
                'plugin' => 'none',
                'name' => 'activitychoosertabmode',
                'value' => '3',
            ],
        ];
        foreach ($settings as $notused => $setting) {
            $params = ['adminpresetid' => $setting['presetid'], 'plugin' => $setting['plugin'], 'name' => $setting['name']];
            if (!$record = $DB->get_record('adminpresets_it', $params)) {
                $record = new \stdClass();
                $record->adminpresetid = $setting['presetid'];
                $record->plugin = $setting['plugin'];
                $record->name = $setting['name'];
                $record->value = $setting['value'];
                $DB->insert_record('adminpresets_it', $record);
            } else {
                $record->value = $setting['value'];
                $DB->update_record('adminpresets_it', $record);
            }
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023030300.01);
    }

    if ($oldversion < 2023030300.02) {
        // If cachestore_mongodb is no longer present, remove it.
        if (!file_exists($CFG->dirroot . '/cache/stores/mongodb/version.php')) {
            // Clean config.
            unset_all_config_for_plugin('cachestore_mongodb');
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023030300.02);
    }

    if ($oldversion < 2023030300.03) {
        // If editor_tinymce is no longer present, remove it.
        if (!file_exists($CFG->dirroot . '/lib/editor/tinymce/version.php')) {
            // Clean config.
            uninstall_plugin('editor', 'tinymce');
            $DB->delete_records('user_preferences', [
                'name' => 'htmleditor',
                'value' => 'tinymce',
            ]);

            if ($editors = get_config('core', 'texteditors')) {
                $editors = array_flip(explode(',', $editors));
                unset($editors['tinymce']);
                set_config('texteditors', implode(',', array_flip($editors)));
            }
        }
        upgrade_main_savepoint(true, 2023030300.03);
    }

    if ($oldversion < 2023031000.02) {
        // If editor_tinymce is no longer present, remove it's sub-plugins too.
        if (!file_exists($CFG->dirroot . '/lib/editor/tinymce/version.php')) {
            $DB->delete_records_select(
                'config_plugins',
                $DB->sql_like('plugin', ':plugin'),
                ['plugin' => $DB->sql_like_escape('tinymce_') . '%']
            );
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023031000.02);
    }

    if ($oldversion < 2023031400.01) {
        // Define field id to be added to groups.
        $table = new xmldb_table('groups');
        $field = new xmldb_field('visibility', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'picture');

        // Conditionally launch add field visibility.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field participation to be added to groups.
        $field = new xmldb_field('participation', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'visibility');

        // Conditionally launch add field participation.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023031400.01);
    }

    if ($oldversion < 2023031400.02) {

        // Define table xapi_states to be created.
        $table = new xmldb_table('xapi_states');

        // Adding fields to table xapi_states.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('stateid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('statedata', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('registration', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table xapi_states.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table xapi_states.
        $table->add_index('component-itemid', XMLDB_INDEX_NOTUNIQUE, ['component', 'itemid']);
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('timemodified', XMLDB_INDEX_NOTUNIQUE, ['timemodified']);

        // Conditionally launch create table for xapi_states.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        if (!isset($CFG->xapicleanupperiod)) {
            set_config('xapicleanupperiod', WEEKSECS * 8);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023031400.02);
    }

    if ($oldversion < 2023040600.01) {
        // If logstore_legacy is no longer present, remove it.
        if (!file_exists($CFG->dirroot . '/admin/tool/log/store/legacy/version.php')) {
            uninstall_plugin('logstore', 'legacy');
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023040600.01);
    }

    if ($oldversion < 2023041100.00) {
        // Add public key field to user_devices table.
        $table = new xmldb_table('user_devices');
        $field = new xmldb_field('publickey', XMLDB_TYPE_TEXT, null, null, null, null, null, 'uuid');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023041100.00);
    }

    if ($oldversion < 2023042000.00) {
        // If mod_assignment is no longer present, remove it.
        if (!file_exists($CFG->dirroot . '/mod/assignment/version.php')) {
            // Delete all mod_assignment grade_grades orphaned data.
            $DB->delete_records_select(
                'grade_grades', "itemid IN (SELECT id FROM {grade_items} WHERE itemtype = 'mod' AND itemmodule = 'assignment')"
            );

            // Delete all mod_assignment grade_grades_history orphaned data.
            $DB->delete_records('grade_grades_history', ['source' => 'mod/assignment']);

            // Delete all mod_assignment grade_items orphaned data.
            $DB->delete_records('grade_items', ['itemtype' => 'mod', 'itemmodule' => 'assignment']);

            // Delete all mod_assignment grade_items_history orphaned data.
            $DB->delete_records('grade_items_history', ['itemtype' => 'mod', 'itemmodule' => 'assignment']);

            // Delete core mod_assignment subplugins.
            uninstall_plugin('assignment', 'offline');
            uninstall_plugin('assignment', 'online');
            uninstall_plugin('assignment', 'upload');
            uninstall_plugin('assignment', 'uploadsingle');

            // Delete other mod_assignment subplugins.
            $pluginnamelike = $DB->sql_like('plugin', ':pluginname');
            $subplugins = $DB->get_fieldset_select('config_plugins', 'plugin', "$pluginnamelike AND name = :name", [
                'pluginname' => $DB->sql_like_escape('assignment_') . '%',
                'name' => 'version',
            ]);
            foreach ($subplugins as $subplugin) {
                [$plugin, $subpluginname] = explode('_', $subplugin, 2);
                uninstall_plugin($plugin, $subpluginname);
            }

            // Delete mod_assignment.
            uninstall_plugin('mod', 'assignment');
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023042000.00);
    }

    // Automatically generated Moodle v4.2.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2023042400.03) {

        // Remove any orphaned role assignment records (pointing to non-existing roles).
        $DB->set_field('task_scheduled', 'disabled', 1, ['classname' => '\core\task\question_stats_cleanup_task']);

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023042400.03);
    }

    if ($oldversion < 2023042401.09) {
        // Upgrade yaml mime type for existing yaml and yml files.
        $filetypes = [
            '%.yaml' => 'application/yaml',
            '%.yml' => 'application/yaml,',
        ];

        $select = $DB->sql_like('filename', '?', false);
        foreach ($filetypes as $extension => $mimetype) {
            $DB->set_field_select(
                'files',
                'mimetype',
                $mimetype,
                $select,
                [$extension]
            );
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023042401.09);
    }

    if ($oldversion < 2023042402.03) {

        // The previous default configuration had a typo, check for its presence and correct if necessary.
        $sensiblesettings = get_config('adminpresets', 'sensiblesettings');
        if (strpos($sensiblesettings, 'smtppass@none') !== false) {
            $newsensiblesettings = str_replace('smtppass@none', 'smtppass@@none', $sensiblesettings);
            set_config('sensiblesettings', $newsensiblesettings, 'adminpresets');
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023042402.03);
    }

    if ($oldversion < 2023042402.11) {
        tool_vault_402_upgrade_core_licenses();
        upgrade_main_savepoint(true, 2023042402.11);
    }

    if ($oldversion < 2023042402.14) {
        // Delete datakey with datavalue -1.
        $DB->delete_records('messageinbound_datakeys', ['datavalue' => '-1']);
        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023042402.14);
    }

    return true;
}

/**
 * Upgrade core licenses shipped with Moodle.
 */
function tool_vault_402_upgrade_core_licenses() {
    global $CFG, $DB;

    $expectedlicenses = json_decode(file_get_contents($CFG->dirroot . '/lib/licenses.json'))->licenses;
    if (!is_array($expectedlicenses)) {
        $expectedlicenses = [];
    }
    $corelicenses = $DB->get_records('license', ['custom' => 0]);

    // Disable core licenses which are no longer current.
    $todisable = array_diff(
        array_map(fn ($license) => $license->shortname, $corelicenses),
        array_map(fn ($license) => $license->shortname, $expectedlicenses),
    );

    // Disable any old *core* license that does not exist in the licenses.json file.
    if (count($todisable)) {
        [$where, $params] = $DB->get_in_or_equal($todisable, SQL_PARAMS_NAMED);
        $DB->set_field_select(
            'license',
            'enabled',
            0,
            "shortname {$where}",
            $params
        );
    }

    // Add any new licenses.
    foreach ($expectedlicenses as $expectedlicense) {
        if (!$expectedlicense->enabled) {
            // Skip any license which is no longer enabled.
            continue;
        }
        if (!$DB->record_exists('license', ['shortname' => $expectedlicense->shortname])) {
            // If the license replaces an older one, check whether this old license was enabled or not.
            $isreplacement = false;
            foreach (array_reverse($expectedlicense->replaces ?? []) as $item) {
                foreach ($corelicenses as $corelicense) {
                    if ($corelicense->shortname === $item) {
                        $expectedlicense->enabled = $corelicense->enabled;
                        // Also, keep the old sort order.
                        $expectedlicense->sortorder = $corelicense->sortorder * 100;
                        $isreplacement = true;
                        break 2;
                    }
                }
            }
            if (!isset($CFG->upgraderunning) || during_initial_install() || $isreplacement) {
                // Only install missing core licenses if not upgrading or during initial installation.
                $DB->insert_record('license', $expectedlicense);
            }
        }
    }

    // Add/renumber sortorder to all licenses.
    $licenses = $DB->get_records('license', null, 'sortorder');
    $sortorder = 1;
    foreach ($licenses as $license) {
        $license->sortorder = $sortorder++;
        $DB->update_record('license', $license);
    }

    // Set the license config values, used by file repository for rendering licenses at front end.
    $activelicenses = $DB->get_records_menu('license', ['enabled' => 1], 'id', 'id, shortname');
    set_config('licenses', implode(',', $activelicenses));

    $sitedefaultlicense = get_config('', 'sitedefaultlicense');
    if (empty($sitedefaultlicense) || !in_array($sitedefaultlicense, $activelicenses)) {
        set_config('sitedefaultlicense', reset($activelicenses));
    }
}
