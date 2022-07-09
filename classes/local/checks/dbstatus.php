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
use tool_vault\local\xmldb\dbtable;

/**
 * Check database status
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dbstatus extends base {

    /**
     * Evaluate check and store results in model details
     */
    public function perform(): void {
        $s = dbstructure::load();
        $result = [
            constants::DIFF_EXTRATABLES => [],
            constants::DIFF_MISSINGTABLES => [],
            constants::DIFF_CHANGEDTABLES => [],
        ];
        foreach (array_diff_key($s->get_tables_actual(), $s->get_tables_definitions()) as $tablename => $table) {
            $result[constants::DIFF_EXTRATABLES][$tablename] = [null, $table, null];
        }
        foreach (array_diff_key($s->get_tables_definitions(), $s->get_tables_actual()) as $tablename => $table) {
            $result[constants::DIFF_MISSINGTABLES][$tablename] = [$table, null, null];
        }
        foreach (array_intersect_key($s->get_tables_actual(), $s->get_tables_definitions()) as $tablename => $table) {
            $deftable = $s->get_tables_definitions()[$tablename];
            $diff = $table->compare_with_other_table($deftable);
            if ($diff) {
                $result[constants::DIFF_CHANGEDTABLES][$tablename] = [$deftable, $table, $diff];
            }
        }
        $this->model->set_details($this->prepare_to_store($result))->save();
    }

    /**
     * Prepare array or object to store in json-encoded details
     *
     * @param mixed $obj
     * @return array|string|null
     */
    protected function prepare_to_store($obj) {
        if ($obj === null) {
            return null;
        } else if (is_array($obj)) {
            $res = [];
            foreach ($obj as $key => $val) {
                $res[$key] = $this->prepare_to_store($val);
            }
            return $res;
        } else if ($obj instanceof dbtable) {
            return trim($obj->get_xmldb_table()->xmlOutput());
        } else if ($obj instanceof \xmldb_field || $obj instanceof \xmldb_index || $obj instanceof \xmldb_key) {
            return trim($obj->xmlOutput());
        } else {
            throw new \coding_exception('Unknown type: '.get_class($obj));
        }
    }

    /**
     * Report
     *
     * @return array|null
     */
    protected function get_report(): ?array {
        if ($this->get_model()->status !== constants::STATUS_FINISHED) {
            return null;
        }
        return $this->get_model()->get_details();
    }

    /**
     * Summary
     *
     * @return string
     */
    public function summary(): string {
        $report = $this->get_report();
        if ($report === null) {
            $details = $this->get_model()->get_details();
            if (isset($details['error'])) {
                // TODO show nicer.
                // @codingStandardsIgnoreLine
                return '<p>Error:</p><pre>'.s(print_r($details['error'], true)).'</pre>';
            }
        } else {
            return '<ul>'.
                '<li>Missing tables: '.count($report[constants::DIFF_MISSINGTABLES]).'</li>'.
                '<li>Extra tables: '.count($report[constants::DIFF_EXTRATABLES]).'</li>'.
                '<li>Changed tables: '.count($report[constants::DIFF_CHANGEDTABLES]).'</li>'.
                '</ul>';
        }
        return '';
    }

    /**
     * Has details
     *
     * @return bool
     */
    public function has_details(): bool {
        $report = $this->get_report();
        return ($report !== null && array_filter($report));
    }

    /**
     * Detailed report
     *
     * @return string
     */
    public function detailed_report(): string {
        $report = $this->get_report();
        if ($report !== null) {
            // TODO show nicer.
            // @codingStandardsIgnoreLine
            return '<pre>' . s(print_r($report, true)) . '</pre>';
        }
        return '';
    }

    /**
     * Display name
     *
     * @return string
     */
    public function get_display_name(): string {
        return 'Database modifications'; // TODO string.
    }
}
