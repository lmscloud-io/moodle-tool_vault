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

// phpcs:ignoreFile

/**
 * All upgrade scripts between 4.2.3 (2023042403.00) and 4.4 (2024042200.00)
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function tool_vault_404_core_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    // Automatically generated Moodle v4.2.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2023051500.00) {
        // Define communication table.
        $table = new xmldb_table('communication');

        // Adding fields to table communication.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('instanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null, 'instanceid');
        $table->add_field('instancetype', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null, 'component');
        $table->add_field('provider', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null, 'instancerype');
        $table->add_field('roomname', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'provider');
        $table->add_field('avatarfilename', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'roomname');
        $table->add_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 1, 'avatarfilename');

        // Add key.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for communication.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define communication user table.
        $table = new xmldb_table('communication_user');

        // Adding fields to table communication.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('commid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'commid');
        $table->add_field('synced', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0, 'userid');
        $table->add_field('deleted', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0, 'synced');

        // Add keys.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('commid', XMLDB_KEY_FOREIGN, ['commid'], 'communication', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Conditionally launch create table for communication.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023051500.00);
    }

    if ($oldversion < 2023062200.00) {
        // Remove device specific fields for themes from config table.
        unset_config('thememobile');
        unset_config('themelegacy');
        unset_config('themetablet');

        upgrade_main_savepoint(true, 2023062200.00);
    }

    if ($oldversion < 2023062700.01) {
        // Define field name to be added to external_tokens.
        $table = new xmldb_table('external_tokens');
        $field = new xmldb_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'lastaccess');
        // Conditionally launch add field name.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Update the old external tokens.
        $sql = 'UPDATE {external_tokens}
                   SET name = ' . $DB->sql_concat(
                       // We only need the prefix, so leave the third param with an empty string.
                        "'" . get_string('tokennameprefix', 'webservice', '') . "'",
                        "id"
                    );
        $DB->execute($sql);
        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023062700.01);
    }

    if ($oldversion < 2023062900.01) {
        // Define field avatarsynced to be added to communication.
        $table = new xmldb_table('communication');
        $field = new xmldb_field('avatarsynced', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0, 'active');

        // Conditionally launch add field avatarsynced.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023062900.01);
    }

    if ($oldversion < 2023080100.00) {
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
        upgrade_main_savepoint(true, 2023080100.00);
    }

    if ($oldversion < 2023081500.00) {
        tool_vault_404_upgrade_core_licenses();
        upgrade_main_savepoint(true, 2023081500.00);
    }

    if ($oldversion < 2023081800.01) {
        // Remove enabledevicedetection and devicedetectregex from config table.
        unset_config('enabledevicedetection');
        unset_config('devicedetectregex');
        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023081800.01);
    }

    if ($oldversion < 2023082200.01) {
        // Some MIME icons have been removed and replaced with existing icons. They need to be upgraded for custom MIME types.
        $replacedicons = [
            'avi' => 'video',
            'base' => 'database',
            'bmp' => 'image',
            'html' => 'markup',
            'jpeg' => 'image',
            'mov' => 'video',
            'mp3' => 'audio',
            'mpeg' => 'video',
            'png' => 'image',
            'quicktime' => 'video',
            'tiff' => 'image',
            'wav' => 'audio',
            'wmv' => 'video',
        ];

        $custom = [];
        if (!empty($CFG->customfiletypes)) {
            if (array_key_exists('customfiletypes', $CFG->config_php_settings)) {
                // It's set in config.php, so the MIME icons can't be upgraded automatically.
                echo("\nYou need to manually check customfiletypes in config.php because some MIME icons have been removed!\n");
            } else {
                // It's a JSON string in the config table.
                $custom = json_decode($CFG->customfiletypes);
            }
        }

        $changed = false;
        foreach ($custom as $customentry) {
            if (!empty($customentry->icon) && array_key_exists($customentry->icon, $replacedicons)) {
                $customentry->icon = $replacedicons[$customentry->icon];
                $changed = true;
            }
        }

        if ($changed) {
            // Save the new customfiletypes.
            set_config('customfiletypes', json_encode($custom));
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023082200.01);
    }

    if ($oldversion < 2023082200.02) {
        // Some MIME icons have been removed. They need to be replaced to 'unknown' for custom MIME types.
        $removedicons = array_flip([
            'clip-353',
            'edit',
            'env',
            'explore',
            'folder-open',
            'help',
            'move',
            'parent',
        ]);

        $custom = [];
        if (!empty($CFG->customfiletypes)) {
            if (array_key_exists('customfiletypes', $CFG->config_php_settings)) {
                // It's set in config.php, so the MIME icons can't be upgraded automatically.
                echo("\nYou need to manually check customfiletypes in config.php because some MIME icons have been removed!\n");
            } else {
                // It's a JSON string in the config table.
                $custom = json_decode($CFG->customfiletypes);
            }
        }

        $changed = false;
        foreach ($custom as $customentry) {
            if (!empty($customentry->icon) && array_key_exists($customentry->icon, $removedicons)) {
                // The icon has been removed, so set it to unknown.
                $customentry->icon = 'unknown';
                $changed = true;
            }
        }

        if ($changed) {
            // Save the new customfiletypes.
            set_config('customfiletypes', json_encode($custom));
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023082200.02);
    }

    if ($oldversion < 2023082200.04) {
        // Remove any non-unique filters/conditions.
        $duplicates = $DB->get_records_sql("
            SELECT MIN(id) AS id, reportid, uniqueidentifier, iscondition
              FROM {reportbuilder_filter}
          GROUP BY reportid, uniqueidentifier, iscondition
            HAVING COUNT(*) > 1");

        foreach ($duplicates as $duplicate) {
            $DB->delete_records_select(
                'reportbuilder_filter',
                'id <> :id AND reportid = :reportid AND uniqueidentifier = :uniqueidentifier AND iscondition = :iscondition',
                (array) $duplicate
            );
        }

        // Define index report-filter (unique) to be added to reportbuilder_filter.
        $table = new xmldb_table('reportbuilder_filter');
        $index = new xmldb_index('report-filter', XMLDB_INDEX_UNIQUE, ['reportid', 'uniqueidentifier', 'iscondition']);

        // Conditionally launch add index report-filter.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023082200.04);
    }

    if ($oldversion < 2023082600.02) {
        // Get all the ids of users who still have md5 hashed passwords.
        if ($DB->sql_regex_supported()) {
            // If the database supports regex, we can add an exact check for md5.
            $condition = 'password ' . $DB->sql_regex() . ' :pattern';
            $params = ['pattern' => "^[a-fA-F0-9]{32}$"];
        } else {
            // Otherwise, we need to use a NOT LIKE condition and rule out bcrypt.
            $condition = $DB->sql_like('password', ':pattern', true, false, true);
            $params = ['pattern' => '$2y$%'];
        }

        // Regardless of database regex support we check the hash length which should be enough.
        // But extra regex or like matching makes sure.
        $sql = "SELECT id FROM {user} WHERE " . $DB->sql_length('password') . " = 32 AND $condition";
        $userids = $DB->get_fieldset_sql($sql, $params);

        // Update the password for each user with a new SHA-512 hash.
        // Users won't know this password, but they can reset it. This is a security measure,
        // in case the database is compromised or the hash has been leaked elsewhere.
        foreach ($userids as $userid) {
            $password = base64_encode(random_bytes(24)); // Generate a new password for the user.

            $user = new \stdClass();
            $user->id = $userid;
            $user->password = hash_internal_user_password($password);
            $DB->update_record('user', $user, true);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023082600.02);
    }

    if ($oldversion < 2023082600.03) {
        // The previous default configuration had a typo, check for its presence and correct if necessary.
        $sensiblesettings = get_config('adminpresets', 'sensiblesettings');
        if (strpos($sensiblesettings, 'smtppass@none') !== false) {
            $newsensiblesettings = str_replace('smtppass@none', 'smtppass@@none', $sensiblesettings);
            set_config('sensiblesettings', $newsensiblesettings, 'adminpresets');
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023082600.03);
    }

    if ($oldversion < 2023082600.05) {
        unset_config('completiondefault');

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023082600.05);
    }

    if ($oldversion < 2023090100.00) {
        // Upgrade MIME type for existing PSD files.
        $DB->set_field_select(
            'files',
            'mimetype',
            'image/vnd.adobe.photoshop',
            $DB->sql_like('filename', '?', false),
            ['%.psd']
        );

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023090100.00);
    }

    if ($oldversion < 2023090200.01) {
        // Define table moodlenet_share_progress to be created.
        $table = new xmldb_table('moodlenet_share_progress');

        // Adding fields to table moodlenet_share_progress.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('type', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('resourceurl', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '2', null, null, null, null);

        // Adding keys to table moodlenet_share_progress.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for moodlenet_share_progress.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023090200.01);
    }

    if ($oldversion < 2023091300.03) {
        // Delete all the searchanywhere prefs in user_preferences table.
        $DB->delete_records('user_preferences', ['name' => 'userselector_searchanywhere']);
        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023091300.03);
    }

    if ($oldversion < 2023100400.01) {
        // Delete datakey with datavalue -1.
        $DB->delete_records('messageinbound_datakeys', ['datavalue' => '-1']);
        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023100400.01);
    }

    if ($oldversion < 2023100400.03) {
        // Define field id to be added to communication.
        $table = new xmldb_table('communication');

        // Add the field and allow it to be nullable.
        // We need to backfill data before setting it to NOT NULL.
        $field = new xmldb_field(
            name: 'contextid',
            type: XMLDB_TYPE_INTEGER,
            precision: '10',
            notnull: null,
            previous: 'id',
        );

        // Conditionally launch add field id.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Fill the existing data.
        $sql = <<<EOF
                    SELECT comm.id, c.id AS contextid
                      FROM {communication} comm
                INNER JOIN {context} c ON c.instanceid = comm.instanceid AND c.contextlevel = :contextcourse
                     WHERE comm.contextid IS NULL
                       AND comm.instancetype = :instancetype
        EOF;
        $rs = $DB->get_recordset_sql(
            sql: $sql,
            params: [
                'contextcourse' => CONTEXT_COURSE,
                'instancetype' => 'coursecommunication',
            ],
        );
        foreach ($rs as $comm) {
            $DB->set_field(
                table: 'communication',
                newfield: 'contextid',
                newvalue: $comm->contextid,
                conditions: [
                    'id' => $comm->id,
                ],
            );
        }
        $rs->close();

        $systemcontext = \core\context\system::instance();
        $DB->set_field_select(
            table: 'communication',
            newfield: 'contextid',
            newvalue: $systemcontext->id,
            select: 'contextid IS NULL',
        );

        // Now make it NOTNULL.
        $field = new xmldb_field(
            name: 'contextid',
            type: XMLDB_TYPE_INTEGER,
            precision: '10',
            notnull:  XMLDB_NOTNULL,
        );
        $dbman->change_field_notnull($table, $field);

        // Add the contextid constraint.
        $key = new xmldb_key('contextid', XMLDB_KEY_FOREIGN, ['contextid'], 'context', ['id']);
        $dbman->add_key($table, $key);

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023100400.03);
    }

    // Automatically generated Moodle v4.3.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2023110900.00) {
        // Reorder the editors to make Tiny the default for all upgrades.
        $editors = [];
        array_push($editors, 'tiny');
        $list = explode(',', $CFG->texteditors);
        foreach ($list as $editor) {
            if ($editor != 'tiny') {
                array_push($editors, $editor);
            }
        }
        set_config('texteditors', implode(',', $editors));

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023110900.00);
    }

    if ($oldversion < 2023120100.01) {
        // The $CFG->linkcoursesections setting has been removed because it's not required anymore.
        // From now, sections will be always linked because a new page, section.php, has been created to display a single section.
        unset_config('linkcoursesections');

        upgrade_main_savepoint(true, 2023120100.01);
    }

    if ($oldversion < 2023121800.02) {
        // Define field attemptsavailable to be added to task_adhoc.
        $table = new xmldb_table('task_adhoc');
        $field = new xmldb_field(
            name: 'attemptsavailable',
            type: XMLDB_TYPE_INTEGER,
            precision: '2',
            unsigned: null,
            notnull: null,
            sequence: null,
            default: null,
            previous: 'pid',
        );

        // Conditionally launch add field attemptsavailable.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Set attemptsavailable to 0 for the tasks that have not been run before.
        // Set attemptsavailable to 1 for the tasks that have been run and failed before.
        $DB->execute('
            UPDATE {task_adhoc}
               SET attemptsavailable = CASE
                                            WHEN faildelay = 0 THEN 1
                                            WHEN faildelay > 0 THEN 0
                                       END
        ');

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023121800.02);
    }

    if ($oldversion < 2023122100.01) {

        // Define field component to be added to course_sections.
        $table = new xmldb_table('course_sections');
        $field = new xmldb_field('component', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'availability');

        // Conditionally launch add field component.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field itemid to be added to course_sections.
        $field = new xmldb_field('itemid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'component');

        // Conditionally launch add field itemid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2023122100.01);
    }

    if ($oldversion < 2023122100.02) {
        $sqllike = $DB->sql_like('filtercondition', '?');
        $params[] = '%includesubcategories%';

        $sql = "SELECT qsr.* FROM {question_set_references} qsr WHERE $sqllike";
        $results = $DB->get_recordset_sql($sql, $params);
        foreach ($results as $result) {
            $filtercondition = json_decode($result->filtercondition);
            if (isset($filtercondition->filter->category->includesubcategories)) {
                $filtercondition->filter->category->filteroptions =
                    ['includesubcategories' => $filtercondition->filter->category->includesubcategories];
                unset($filtercondition->filter->category->includesubcategories);
                $result->filtercondition = json_encode($filtercondition);
                $DB->update_record('question_set_references', $result);
            }
        }
        $results->close();

        upgrade_main_savepoint(true, 2023122100.02);
    }

    if ($oldversion < 2024010400.01) {

        // Define index timecreated (not unique) to be added to notifications.
        $table = new xmldb_table('notifications');
        $createdindex = new xmldb_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

        // Conditionally launch add index timecreated.
        if (!$dbman->index_exists($table, $createdindex)) {
            $dbman->add_index($table, $createdindex);
        }

        // Define index timeread (not unique) to be added to notifications.
        $readindex = new xmldb_index('timeread', XMLDB_INDEX_NOTUNIQUE, ['timeread']);

        // Conditionally launch add index timeread.
        if (!$dbman->index_exists($table, $readindex)) {
            $dbman->add_index($table, $readindex);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2024010400.01);
    }

    if ($oldversion < 2024012300.00) {

        // Define field valuetrust to be added to customfield_data.
        $table = new xmldb_table('customfield_data');
        $field = new xmldb_field('valuetrust', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'valueformat');

        // Conditionally launch add field valuetrust.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2024012300.00);
    }

    if ($oldversion < 2024020200.01) {
        // If h5plib_v124 is no longer present, remove it.
        if (!file_exists($CFG->dirroot . '/h5p/h5plib/v124/version.php')) {
            // Clean config.
            uninstall_plugin('h5plib', 'v124');
        }

        // If h5plib_v126 is present, set it as the default one.
        if (file_exists($CFG->dirroot . '/h5p/h5plib/v126/version.php')) {
            set_config('h5plibraryhandler', 'h5plib_v126');
        }

        upgrade_main_savepoint(true, 2024020200.01);
    }

    if ($oldversion < 2024021500.01) {
        // Change default course formats order for sites never changed the default order.
        if (!get_config('core', 'format_plugins_sortorder')) {
            set_config('format_plugins_sortorder', 'topics,weeks,singleactivity,social');
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2024021500.01);
    }

    if ($oldversion < 2024021500.02) {
        // A [name => url] map of new OIDC endpoints to be updated/created.
        $endpointuris = [
            'authorization_endpoint' => 'https://clever.com/oauth/authorize',
            'token_endpoint' => 'https://clever.com/oauth/tokens',
            'userinfo_endpoint' => 'https://api.clever.com/userinfo',
            'jwks_uri' => 'https://clever.com/oauth/certs',
        ];

        // A [internalfield => externalfield] map of new OIDC-based user field mappings to be updated/created.
        $userfieldmappings = [
            'idnumber' => 'sub',
            'firstname' => 'given_name',
            'lastname' => 'family_name',
            'email' => 'email',
        ];

        $admin = get_admin();
        $adminid = $admin ? $admin->id : '0';

        $cleverservices = $DB->get_records('oauth2_issuer', ['servicetype' => 'clever']);
        foreach ($cleverservices as $cleverservice) {
            $time = time();

            // Insert/update the new endpoints.
            foreach ($endpointuris as $endpointname => $endpointuri) {
                $endpoint = ['issuerid' => $cleverservice->id, 'name' => $endpointname];
                $endpointid = $DB->get_field('oauth2_endpoint', 'id', $endpoint);

                if ($endpointid) {
                    $endpoint = array_merge($endpoint, [
                        'id' => $endpointid,
                        'url' => $endpointuri,
                        'timemodified' => $time,
                        'usermodified' => $adminid,
                    ]);
                    $DB->update_record('oauth2_endpoint', $endpoint);
                } else {
                    $endpoint = array_merge($endpoint, [
                        'url' => $endpointuri,
                        'timecreated' => $time,
                        'timemodified' => $time,
                        'usermodified' => $adminid,
                    ]);
                    $DB->insert_record('oauth2_endpoint', $endpoint);
                }
            }

            // Insert/update new user field mappings.
            foreach ($userfieldmappings as $internalfieldname => $externalfieldname) {
                $fieldmap = ['issuerid' => $cleverservice->id, 'internalfield' => $internalfieldname];
                $fieldmapid = $DB->get_field('oauth2_user_field_mapping', 'id', $fieldmap);

                if ($fieldmapid) {
                    $fieldmap = array_merge($fieldmap, [
                        'id' => $fieldmapid,
                        'externalfield' => $externalfieldname,
                        'timemodified' => $time,
                        'usermodified' => $adminid,
                    ]);
                    $DB->update_record('oauth2_user_field_mapping', $fieldmap);
                } else {
                    $fieldmap = array_merge($fieldmap, [
                        'externalfield' => $externalfieldname,
                        'timecreated' => $time,
                        'timemodified' => $time,
                        'usermodified' => $adminid,
                    ]);
                    $DB->insert_record('oauth2_user_field_mapping', $fieldmap);
                }
            }

            // Update the baseurl for the issuer.
            $cleverservice->baseurl = 'https://clever.com';
            $cleverservice->timemodified = $time;
            $cleverservice->usermodified = $adminid;
            $DB->update_record('oauth2_issuer', $cleverservice);
        }

        upgrade_main_savepoint(true, 2024021500.02);
    }

    if ($oldversion < 2024022300.02) {
        // Removed advanced grade item settings.
        unset_config('grade_item_advanced');

        upgrade_main_savepoint(true, 2024022300.02);
    }

    if ($oldversion < 2024030500.01) {

        // Define field firststartingtime to be added to task_adhoc.
        $table = new xmldb_table('task_adhoc');
        $field = new xmldb_field('firststartingtime', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'attemptsavailable');

        // Conditionally launch add field firststartingtime.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            // Main savepoint reached.
            upgrade_main_savepoint(true, 2024030500.01);
        }

    }

    if ($oldversion < 2024030500.02) {

        // Get all "select" custom field shortnames.
        $fieldshortnames = $DB->get_fieldset('customfield_field', 'shortname', ['type' => 'select']);

        // Ensure any used in custom reports columns are not using integer type aggregation.
        foreach ($fieldshortnames as $fieldshortname) {
            $DB->execute("
                UPDATE {reportbuilder_column}
                   SET aggregation = NULL
                 WHERE " . $DB->sql_like('uniqueidentifier', ':uniqueidentifier', false) . "
                   AND aggregation IN ('avg', 'max', 'min', 'sum')
            ", [
                'uniqueidentifier' => '%' . $DB->sql_like_escape(":customfield_{$fieldshortname}"),
            ]);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2024030500.02);
    }

    if ($oldversion < 2024032600.01) {

        // Changing precision of field attemptsavailable on table task_adhoc to (2).
        $table = new xmldb_table('task_adhoc');
        $field = new xmldb_field('attemptsavailable', XMLDB_TYPE_INTEGER, '2', null, null, null, null, 'pid');

        // Launch change of precision for field.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2024032600.01);
    }

    if ($oldversion < 2024041200.00) {
        // Define field blocking to be dropped from task_adhoc.
        $table = new xmldb_table('task_adhoc');
        $field = new xmldb_field('blocking');

        // Conditionally launch drop field blocking.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define field blocking to be dropped from task_scheduled.
        $table = new xmldb_table('task_scheduled');
        $field = new xmldb_field('blocking');

        // Conditionally launch drop field blocking.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_main_savepoint(true, 2024041200.00);
    }

    return true;
}

function tool_vault_404_upgrade_core_licenses() {
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
