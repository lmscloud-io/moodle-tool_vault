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

use renderer_base;
use tool_vault\constants;
use tool_vault\local\models\check;
use tool_vault\task\check_task;

/**
 * Base class for all health checks
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base implements \templatable {
    /** @var check */
    protected $model;

    /**
     * Constructor
     *
     * @param check $model
     */
    protected function __construct(check $model) {
        $this->model = $model;
    }

    /**
     * List of all health checks
     *
     * @return self[]
     */
    public static function get_all_checks(): array {
        return [
            dbstatus::get_last_check(),
        ];
    }

    /**
     * Load check by id
     *
     * @param int $id
     * @return static|null
     */
    public static function load(int $id): ?self {
        global $DB;
        $record = $DB->get_record('tool_vault_checks', ['id' => $id]);
        if (!$record) {
            return null;
        }
        $model = new check($record);
        try {
            $instance = static::instance($model);
            return $instance;
        } catch (\Throwable $t) {
            if (in_array($model->status, [constants::STATUS_SCHEDULED, constants::STATUS_INPROGRESS])) {
                $model->set_error($t)->set_status(constants::STATUS_FAILEDTOSTART)->save();
            }
            return null;
        }
    }

    /**
     * Creates an instance of this class from a model
     *
     * @param check $model
     * @return static
     * @throws \coding_exception
     */
    protected static function instance(check $model): self {
        $checkname = $model->type;
        if (clean_param($checkname, PARAM_ALPHANUMEXT) !== $checkname || !strlen($checkname)) {
            throw new \coding_exception('Check name is not valid');
        }
        $classname = 'tool_vault\\local\\checks\\' . $checkname;
        if (class_exists($classname) && is_subclass_of($classname, self::class)) {
            $class = new \ReflectionClass($classname);
            if (!$class->isAbstract()) {
                return new $classname($model);
            }
        }
        throw new \coding_exception('Check with the name '.$checkname.' does not exist -- '.$classname);
    }

    /**
     * Get last part of the class name
     *
     * @return string
     */
    public static function get_name() {
        $tableclass = explode("\\", static::class);
        return end($tableclass);
    }

    /**
     * Get the model
     *
     * @return check
     */
    public function get_model(): check {
        return $this->model;
    }

    /**
     * Get last check of this type or creates and schedules new
     *
     * @return static
     */
    public static function get_last_check(): self {
        global $DB;
        $records = $DB->get_records('tool_vault_checks', ['type' => static::get_name()], 'timecreated DESC');
        if (!$records) {
            return self::schedule_new(static::get_name());
        } else {
            $model = new check(reset($records));
            return new static($model);
        }
    }

    /**
     * Check is in progress
     *
     * @return bool
     */
    public function is_in_progress(): bool {
        return ($this->model->status === constants::STATUS_SCHEDULED || $this->model->status === constants::STATUS_INPROGRESS);
    }

    /**
     * Schedule new check of the given type
     *
     * @param string $type
     * @return static
     */
    public static function schedule_new(string $type): self {
        $model = new check((object)['type' => $type, 'status' => constants::STATUS_SCHEDULED]);
        $obj = self::instance($model);
        $model->save();
        check_task::schedule($model->id);
        return $obj;
    }

    /**
     * Mark check as failed
     *
     * @param \Throwable $t
     */
    public function mark_as_failed(\Throwable $t) {
        $this->model->set_status(constants::STATUS_FAILED)->set_error($t)->save();
    }

    /**
     * Mark check as in progress
     */
    public function mark_as_inprogress() {
        $this->model->set_status(constants::STATUS_INPROGRESS)->save();
    }

    /**
     * Mark check as finished
     *
     * @return void
     */
    public function mark_as_finished() {
        $this->model->set_status(constants::STATUS_FINISHED)->save();
    }

    /**
     * Evaluate check and store results in model details
     */
    abstract public function perform(): void;

    /**
     * Get summary of the past check
     *
     * @return string
     */
    abstract public function summary(): string;

    /**
     * Does this past check have details (to display a link "Show details")
     *
     * @return bool
     */
    abstract public function has_details(): bool;

    /**
     * Get detailed report of the past check
     *
     * @return string
     */
    abstract public function detailed_report(): string;

    /**
     * Display name of this check
     *
     * @return string
     */
    abstract public function get_display_name(): string;

    /**
     * Export for template
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $overviewurl = new \moodle_url('/admin/tool/vault/index.php');
        $rescheduleurl = new \moodle_url($overviewurl,
            ['action' => 'newcheck', 'type' => static::get_name(), 'sesskey' => sesskey()]);
        $fullreporturl = new \moodle_url($overviewurl,
            ['action' => 'details', 'id' => $this->get_model()->id]);
        return [
            'title' => $this->get_display_name(),
            'overviewurl' => $overviewurl->out(false),
            'subtitle' => userdate($this->get_model()->timecreated) .' : '.
                $this->get_model()->status,
            'inprogress' => $this->is_in_progress(),
            'reschedulelink' => $rescheduleurl->out(false),
            'summary' => $this->summary(),
            'showdetailslink' => $this->has_details(),
            'fullreporturl' => $this->has_details() ? $fullreporturl->out(false) : null,
        ];
    }
}
