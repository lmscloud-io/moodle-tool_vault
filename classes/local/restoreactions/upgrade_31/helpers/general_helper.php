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

namespace tool_vault\local\restoreactions\upgrade_31\helpers;

use context_course;
use context_system;
use stdClass;

/**
 * Class general_helper
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class general_helper {

    /**
     * Updates the mime-types for files that exist in the database, based on their
     * file extension.
     *
     * @param array $filetypes Array with file extension as the key, and mimetype as the value
     */
    public static function upgrade_mimetypes($filetypes) {
        global $DB;
        $select = $DB->sql_like('filename', '?', false);
        foreach ($filetypes as $extension => $mimetype) {
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
     * Using data for a single course-module that has groupmembersonly enabled,
     * returns the new availability value that incorporates the correct
     * groupmembersonly option.
     *
     * Included as a function so that it can be shared between upgrade and restore,
     * and unit-tested.
     *
     * @param int $groupingid Grouping id for the course-module (0 if none)
     * @param string $availability Availability JSON data for the module (null if none)
     * @return string New value for availability for the module
     */
    public static function upgrade_group_members_only($groupingid, $availability) {
        // Work out the new JSON object representing this option.
        if ($groupingid) {
            // Require specific grouping.
            $condition = (object)array('type' => 'grouping', 'id' => (int)$groupingid);
        } else {
            // No grouping specified, so require membership of any group.
            $condition = (object)array('type' => 'group');
        }

        if (is_null($availability)) {
            // If there are no conditions using the new API then just set it.
            $tree = (object)array('op' => '&', 'c' => array($condition), 'showc' => array(false));
        } else {
            // There are existing conditions.
            $tree = json_decode($availability);
            switch ($tree->op) {
                case '&' :
                    // For & conditions we can just add this one.
                    $tree->c[] = $condition;
                    $tree->showc[] = false;
                    break;
                case '!|' :
                    // For 'not or' conditions we can add this one
                    // but negated.
                    $tree->c[] = (object)array('op' => '!&', 'c' => array($condition));
                    $tree->showc[] = false;
                    break;
                default:
                    // For the other two (OR and NOT AND) we have to add
                    // an extra level to the tree.
                    $tree = (object)array('op' => '&', 'c' => array($tree, $condition),
                            'showc' => array($tree->show, false));
                    // Inner trees do not have a show option, so remove it.
                    unset($tree->c[0]->show);
                    break;
            }
        }

        return json_encode($tree);
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

    /**
     * Upgrade the minmaxgrade setting.
     *
     * This step should only be run for sites running 2.8 or later. Sites using 2.7 will be fine
     * using the new default system setting $CFG->grade_minmaxtouse.
     *
     * @return void
     */
    public static function upgrade_minmaxgrade() {
        global $CFG, $DB;

        // 2 is a copy of GRADE_MIN_MAX_FROM_GRADE_GRADE.
        $settingvalue = 2;

        // Set the course setting when:
        // - The system setting does not exist yet.
        // - The system seeting is not set to what we'd set the course setting.
        $setcoursesetting = !isset($CFG->grade_minmaxtouse) || $CFG->grade_minmaxtouse != $settingvalue;

        // Identify the courses that have inconsistencies grade_item vs grade_grade.
        $sql = "SELECT DISTINCT(gi.courseid)
                FROM {grade_grades} gg
                JOIN {grade_items} gi
                    ON gg.itemid = gi.id
                WHERE gi.itemtype NOT IN (?, ?)
                AND (gg.rawgrademax != gi.grademax OR gg.rawgrademin != gi.grademin)";

        $rs = $DB->get_recordset_sql($sql, array('course', 'category'));
        foreach ($rs as $record) {
            // Flag the course to show a notice in the gradebook.
            set_config('show_min_max_grades_changed_' . $record->courseid, 1);

            // Set the appropriate course setting so that grades displayed are not changed.
            $configname = 'minmaxtouse';
            if ($setcoursesetting &&
                    !$DB->record_exists('grade_settings', array('courseid' => $record->courseid, 'name' => $configname))) {
                // Do not set the setting when the course already defines it.
                $data = new stdClass();
                $data->courseid = $record->courseid;
                $data->name     = $configname;
                $data->value    = $settingvalue;
                $DB->insert_record('grade_settings', $data);
            }

            // Mark the grades to be regraded.
            $DB->set_field('grade_items', 'needsupdate', 1, array('courseid' => $record->courseid));
        }
        $rs->close();
    }

    /**
     * Marks all courses with changes in extra credit weight calculation
     *
     * Used during upgrade and in course restore process
     *
     * This upgrade script is needed because we changed the algorithm for calculating the automatic weights of extra
     * credit items and want to prevent changes in the existing student grades.
     *
     * @param int $onlycourseid
     */
    public static function upgrade_extra_credit_weightoverride($onlycourseid = 0) {
        global $DB;

        // Find all courses that have categories in Natural aggregation method where there is at least one extra credit
        // item and at least one item with overridden weight.
        $courses = $DB->get_fieldset_sql(
            "SELECT DISTINCT gc.courseid
            FROM {grade_categories} gc
            INNER JOIN {grade_items} gi ON gc.id = gi.categoryid AND gi.weightoverride = :weightoverriden
            INNER JOIN {grade_items} gie ON gc.id = gie.categoryid AND gie.aggregationcoef = :extracredit
            WHERE gc.aggregation = :naturalaggmethod" . ($onlycourseid ? " AND gc.courseid = :onlycourseid" : ''),
            array('naturalaggmethod' => 13,
                'weightoverriden' => 1,
                'extracredit' => 1,
                'onlycourseid' => $onlycourseid,
            )
        );
        foreach ($courses as $courseid) {
            $gradebookfreeze = get_config('core', 'gradebook_calculations_freeze_' . $courseid);
            if (!$gradebookfreeze) {
                set_config('gradebook_calculations_freeze_' . $courseid, 20150619);
            }
        }
    }

    /**
     * Marks all courses that require calculated grade items be updated.
     *
     * Used during upgrade and in course restore process.
     *
     * This upgrade script is needed because the calculated grade items were stuck with a maximum of 100 and could be changed.
     * This flags the courses that are affected and the grade book is frozen to retain grade integrity.
     *
     * @param int $courseid Specify a course ID to run this script on just one course.
     */
    public static function upgrade_calculated_grade_items($courseid = null) {
        global $DB, $CFG;

        $affectedcourses = array();
        $possiblecourseids = array();
        $params = array();
        $singlecoursesql = '';
        if (isset($courseid)) {
            $singlecoursesql = "AND ns.id = :courseid";
            $params['courseid'] = $courseid;
        }
        $siteminmaxtouse = 1;
        if (isset($CFG->grade_minmaxtouse)) {
            $siteminmaxtouse = $CFG->grade_minmaxtouse;
        }
        $courseidsql = "SELECT ns.id
                        FROM (
                            SELECT c.id, coalesce(" . $DB->sql_compare_text('gs.value') . ", :siteminmax) AS gradevalue
                            FROM {course} c
                            LEFT JOIN {grade_settings} gs
                                ON c.id = gs.courseid
                            AND ((gs.name = 'minmaxtouse' AND " . $DB->sql_compare_text('gs.value') . " = '2'))
                            ) ns
                        WHERE " . $DB->sql_compare_text('ns.gradevalue') . " = '2' $singlecoursesql";
        $params['siteminmax'] = $siteminmaxtouse;
        $courses = $DB->get_records_sql($courseidsql, $params);
        foreach ($courses as $course) {
            $possiblecourseids[$course->id] = $course->id;
        }

        if (!empty($possiblecourseids)) {
            list($sql, $params) = $DB->get_in_or_equal($possiblecourseids);
            // A calculated grade item grade min != 0 and grade max != 100 and the course setting is set to
            // "Initial min and max grades".
            $coursesql = "SELECT DISTINCT courseid
                            FROM {grade_items}
                        WHERE calculation IS NOT NULL
                            AND itemtype = 'manual'
                            AND (grademax <> 100 OR grademin <> 0)
                            AND courseid $sql";
            $affectedcourses = $DB->get_records_sql($coursesql, $params);
        }

        // Check for second type of affected courses.
        // If we already have the courseid parameter set in the affectedcourses then there is no need to run through this section.
        if (!isset($courseid) || !in_array($courseid, $affectedcourses)) {
            $singlecoursesql = '';
            $params = array();
            if (isset($courseid)) {
                $singlecoursesql = "AND courseid = :courseid";
                $params['courseid'] = $courseid;
            }
            $nestedsql = "SELECT id
                            FROM {grade_items}
                        WHERE itemtype = 'category'
                            AND calculation IS NOT NULL $singlecoursesql";
            $calculatedgradecategories = $DB->get_records_sql($nestedsql, $params);
            $categoryids = array();
            foreach ($calculatedgradecategories as $key => $gradecategory) {
                $categoryids[$key] = $gradecategory->id;
            }

            if (!empty($categoryids)) {
                list($sql, $params) = $DB->get_in_or_equal($categoryids);
                // A category with a calculation where the raw grade min and the raw grade max don't match the grade min and grade max
                // for the category.
                $coursesql = "SELECT DISTINCT gi.courseid
                                FROM {grade_grades} gg, {grade_items} gi
                            WHERE gi.id = gg.itemid
                                AND (gg.rawgrademax <> gi.grademax OR gg.rawgrademin <> gi.grademin)
                                AND gi.id $sql";
                $additionalcourses = $DB->get_records_sql($coursesql, $params);
                foreach ($additionalcourses as $key => $additionalcourse) {
                    if (!array_key_exists($key, $affectedcourses)) {
                        $affectedcourses[$key] = $additionalcourse;
                    }
                }
            }
        }

        foreach ($affectedcourses as $affectedcourseid) {
            if (isset($CFG->upgrade_calculatedgradeitemsonlyregrade) && !($courseid)) {
                $DB->set_field('grade_items', 'needsupdate', 1, array('courseid' => $affectedcourseid->courseid));
            } else {
                // Check to see if the gradebook freeze is already in affect.
                $gradebookfreeze = get_config('core', 'gradebook_calculations_freeze_' . $affectedcourseid->courseid);
                if (!$gradebookfreeze) {
                    set_config('gradebook_calculations_freeze_' . $affectedcourseid->courseid, 20150627);
                }
            }
        }
    }

    /**
     * This upgrade script merges all tag instances pointing to the same course tag
     *
     * User id is no longer used for those tag instances
     */
    public static function upgrade_course_tags() {
        global $DB;
        $sql = "SELECT min(ti.id)
            FROM {tag_instance} ti
            LEFT JOIN {tag_instance} tii on tii.itemtype = ? and tii.itemid = ti.itemid and tii.tiuserid = 0 and tii.tagid = ti.tagid
            where ti.itemtype = ? and ti.tiuserid <> 0 AND tii.id is null
            group by ti.tagid, ti.itemid";
        $ids = $DB->get_fieldset_sql($sql, array('course', 'course'));
        if ($ids) {
            list($idsql, $idparams) = $DB->get_in_or_equal($ids);
            $DB->execute('UPDATE {tag_instance} SET tiuserid = 0 WHERE id ' . $idsql, $idparams);
        }
        $DB->execute("DELETE FROM {tag_instance} WHERE itemtype = ? AND tiuserid <> 0", array('course'));
    }

    /**
     * Given a float value situated between a source minimum and a source maximum, converts it to the
     * corresponding value situated between a target minimum and a target maximum. Thanks to Darlene
     * for the formula :-)
     *
     * @param float $rawgrade
     * @param float $sourcemin
     * @param float $sourcemax
     * @param float $targetmin
     * @param float $targetmax
     * @return float Converted value
     */
    public static function upgrade_standardise_score($rawgrade, $sourcemin, $sourcemax, $targetmin, $targetmax) {
        if (is_null($rawgrade)) {
            return null;
        }

        if ($sourcemax == $sourcemin or $targetmin == $targetmax) {
            // Prevent division by 0.
            return $targetmax;
        }

        $factor = ($rawgrade - $sourcemin) / ($sourcemax - $sourcemin);
        $diff = $targetmax - $targetmin;
        $standardisedvalue = $factor * $diff + $targetmin;
        return $standardisedvalue;
    }

    /**
     * Checks the letter boundary of the provided context to see if it needs freezing.
     * Each letter boundary is tested to see if receiving that boundary number will
     * result in achieving the cosponsoring letter.
     *
     * @param object $context Context object
     * @return bool if the letter boundary for this context should be frozen.
     */
    public static function upgrade_letter_boundary_needs_freeze($context) {
        global $DB;

        $contexts = $context->get_parent_context_ids();
        array_unshift($contexts, $context->id);

        foreach ($contexts as $ctxid) {

            $letters = $DB->get_records_menu('grade_letters', array('contextid' => $ctxid), 'lowerboundary DESC',
                    'lowerboundary, letter');

            if (!empty($letters)) {
                foreach ($letters as $boundary => $notused) {
                    $standardisedboundary = self::upgrade_standardise_score($boundary, 0, 100, 0, 100);
                    if ($standardisedboundary < $boundary) {
                        return true;
                    }
                }
                // We found letters but we have no boundary problem.
                return false;
            }
        }
        return false;
    }

    /**
     * Compatibility method for the version when context table did not have a 'locked' field
     *
     * @param string $tablealias
     * @return string
     */
    public static function context_helper_get_preload_record_columns_sql($tablealias) {
        return "$tablealias.id AS ctxid, " .
            "$tablealias.path AS ctxpath, " .
            "$tablealias.depth AS ctxdepth, " .
            "$tablealias.contextlevel AS ctxlevel, " .
            "$tablealias.instanceid AS ctxinstance, " .
            "0 AS ctxlocked";
    }

    /**
     * Marks all courses that require rounded grade items be updated.
     *
     * Used during upgrade and in course restore process.
     *
     * This upgrade script is needed because it has been decided that if a grade is rounded up, and it will changed a letter
     * grade or satisfy a course completion grade criteria, then it should be set as so, and the letter will be awarded and or
     * the course completion grade will be awarded.
     *
     * @param int $courseid Specify a course ID to run this script on just one course.
     */
    public static function upgrade_course_letter_boundary($courseid = null) {
        global $DB, $CFG;

        $coursesql = '';
        $params = array('contextlevel' => CONTEXT_COURSE);
        if (!empty($courseid)) {
            $coursesql = 'AND c.id = :courseid';
            $params['courseid'] = $courseid;
        }

        // Check to see if the system letter boundaries are borked.
        $systemcontext = context_system::instance();
        $systemneedsfreeze = self::upgrade_letter_boundary_needs_freeze($systemcontext);

        // Check the setting for showing the letter grade in a column (default is false).
        $usergradelettercolumnsetting = 0;
        if (isset($CFG->grade_report_user_showlettergrade)) {
            $usergradelettercolumnsetting = (int)$CFG->grade_report_user_showlettergrade;
        }
        $lettercolumnsql = '';
        if ($usergradelettercolumnsetting) {
            // the system default is to show a column with letters (and the course uses the defaults).
            $lettercolumnsql = '(gss.value is NULL OR ' . $DB->sql_compare_text('gss.value') .  ' <> \'0\')';
        } else {
            // the course displays a column with letters.
            $lettercolumnsql = $DB->sql_compare_text('gss.value') .  ' = \'1\'';
        }

        // 3, 13, 23, 31, and 32 are the grade display types that incorporate showing letters. See lib/grade/constants/php.
        $systemusesletters = (int) (isset($CFG->grade_displaytype) && in_array($CFG->grade_displaytype, array(3, 13, 23, 31, 32)));
        $systemletters = $systemusesletters || $usergradelettercolumnsetting;

        $contextselect = self::context_helper_get_preload_record_columns_sql('ctx');

        if ($systemletters && $systemneedsfreeze) {
            // Select courses with no grade setting for display and a grade item that is using the default display,
            // but have not altered the course letter boundary configuration. These courses are definitely affected.

            $sql = "SELECT DISTINCT c.id AS courseid
                    FROM {course} c
                    JOIN {grade_items} gi ON c.id = gi.courseid
                    JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel
                LEFT JOIN {grade_settings} gs ON gs.courseid = c.id AND gs.name = 'displaytype'
                LEFT JOIN {grade_settings} gss ON gss.courseid = c.id AND gss.name = 'report_user_showlettergrade'
                LEFT JOIN {grade_letters} gl ON gl.contextid = ctx.id
                    WHERE gi.display = 0
                    AND ((gs.value is NULL)
                        AND ($lettercolumnsql))
                    AND gl.id is NULL $coursesql";
            $affectedcourseids = $DB->get_recordset_sql($sql, $params);
            foreach ($affectedcourseids as $courseid) {
                set_config('gradebook_calculations_freeze_' . $courseid->courseid, 20160518);
            }
            $affectedcourseids->close();
        }

        // If the system letter boundary is okay proceed to check grade item and course grade display settings.
        $sql = "SELECT DISTINCT c.id AS courseid, $contextselect
                FROM {course} c
                JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel
                JOIN {grade_items} gi ON c.id = gi.courseid
            LEFT JOIN {grade_settings} gs ON c.id = gs.courseid AND gs.name = 'displaytype'
            LEFT JOIN {grade_settings} gss ON gss.courseid = c.id AND gss.name = 'report_user_showlettergrade'
                WHERE
                    (
                        -- A grade item is using letters
                        (gi.display IN (3, 13, 23, 31, 32))
                        -- OR the course is using letters
                        OR (" . $DB->sql_compare_text('gs.value') . " IN ('3', '13', '23', '31', '32')
                            -- OR the course using the system default which is letters
                            OR (gs.value IS NULL AND $systemusesletters = 1)
                        )
                        OR ($lettercolumnsql)
                    )
                    -- AND the course matches
                    $coursesql";

        $potentialcourses = $DB->get_recordset_sql($sql, $params);

        foreach ($potentialcourses as $value) {
            $gradebookfreeze = 'gradebook_calculations_freeze_' . $value->courseid;

            // Check also if this course id has already been frozen.
            // If we already have this course ID then move on to the next record.
            if (!property_exists($CFG, $gradebookfreeze)) {
                // Check for 57 letter grade issue.
                \context_helper::preload_from_record($value);
                $coursecontext = context_course::instance($value->courseid);
                if (self::upgrade_letter_boundary_needs_freeze($coursecontext)) {
                    // We have a course with a possible score standardisation problem. Flag for freeze.
                    // Flag this course as being frozen.
                    set_config('gradebook_calculations_freeze_' . $value->courseid, 20160518);
                }
            }
        }
        $potentialcourses->close();
    }

    /**
     * Create another default scale.
     *
     * @param int $oldversion
     * @return bool always true
     */
    public static function make_competence_scale() {
        global $DB;

        $defaultscale = new stdClass();
        $defaultscale->courseid = 0;
        $defaultscale->userid = 0;
        $defaultscale->name  = get_string('defaultcompetencescale');
        $defaultscale->description = get_string('defaultcompetencescaledesc');
        $defaultscale->scale = get_string('defaultcompetencescalenotproficient').','.
                            get_string('defaultcompetencescaleproficient');
        $defaultscale->timemodified = time();

        $defaultscale->id = $DB->insert_record('scale', $defaultscale);
        return $defaultscale;
    }

}
