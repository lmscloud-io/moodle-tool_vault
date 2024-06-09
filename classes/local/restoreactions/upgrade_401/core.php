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
 *     - {@link xmldb_page_upgrade($oldversion)}. (modules don't require the plugintype ("mod_") to be used.
 *     - {@link xmldb_auth_manual_upgrade($oldversion)}.
 *     - {@link xmldb_workshopform_accumulative_upgrade($oldversion)}.
 *     - ....
 *
 * In order to keep the contents of this file reduced, it's allowed to create some helper
 * functions to be used here in the {@link upgradelib.php} file at the same directory. Note
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
function tool_vault_401_xmldb_main_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

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
