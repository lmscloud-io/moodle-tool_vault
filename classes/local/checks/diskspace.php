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

namespace tool_vault\local\checks;

use tool_vault\constants;
use tool_vault\local\xmldb\dbstructure;

/**
 * Check disk space
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class diskspace extends base {

    /**
     * Evaluate check and store results in model details
     */
    public function perform(): void {
        global $DB;
        $record = $DB->get_record_sql('SELECT sum(filesize) AS sumfilesize, max(filesize) AS maxfilesize
            FROM (SELECT distinct contenthash, filesize
                from {files}
                WHERE not (component=? AND filearea = ?)) a',
            ['user', 'draft']);
        $totalsize = $record->sumfilesize;
        $maxfilesize = $record->maxfilesize;
        $freespace = disk_free_space(make_temp_directory('test'));
        $dbrecords = 0;
        $structure = dbstructure::load();
        foreach ($structure->get_tables_actual() as $tablename => $table) {
            $cnt = $DB->count_records_select($tablename, '1=1');
            $dbrecords += $cnt;
        }
        $tablesizes = $structure->get_actual_tables_sizes();
        $dbtotalsize = array_sum($tablesizes);
        $dbmaxsize = max($tablesizes);
        $enoughspace = ($totalsize + $dbtotalsize) * 2 < $freespace;
        $this->model->set_details([
            'totalfilesize' => $totalsize,
            'maxfilesize' => $maxfilesize,
            'freespace' => $freespace,
            'dbrecords' => $dbrecords,
            'dbtotalsize' => $dbtotalsize,
            'dbmaxsize' => $dbmaxsize,
            'enoughspace' => $enoughspace,
        ])->save();
    }

    /**
     * Can backup be performed
     *
     * @return bool
     */
    public function success(): bool {
        return $this->model->status === constants::STATUS_FINISHED
            && $this->model->get_details()['enoughspace'];
    }

    /**
     * Get summary of the past check
     *
     * @return string
     */
    public function summary(): string {
        if ($this->model->status !== constants::STATUS_FINISHED) {
            return '';
        }
        $details = $this->model->get_details();
        $status = $details['enoughspace'] ?
            'There is enough disk space to perform site backup' :
            'There is not enough disk space to perform site backup';
        return
            $this->status_message($status).
            '<ul>'.
            '<li>Total size of files: '.display_size($details['totalfilesize']).'</li>'.
            '<li>The largest file: '.display_size($details['maxfilesize']).'</li>'.
            '<li>Total number of rows in DB tables: '.number_format($details['dbrecords'], 0).'</li>'.
            '<li>Total size of DB tables (approx): '.display_size($details['dbtotalsize']).'</li>'.
            '<li>The largest DB table size (approx): '.display_size($details['dbmaxsize']).'</li>'.
            '<li>Free space in temp dir: '.display_size($details['freespace']).'</li>'.
            '</ul>';
    }

    /**
     * Does this past check have details (to display a link "Show details")
     *
     * @return bool
     */
    public function has_details(): bool {
        return false;
    }

    /**
     * Get detailed report of the past check
     *
     * @return string
     */
    public function detailed_report(): string {
        return '';
    }

    /**
     * Display name of this check
     *
     * @return string
     */
    public function get_display_name(): string {
        return "Disk space";
    }
}
