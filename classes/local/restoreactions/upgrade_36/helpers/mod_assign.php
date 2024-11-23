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

namespace tool_vault\local\restoreactions\upgrade_36\helpers;

/**
 * Class mod_assign
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_assign {

    /**
     * Determines if the assignment as any null grades that were rescaled.
     *
     * Null grades are stored as -1 but should never be rescaled.
     *
     * @return int[] Array of the ids of all the assignments with rescaled null grades.
     */
    public static function get_assignments_with_rescaled_null_grades() {
        global $DB;

        $query = 'SELECT id, assignment FROM {assign_grades}
                WHERE grade < 0 AND grade <> -1';

        $assignments = array_values($DB->get_records_sql($query));

        $getassignmentid = function ($assignment) {
            return $assignment->assignment;
        };

        $assignments = array_map($getassignmentid, $assignments);

        return $assignments;
    }
}
