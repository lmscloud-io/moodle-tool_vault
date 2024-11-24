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
// Mdlcode-disable cannot-parse-db-tablename.

namespace tool_vault\local\restoreactions\upgrade_27\helpers;

use backup;
use backup_general_helper;
use backup_helper_exception;
use core_text;

/**
 * Class general_helper
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class general_helper {

    /**
     * Returns all non-view and non-temp tables with sane names.
     * Prints list of non-supported tables using $OUTPUT->notification()
     *
     * @return array
     */
    public static function upgrade_mysql_get_supported_tables() {
        global $OUTPUT, $DB;

        $tables = array();
        $patprefix = str_replace('_', '\\_', $DB->get_prefix());
        $pregprefix = preg_quote($DB->get_prefix(), '/');

        $sql = "SHOW FULL TABLES LIKE '$patprefix%'";
        $rs = $DB->get_recordset_sql($sql);
        foreach ($rs as $record) {
            $record = array_change_key_case((array)$record, CASE_LOWER);
            $type = $record['table_type'];
            unset($record['table_type']);
            $fullname = array_shift($record);

            if ($pregprefix === '') {
                $name = $fullname;
            } else {
                $count = null;
                $name = preg_replace("/^$pregprefix/", '', $fullname, -1, $count);
                if ($count !== 1) {
                    continue;
                }
            }

            if (!preg_match("/^[a-z][a-z0-9_]*$/", $name)) {
                echo $OUTPUT->notification("Database table with invalid name '$fullname' detected, skipping.", 'notifyproblem');
                continue;
            }
            if ($type === 'VIEW') {
                echo $OUTPUT->notification("Unsupported database table view '$fullname' detected, skipping.", 'notifyproblem');
                continue;
            }
            $tables[$name] = $name;
        }
        $rs->close();

        return $tables;
    }

    /**
     * Remove all signed numbers from current database and change
     * text fields to long texts - mysql only.
     */
    public static function upgrade_mysql_fix_unsigned_and_lob_columns() {
        // We are not using standard API for changes of column
        // because everything 'signed'-related will be removed soon.

        // If anybody already has numbers higher than signed limit the execution stops
        // and tables must be fixed manually before continuing upgrade.

        global $DB;

        if ($DB->get_dbfamily() !== 'mysql') {
            return;
        }

        $prefix = $DB->get_prefix();
        $tables = self::upgrade_mysql_get_supported_tables();

        $tablecount = count($tables);
        $i = 0;
        foreach ($tables as $table) {
            $i++;

            $changes = array();

            $sql = "SHOW COLUMNS FROM `{{$table}}`";
            $rs = $DB->get_recordset_sql($sql);
            foreach ($rs as $column) {
                $column = (object)array_change_key_case((array)$column, CASE_LOWER);
                if (stripos($column->type, 'unsigned') !== false) {
                    $maxvalue = 0;
                    if (preg_match('/^int/i', $column->type)) {
                        $maxvalue = 2147483647;
                    } else if (preg_match('/^medium/i', $column->type)) {
                        $maxvalue = 8388607;
                    } else if (preg_match('/^smallint/i', $column->type)) {
                        $maxvalue = 32767;
                    } else if (preg_match('/^tinyint/i', $column->type)) {
                        $maxvalue = 127;
                    }
                    if ($maxvalue) {
                        // Make sure nobody is abusing our integer ranges - moodle int sizes are in digits, not bytes!!!
                        $invalidcount = $DB->get_field_sql("SELECT COUNT('x') FROM `{{$table}}` WHERE `$column->field` > :maxnumber", array('maxnumber'=>$maxvalue));
                        if ($invalidcount) {
                            throw new moodle_exception('notlocalisederrormessage', 'error', new moodle_url('/admin/'), "Database table '{$table}'' contains unsigned column '{$column->field}' with $invalidcount values that are out of allowed range, upgrade can not continue.");
                        }
                    }
                    $type = preg_replace('/unsigned/i', 'signed', $column->type);
                    $notnull = ($column->null === 'NO') ? 'NOT NULL' : 'NULL';
                    $default = (!is_null($column->default) and $column->default !== '') ? "DEFAULT '$column->default'" : '';
                    $autoinc = (stripos($column->extra, 'auto_increment') !== false) ? 'AUTO_INCREMENT' : '';
                    // Primary and unique not necessary here, change_database_structure does not add prefix.
                    $changes[] = "MODIFY COLUMN `$column->field` $type $notnull $default $autoinc";

                } else if ($column->type === 'tinytext' or $column->type === 'mediumtext' or $column->type === 'text') {
                    $notnull = ($column->null === 'NO') ? 'NOT NULL' : 'NULL';
                    $default = (!is_null($column->default) and $column->default !== '') ? "DEFAULT '$column->default'" : '';
                    // Primary, unique and inc are not supported for texts.
                    $changes[] = "MODIFY COLUMN `$column->field` LONGTEXT $notnull $default";

                } else if ($column->type === 'tinyblob' or $column->type === 'mediumblob' or $column->type === 'blob') {
                    $notnull = ($column->null === 'NO') ? 'NOT NULL' : 'NULL';
                    $default = (!is_null($column->default) and $column->default !== '') ? "DEFAULT '$column->default'" : '';
                    // Primary, unique and inc are not supported for blobs.
                    $changes[] = "MODIFY COLUMN `$column->field` LONGBLOB $notnull $default";
                }

            }
            $rs->close();

            if ($changes) {
                $sql = "ALTER TABLE `{$prefix}$table` ".implode(', ', $changes);
                $DB->change_database_structure($sql);
            }
        }
    }

    /**
     * This function finds duplicate records (based on combinations of fields that should be unique)
     * and then progamatically generated a "most correct" version of the data, update and removing
     * records as appropriate
     *
     * Thanks to Dan Marsden for help
     *
     * @param   string  $table      Table name
     * @param   array   $uniques    Array of field names that should be unique
     * @param   array   $fieldstocheck  Array of fields to generate "correct" data from (optional)
     * @return  void
     */
    public static function upgrade_course_completion_remove_duplicates($table, $uniques, $fieldstocheck = array()) {
        global $DB;

        // Find duplicates
        $sql_cols = implode(', ', $uniques);

        $sql = "SELECT {$sql_cols} FROM {{$table}} GROUP BY {$sql_cols} HAVING (count(id) > 1)";
        $duplicates = $DB->get_recordset_sql($sql, array());

        // Loop through duplicates
        foreach ($duplicates as $duplicate) {
            $pointer = 0;

            // Generate SQL for finding records with these duplicate uniques
            $sql_select = implode(' = ? AND ', $uniques).' = ?'; // builds "fieldname = ? AND fieldname = ?"
            $uniq_values = array();
            foreach ($uniques as $u) {
                $uniq_values[] = $duplicate->$u;
            }

            $sql_order = implode(' DESC, ', $uniques).' DESC'; // builds "fieldname DESC, fieldname DESC"

            // Get records with these duplicate uniques
            $records = $DB->get_records_select(
                $table,
                $sql_select,
                $uniq_values,
                $sql_order
            );

            // Loop through and build a "correct" record, deleting the others
            $needsupdate = false;
            $origrecord = null;
            foreach ($records as $record) {
                $pointer++;
                if ($pointer === 1) { // keep 1st record but delete all others.
                    $origrecord = $record;
                } else {
                    // If we have fields to check, update original record
                    if ($fieldstocheck) {
                        // we need to keep the "oldest" of all these fields as the valid completion record.
                        // but we want to ignore null values
                        foreach ($fieldstocheck as $f) {
                            if ($record->$f && (($origrecord->$f > $record->$f) || !$origrecord->$f)) {
                                $origrecord->$f = $record->$f;
                                $needsupdate = true;
                            }
                        }
                    }
                    $DB->delete_records($table, array('id' => $record->id));
                }
            }
            if ($needsupdate || isset($origrecord->reaggregate)) {
                // If this table has a reaggregate field, update to force recheck on next cron run
                if (isset($origrecord->reaggregate)) {
                    $origrecord->reaggregate = time();
                }
                $DB->update_record($table, $origrecord);
            }
        }
    }

    /**
     * Find questions missing an existing category and associate them with
     * a category which purpose is to gather them.
     *
     * @return void
     */
    public static function upgrade_save_orphaned_questions() {
        global $DB;

        // Looking for orphaned questions
        $orphans = $DB->record_exists_select('question',
                'NOT EXISTS (SELECT 1 FROM {question_categories} WHERE {question_categories}.id = {question}.category)');
        if (!$orphans) {
            return;
        }

        // Generate a unique stamp for the orphaned questions category, easier to identify it later on
        $uniquestamp = "unknownhost+120719170400+orphan";
        $systemcontext = \context_system::instance();

        // Create the orphaned category at system level
        $cat = $DB->get_record('question_categories', array('stamp' => $uniquestamp,
                'contextid' => $systemcontext->id));
        if (!$cat) {
            $cat = new stdClass();
            $cat->parent = 0;
            $cat->contextid = $systemcontext->id;
            $cat->name = 'Questions saved from deleted categories';
            $cat->info = 'Occasionally, typically due to old software bugs, questions can remain in the database even though the corresponding question category has been deleted. Of course, this should not happen, it has happened in the past on this site. This category has been created automatically, and the orphaned questions moved here so that you can manage them. Note that any images or media files used by these questions have probably been lost.';
            $cat->sortorder = 999;
            $cat->stamp = $uniquestamp;
            $cat->id = $DB->insert_record("question_categories", $cat);
        }

        // Set a category to those orphans
        $params = array('catid' => $cat->id);
        $DB->execute('UPDATE {question} SET category = :catid WHERE NOT EXISTS
                (SELECT 1 FROM {question_categories} WHERE {question_categories}.id = {question}.category)', $params);
    }

    /**
     * Rename old backup files to current backup files.
     *
     * When added the setting 'backup_shortname' (MDL-28657) the backup file names did not contain the id of the course.
     * Further we fixed that behaviour by forcing the id to be always present in the file name (MDL-33812).
     * This function will explore the backup directory and attempt to rename the previously created files to include
     * the id in the name. Doing this will put them back in the process of deleting the excess backups for each course.
     *
     * This function manually recreates the file name, instead of using
     * {@link backup_plan_dbops::get_default_backup_filename()}, use it carefully if you're using it outside of the
     * usual upgrade process.
     *
     * @see backup_cron_automated_helper::remove_excess_backups()
     * @link http://tracker.moodle.org/browse/MDL-35116
     * @return void
     * @since Moodle 2.4
     */
    public static function upgrade_rename_old_backup_files_using_shortname() {
        global $CFG;
        $dir = get_config('backup', 'backup_auto_destination');
        $useshortname = get_config('backup', 'backup_shortname');
        if (empty($dir) || !is_dir($dir) || !is_writable($dir)) {
            return;
        }

        require_once($CFG->dirroot.'/backup/util/includes/backup_includes.php'); // TODO no requries!
        $backupword = str_replace(' ', '_', core_text::strtolower(get_string('backupfilename')));
        $backupword = trim(clean_filename($backupword), '_');
        $filename = $backupword . '-' . backup::FORMAT_MOODLE . '-' . backup::TYPE_1COURSE . '-';
        $regex = '#^'.preg_quote($filename, '#').'.*\.mbz$#';
        $thirtyapril = strtotime('30 April 2012 00:00');

        // Reading the directory.
        if (!$files = scandir($dir)) {
            return;
        }
        foreach ($files as $file) {
            // Skip directories and files which do not start with the common prefix.
            // This avoids working on files which are not related to this issue.
            if (!is_file($dir . '/' . $file) || !preg_match($regex, $file)) {
                continue;
            }

            // Extract the information from the XML file.
            try {
                $bcinfo = backup_general_helper::get_backup_information_from_mbz($dir . '/' . $file);
            } catch (backup_helper_exception $e) {
                // Some error while retrieving the backup informations, skipping...
                continue;
            }

            // Make sure this a course backup.
            if ($bcinfo->format !== backup::FORMAT_MOODLE || $bcinfo->type !== backup::TYPE_1COURSE) {
                continue;
            }

            // Skip the backups created before the short name option was initially introduced (MDL-28657).
            // This was integrated on the 2nd of May 2012. Let's play safe with timezone and use the 30th of April.
            if ($bcinfo->backup_date < $thirtyapril) {
                continue;
            }

            // Let's check if the file name contains the ID where it is supposed to be, if it is the case then
            // we will skip the file. Of course it could happen that the course ID is identical to the course short name
            // even though really unlikely, but then renaming this file is not necessary. If the ID is not found in the
            // file name then it was probably the short name which was used.
            $idfilename = $filename . $bcinfo->original_course_id . '-';
            $idregex = '#^'.preg_quote($idfilename, '#').'.*\.mbz$#';
            if (preg_match($idregex, $file)) {
                continue;
            }

            // Generating the file name manually. We do not use backup_plan_dbops::get_default_backup_filename() because
            // it will query the database to get some course information, and the course could not exist any more.
            $newname = $filename . $bcinfo->original_course_id . '-';
            if ($useshortname) {
                $shortname = str_replace(' ', '_', $bcinfo->original_course_shortname);
                $shortname = core_text::strtolower(trim(clean_filename($shortname), '_'));
                $newname .= $shortname . '-';
            }

            $backupdateformat = str_replace(' ', '_', get_string('backupnameformat', 'langconfig'));
            $date = userdate($bcinfo->backup_date, $backupdateformat, 99, false);
            $date = core_text::strtolower(trim(clean_filename($date), '_'));
            $newname .= $date;

            if (isset($bcinfo->root_settings['users']) && !$bcinfo->root_settings['users']) {
                $newname .= '-nu';
            } else if (isset($bcinfo->root_settings['anonymize']) && $bcinfo->root_settings['anonymize']) {
                $newname .= '-an';
            }
            $newname .= '.mbz';

            // Final check before attempting the renaming.
            if ($newname == $file || file_exists($dir . '/' . $newname)) {
                continue;
            }
            @rename($dir . '/' . $file, $dir . '/' . $newname);
        }
    }

    /**
     * Migrate NTEXT to NVARCHAR(MAX).
     */
    public static function upgrade_mssql_nvarcharmax() {
        global $DB;

        if ($DB->get_dbfamily() !== 'mssql') {
            return;
        }

        $prefix = $DB->get_prefix();
        $tables = $DB->get_tables(false);

        $i = 0;
        foreach ($tables as $table) {
            $i++;

            $columns = array();

            $sql = "SELECT column_name
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE table_name = '{{$table}}' AND UPPER(data_type) = 'NTEXT'";
            $rs = $DB->get_recordset_sql($sql);
            foreach ($rs as $column) {
                $columns[] = $column->column_name;
            }
            $rs->close();

            if ($columns) {
                $updates = array();
                foreach ($columns as $column) {
                    // Change the definition.
                    $sql = "ALTER TABLE {$prefix}$table ALTER COLUMN $column NVARCHAR(MAX)";
                    $DB->change_database_structure($sql);
                    $updates[] = "$column = $column";
                }

                // Now force the migration of text data to new optimised storage.
                $sql = "UPDATE {{$table}} SET ".implode(', ', $updates);
                $DB->execute($sql);
            }
        }
    }

    /**
     * Migrate IMAGE to VARBINARY(MAX).
     */
    public static function upgrade_mssql_varbinarymax() {
        global $DB;

        if ($DB->get_dbfamily() !== 'mssql') {
            return;
        }

        $prefix = $DB->get_prefix();
        $tables = $DB->get_tables(false);

        $i = 0;
        foreach ($tables as $table) {
            $i++;

            $columns = array();

            $sql = "SELECT column_name
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE table_name = '{{$table}}' AND UPPER(data_type) = 'IMAGE'";
            $rs = $DB->get_recordset_sql($sql);
            foreach ($rs as $column) {
                $columns[] = $column->column_name;
            }
            $rs->close();

            if ($columns) {

                foreach ($columns as $column) {
                    // Change the definition.
                    $sql = "ALTER TABLE {$prefix}$table ALTER COLUMN $column VARBINARY(MAX)";
                    $DB->change_database_structure($sql);
                }

                // Binary columns should not be used, do not waste time optimising the storage.
            }

        }
    }

    /**
     * Detect file areas with missing root directory records and add them.
     */
    public static function upgrade_fix_missing_root_folders() {
        global $DB, $USER;

        $transaction = $DB->start_delegated_transaction();

        $sql = "SELECT contextid, component, filearea, itemid
                FROM {files}
                WHERE (component <> 'user' OR filearea <> 'draft')
            GROUP BY contextid, component, filearea, itemid
                HAVING MAX(CASE WHEN filename = '.' AND filepath = '/' THEN 1 ELSE 0 END) = 0";

        $rs = $DB->get_recordset_sql($sql);
        $defaults = array('filepath' => '/',
            'filename' => '.',
            'userid' => 0, // Don't rely on any particular user for these system records.
            'filesize' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
            'contenthash' => sha1(''));
        foreach ($rs as $r) {
            $pathhash = sha1("/$r->contextid/$r->component/$r->filearea/$r->itemid/.");
            $DB->insert_record('files', (array)$r + $defaults +
                array('pathnamehash' => $pathhash));
        }
        $rs->close();
        $transaction->allow_commit();
    }

    /**
     * This upgrade script fixes the mismatches between DB fields course_modules.section
     * and course_sections.sequence. It makes sure that each module is included
     * in the sequence of at least one section.
     * Note that this script is different from admin/cli/fix_course_sortorder.php
     * in the following ways:
     * 1. It does not fix the cases when module appears several times in section(s) sequence(s) -
     *    it will be done automatically on the next viewing of the course.
     * 2. It does not remove non-existing modules from section sequences - administrator
     *    has to run the CLI script to do it.
     * 3. When this script finds an orphaned module it adds it to the section but makes hidden
     *    where CLI script does not change the visiblity specified in the course_modules table.
     */
    public static function upgrade_course_modules_sequences() {
        global $DB;

        // Find all modules that point to the section which does not point back to this module.
        $sequenceconcat = $DB->sql_concat("','", "s.sequence", "','");
        $moduleconcat = $DB->sql_concat("'%,'", "m.id", "',%'");
        $sql = "SELECT m.id, m.course, m.section, s.sequence
            FROM {course_modules} m LEFT OUTER JOIN {course_sections} s
            ON m.course = s.course and m.section = s.id
            WHERE s.sequence IS NULL OR ($sequenceconcat NOT LIKE $moduleconcat)
            ORDER BY m.course";
        $rs = $DB->get_recordset_sql($sql);
        $sections = null;
        foreach ($rs as $cm) {
            if (!isset($sections[$cm->course])) {
                // Retrieve all sections for the course (only once for each corrupt course).
                $sections = array($cm->course =>
                        $DB->get_records('course_sections', array('course' => $cm->course),
                                'section', 'id, section, sequence, visible'));
                if (empty($sections[$cm->course])) {
                    // Very odd - the course has a module in it but has no sections. Create 0-section.
                    $newsection = array('sequence' => '', 'section' => 0, 'visible' => 1);
                    $newsection['id'] = $DB->insert_record('course_sections',
                            $newsection + array('course' => $cm->course, 'summary' => '', 'summaryformat' => FORMAT_HTML));
                    $sections[$cm->course] = array($newsection['id'] => (object)$newsection);
                }
            }
            // Attempt to find the section that has this module in it's sequence.
            // If there are several of them, pick the last because this is what get_fast_modinfo() does.
            $sectionid = null;
            foreach ($sections[$cm->course] as $section) {
                if (!empty($section->sequence) && in_array($cm->id, preg_split('/,/', $section->sequence))) {
                    $sectionid = $section->id;
                }
            }
            if ($sectionid) {
                // Found the section. Update course_module to point to the correct section.
                $params = array('id' => $cm->id, 'section' => $sectionid);
                if (!$sections[$cm->course][$sectionid]->visible) {
                    $params['visible'] = 0;
                }
                $DB->update_record('course_modules', $params);
            } else {
                // No section in the course has this module in it's sequence.
                if (isset($sections[$cm->course][$cm->section])) {
                    // Try to add module to the section it points to (if it is valid).
                    $sectionid = $cm->section;
                } else {
                    // Section not found. Just add to the first available section.
                    reset($sections[$cm->course]);
                    $sectionid = key($sections[$cm->course]);
                }
                $newsequence = ltrim($sections[$cm->course][$sectionid]->sequence . ',' . $cm->id, ',');
                $sections[$cm->course][$sectionid]->sequence = $newsequence;
                $DB->update_record('course_sections', array('id' => $sectionid, 'sequence' => $newsequence));
                // Make module invisible because it was not displayed at all before this upgrade script.
                $DB->update_record('course_modules', array('id' => $cm->id, 'section' => $sectionid, 'visible' => 0, 'visibleold' => 0));
            }
        }
        $rs->close();
        unset($sections);

        // Note that we don't need to reset course cache here because it is reset automatically after upgrade.
    }

    /**
     * Detect duplicate grade item sortorders and resort the
     * items to remove them.
     */
    public static function upgrade_grade_item_fix_sortorder() {
        global $DB;

        // The simple way to fix these sortorder duplicates would be simply to resort each
        // affected course. But in order to reduce the impact of this upgrade step we're trying
        // to do it more efficiently by doing a series of update statements rather than updating
        // every single grade item in affected courses.

        $sql = "SELECT DISTINCT g1.courseid
                FROM {grade_items} g1
                JOIN {grade_items} g2 ON g1.courseid = g2.courseid
                WHERE g1.sortorder = g2.sortorder AND g1.id != g2.id
                ORDER BY g1.courseid ASC";
        foreach ($DB->get_fieldset_sql($sql) as $courseid) {
            $transaction = $DB->start_delegated_transaction();
            $items = $DB->get_records('grade_items', array('courseid' => $courseid), '', 'id, sortorder, sortorder AS oldsort');

            // Get all duplicates in course order, highest sort order, and higest id first so that we can make space at the
            // bottom higher end of the sort orders and work down by id.
            $sql = "SELECT DISTINCT g1.id, g1.sortorder
                    FROM {grade_items} g1
                    JOIN {grade_items} g2 ON g1.courseid = g2.courseid
                    WHERE g1.sortorder = g2.sortorder AND g1.id != g2.id AND g1.courseid = :courseid
                    ORDER BY g1.sortorder DESC, g1.id DESC";

            // This is the O(N*N) like the database version we're replacing, but at least the constants are a billion times smaller...
            foreach ($DB->get_records_sql($sql, array('courseid' => $courseid)) as $duplicate) {
                foreach ($items as $item) {
                    if ($item->sortorder > $duplicate->sortorder || ($item->sortorder == $duplicate->sortorder && $item->id > $duplicate->id)) {
                        $item->sortorder += 1;
                    }
                }
            }
            foreach ($items as $item) {
                if ($item->sortorder != $item->oldsort) {
                    $DB->update_record('grade_items', array('id' => $item->id, 'sortorder' => $item->sortorder));
                }
            }

            $transaction->allow_commit();
        }
    }

    /**
     * Updates a single item (course module or course section) to transfer the
     * availability settings from the old to the new format.
     *
     * Note: We do not convert groupmembersonly for modules at present. If we did,
     * $groupmembersonly would be set to the groupmembersonly option for the
     * module. Since we don't, it will be set to 0 for modules, and 1 for sections
     * if they have a grouping.
     *
     * @param int $groupmembersonly 1 if activity has groupmembersonly option
     * @param int $groupingid Grouping id (0 = none)
     * @param int $availablefrom Available from time (0 = none)
     * @param int $availableuntil Available until time (0 = none)
     * @param int $showavailability Show availability (1) or hide activity entirely
     * @param array $availrecs Records from course_modules/sections_availability
     * @param array $fieldrecs Records from course_modules/sections_avail_fields
     */
    public static function upgrade_availability_item($groupmembersonly, $groupingid,
            $availablefrom, $availableuntil, $showavailability,
            array $availrecs, array $fieldrecs) {
        global $CFG, $DB;
        $conditions = array();
        $shows = array();

        // Group members only condition (if enabled).
        if ($CFG->enablegroupmembersonly && $groupmembersonly) {
            if ($groupingid) {
                $conditions[] = '{"type":"grouping"' .
                        ($groupingid ? ',"id":' . $groupingid : '') . '}';
            } else {
                // No grouping specified, so allow any group.
                $conditions[] = '{"type":"group"}';
            }
            // Group members only condition was not displayed to students.
            $shows[] = 'false';

            // In the unlikely event that the site had enablegroupmembers only
            // but NOT enableavailability, we need to turn this on now.
            if (!$CFG->enableavailability) {
                set_config('enableavailability', 1);
            }
        }

        // Date conditions.
        if ($availablefrom) {
            $conditions[] = '{"type":"date","d":">=","t":' . $availablefrom . '}';
            $shows[] = $showavailability ? 'true' : 'false';
        }
        if ($availableuntil) {
            $conditions[] = '{"type":"date","d":"<","t":' . $availableuntil . '}';
            // Until dates never showed to students.
            $shows[] = 'false';
        }

        // Conditions from _availability table.
        foreach ($availrecs as $rec) {
            if (!empty($rec->sourcecmid)) {
                // Completion condition.
                $conditions[] = '{"type":"completion","cm":' . $rec->sourcecmid .
                        ',"e":' . $rec->requiredcompletion . '}';
            } else {
                // Grade condition.
                $minmax = '';
                if (!empty($rec->grademin)) {
                    $minmax .= ',"min":' . sprintf('%.5f', $rec->grademin);
                }
                if (!empty($rec->grademax)) {
                    $minmax .= ',"max":' . sprintf('%.5f', $rec->grademax);
                }
                $conditions[] = '{"type":"grade","id":' . $rec->gradeitemid . $minmax . '}';
            }
            $shows[] = $showavailability ? 'true' : 'false';
        }

        // Conditions from _fields table.
        foreach ($fieldrecs as $rec) {
            if (isset($rec->userfield)) {
                // Standard field.
                $fieldbit = ',"sf":' . json_encode($rec->userfield);
            } else {
                // Custom field.
                $fieldbit = ',"cf":' . json_encode($rec->shortname);
            }
            // Value is not included for certain operators.
            switch($rec->operator) {
                case 'isempty':
                case 'isnotempty':
                    $valuebit = '';
                    break;

                default:
                    $valuebit = ',"v":' . json_encode($rec->value);
                    break;
            }
            $conditions[] = '{"type":"profile","op":"' . $rec->operator . '"' .
                    $fieldbit . $valuebit . '}';
            $shows[] = $showavailability ? 'true' : 'false';
        }

        // If there are some conditions, set them into database.
        if ($conditions) {
            return '{"op":"&","showc":[' . implode(',', $shows) . '],' .
                    '"c":[' . implode(',', $conditions) . ']}';
        } else {
            return null;
        }
    }

    /**
     * Updates the mime-types for files that exist in the database, based on their
     * file extension.
     *
     * @param array $filetypes Array with file extension as the key, and mimetype as the value
     */
    public static function upgrade_mimetypes($filetypes) {
        global $DB;
        $select = $DB->sql_like('filename', '?', false);
        foreach ($filetypes as $extension=>$mimetype) {
            $DB->set_field_select(
                'files',
                'mimetype',
                $mimetype,
                $select,
                array($extension)
            );
        }
    }

    /**
     * Detect draft file areas with missing root directory records and add them.
     */
    public static function upgrade_fix_missing_root_folders_draft() {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        $sql = "SELECT contextid, itemid, MAX(timecreated) AS timecreated, MAX(timemodified) AS timemodified
                FROM {files}
                WHERE (component = 'user' AND filearea = 'draft')
            GROUP BY contextid, itemid
                HAVING MAX(CASE WHEN filename = '.' AND filepath = '/' THEN 1 ELSE 0 END) = 0";

        $rs = $DB->get_recordset_sql($sql);
        $defaults = array('component' => 'user',
            'filearea' => 'draft',
            'filepath' => '/',
            'filename' => '.',
            'userid' => 0, // Don't rely on any particular user for these system records.
            'filesize' => 0,
            'contenthash' => sha1(''));
        foreach ($rs as $r) {
            $r->pathnamehash = sha1("/$r->contextid/user/draft/$r->itemid/.");
            $DB->insert_record('files', (array)$r + $defaults);
        }
        $rs->close();
        $transaction->allow_commit();
    }
}
