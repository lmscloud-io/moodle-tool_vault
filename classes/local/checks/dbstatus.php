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

use tool_vault\api;
use tool_vault\constants;
use tool_vault\local\helpers\siteinfo;
use tool_vault\local\xmldb\dbstructure;
use tool_vault\local\xmldb\dbtable;

/**
 * Check database status
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dbstatus extends check_base {

    /** @var int */
    const STATUS_CLEAN = 1;
    /** @var int */
    const STATUS_NOMODIFICATIONS = 2;
    /** @var int */
    const STATUS_MODIFIED = 3;
    /** @var int */
    const STATUS_INVALID = 4;

    /**
     * Evaluate check and store results in model details
     */
    public function perform(): void {
        $s = dbstructure::load();
        $result = [
            constants::DIFF_EXTRATABLES => [],
            constants::DIFF_MISSINGTABLES => [],
            constants::DIFF_CHANGEDTABLES => [],
            constants::DIFF_INVALIDTABLES => [],
        ];

        $actualtables = array_filter($s->get_tables_actual(), function($tableobj, $tablename) use ($s) {
            return !siteinfo::is_table_excluded_from_backup($tablename, $s->find_table_definition($tablename));
        }, ARRAY_FILTER_USE_BOTH);
        $deftables = array_filter($s->get_tables_definitions(), function($deftable, $tablename) {
            return !siteinfo::is_table_excluded_from_backup($tablename, $deftable);
        }, ARRAY_FILTER_USE_BOTH);

        foreach (array_diff_key($actualtables, $deftables) as $tablename => $table) {
            $result[constants::DIFF_EXTRATABLES][$tablename] = [null, $table, null];
        }
        foreach (array_diff_key($deftables, $actualtables) as $tablename => $table) {
            $result[constants::DIFF_MISSINGTABLES][$tablename] = [$table, null, null];
        }
        foreach (array_intersect_key($actualtables, $deftables) as $tablename => $table) {
            $deftable = $deftables[$tablename];
            $diff = $table->compare_with_other_table($deftable);
            if ($diff) {
                $result[constants::DIFF_CHANGEDTABLES][$tablename] = [$deftable, $table, $diff];
            }
        }
        foreach ($actualtables as $tablename => $table) {
            $errors = $table->validate_definition();
            if ($errors) {
                $deftable = $deftables[$tablename] ?? null;
                $result[constants::DIFF_INVALIDTABLES][$tablename] = [$deftable, $table, $errors];
            }
        }
        $this->model->set_details($this->prepare_to_store($result))->save();
    }

    /**
     * String explaining the status
     *
     * @return string
     */
    public function get_status_message(): string {
        if ($report = $this->get_report()) {
            $status = $this->get_status($report);
            switch ($status) {
                case self::STATUS_CLEAN:
                    return "Your database tables match descriptions in install.xml";
                case self::STATUS_NOMODIFICATIONS:
                    return "Site backup can be performed without any database modifications";
                case self::STATUS_MODIFIED:
                    return "Your database state does not match specifications in install.xml. The site can be backed up, " .
                        "the modifications will be included in the backup";
                case self::STATUS_INVALID:
                    return "Your database has modifications that can not be processed by Moodle. You need to adjust the " .
                        "'Backup settings' and exclude some entities if you want to perform site backup";
            }
        }
        return "Unknown";
    }

    /**
     * Numeric status
     *
     * @param array $result
     * @return int
     */
    protected function get_status(array $result): int {
        $result = $this->get_report();
        if (!empty($result[constants::DIFF_INVALIDTABLES])) {
            $status = self::STATUS_INVALID;
        } else if (array_filter($result)) {
            $status = self::STATUS_MODIFIED;
        } else if (!empty(api::get_setting_array('backupexcludetables'))) {
            $status = self::STATUS_NOMODIFICATIONS;
        } else {
            $status = self::STATUS_CLEAN;
        }
        return $status;
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
        } else if (is_string($obj)) {
            return $obj;
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
     * Can backup be performed
     *
     * @return bool
     */
    public function success(): bool {
        return ($report = $this->get_report()) && $this->get_status($report) !== self::STATUS_INVALID;
    }

    /**
     * Summary
     *
     * @return string
     */
    public function summary(): string {
        $report = $this->get_report();
        if ($report) {
            return
                $this->display_status_message($this->get_status_message(), !empty(array_filter($report))).
                '<ul>'.
                '<li>Missing tables: '.count($report[constants::DIFF_MISSINGTABLES]).'</li>'.
                '<li>Extra tables: '.count($report[constants::DIFF_EXTRATABLES]).'</li>'.
                '<li>Changed tables: '.count($report[constants::DIFF_CHANGEDTABLES]).'</li>'.
                '<li>Invalid tables: '.count($report[constants::DIFF_INVALIDTABLES]).'</li>'.
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
     * Data for the template
     *
     * @return array
     */
    protected function get_template_data(): array {
        global $OUTPUT;
        $report = $this->get_report();
        $tables = [];
        if (!$report) {
            return $tables;
        }
        foreach ($report as $r) {
            foreach ($r as $tablename => $details) {
                $tables[$tablename] = [
                    'tablename' => $tablename,
                    'tablewarnings' => [],
                    'diffdefinition' => s($details[0]),
                    'diffactual' => s($details[1]),
                ];
            }
        }
        foreach ($report as $errortype => $r) {
            foreach ($r as $tablename => $details) {
                if ($errortype === constants::DIFF_MISSINGTABLES) {
                    // TODO strings.
                    $tables[$tablename]['tablewarnings'][] =
                        'Missing table: Table is present in the definition but is absent in the actual database';
                } else if ($errortype === constants::DIFF_EXTRATABLES) {
                    $tables[$tablename]['tablewarnings'][] =
                        'Extra table: Table is absent in the definition but is present in the actual database';
                } else if ($errortype === constants::DIFF_CHANGEDTABLES) {
                    foreach ($details[2] as $changetype => $list) {
                        foreach ($list as $l) {
                            // TODO more human readable?
                            $tables[$tablename]['tablewarnings'][] = $changetype . ': '.s($l);
                        }
                    }
                } else if ($errortype === constants::DIFF_INVALIDTABLES) {
                    foreach ($details[2] as $l) {
                        $tables[$tablename]['tablewarnings'][] = $OUTPUT->pix_icon('req', '') . $l;
                    }
                }
            }
        }
        return ['tables' => array_values($tables)];
    }

    /**
     * Detailed report
     *
     * @return string
     */
    public function detailed_report(): string {
        global $OUTPUT;
        $report = $this->get_report();
        if ($report !== null) {
            return
                $this->display_status_message($this->get_status_message(), !empty(array_filter($report))).
                    $OUTPUT->render_from_template('tool_vault/check_dbstatus_details', $this->get_template_data());
        }
        return '';
    }

    /**
     * Display name
     *
     * @return string
     */
    public static function get_display_name(): string {
        return 'Database modifications'; // TODO string.
    }
}
