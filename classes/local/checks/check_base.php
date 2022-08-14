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
use tool_vault\local\models\check_model;
use tool_vault\local\models\operation_model;
use tool_vault\local\operations\operation_base;
use tool_vault\site_backup;
use tool_vault\task\check_task;

/**
 * Base class for all health checks
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class check_base extends operation_base {
    /** @var check_model */
    protected $model;
    /** @var operation_model */
    protected $parent;

    /**
     * Constructor
     *
     * @param check_model $model
     */
    protected function __construct(check_model $model) {
        $this->model = $model;
    }

    /**
     * List of all health checks
     *
     * @return self[]
     */
    public static function get_all_checks(): array {
        $checks = [];
        /** @var check_base[] $precheckclasses */
        $precheckclasses = site_backup::backup_prechecks();
        foreach ($precheckclasses as $classname) {
            $checks[] = $classname::get_last_check();
        }
        return $checks;
    }

    /**
     * Load check by id
     *
     * @param int $id
     * @return static|null
     */
    public static function load(int $id): ?self {
        $model = check_model::get_by_id($id);
        if (!$model) {
            return null;
        }
        try {
            $instance = static::instance($model);
            return $instance;
        } catch (\Throwable $t) {
            return null;
        }
    }

    /**
     * Creates an instance of this class from a model
     *
     * @param check_model $model
     * @return static
     * @throws \coding_exception
     */
    protected static function instance(check_model $model): self {
        $checkname = $model->get_check_name();
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
        throw new \coding_exception('Check with the name ' . $checkname . ' does not exist -- ' . $classname);
    }

    /**
     * Get all checks for given parentid
     *
     * @param int $operationid
     * @return self[]
     */
    public static function get_all_checks_for_operation(int $operationid): array {
        $checks = check_model::get_all_checks_for_operation($operationid);
        $res = [];
        foreach ($checks as $check) {
            $res[] = self::instance($check);
        }
        return $res;
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
     * @return check_model
     */
    public function get_model(): operation_model {
        return $this->model;
    }

    /**
     * Get last check of this type or creates and schedules new
     *
     * @return static
     */
    public static function get_last_check(): self {
        $records = check_model::get_checks_by_type(static::get_name());
        if (!$records) {
            return self::schedule(['type' => static::get_name()]);
        } else {
            $model = reset($records);
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
     * @param array $params
     * @return static
     */
    public static function schedule(array $params = []): operation_base {
        $type = $params['type'];
        $model = new check_model((object)['status' => constants::STATUS_SCHEDULED], $type);
        $obj = self::instance($model);
        $model->save();
        return $obj;
    }

    /**
     * Run individual check from CLI
     *
     * @param operation_model|null $parent
     * @return static
     */
    public static function create_and_run(?operation_model $parent = null): self {
        // TODO check - only to use from CLI.
        // TODO make sure there is nothing else scheduled.
        $model = new check_model((object)['status' => constants::STATUS_INPROGRESS, 'parentid' => $parent ? $parent->id : null],
            self::get_name());
        $obj = static::instance($model);
        $obj->parent = $parent;
        $model->save();

        try {
            $obj->execute();
        } catch (\Throwable $t) {
            $obj->mark_as_failed($t);
        }

        return $obj;
    }

    /**
     * Evaluate check and store results in model details
     */
    abstract public function perform(): void;

    /**
     * Evaluate check and store results in model details
     */
    final public function execute(): void {
        $this->perform();
        $this->model->set_status(constants::STATUS_FINISHED)->save();
    }

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
    abstract public static function get_display_name(): string;

    /**
     * Pre-check is successful (backup/restore can be performed)
     *
     * @return bool
     */
    abstract public function success(): bool;

    /**
     * Display a check status message
     *
     * @param string $status
     * @param bool $iswarning
     * @return string
     */
    protected function display_status_message(string $status, bool $iswarning = false): string {
        global $OUTPUT;
        return $OUTPUT->notification($status,
            !$this->success() ? 'error' :
                ($iswarning ? 'warning' : 'success'),
            false);
    }

    /**
     * URL to reschedule this check (if applicable)
     *
     * @return \moodle_url|null
     */
    public function get_reschedule_url(): ?\moodle_url {
        if ($this->get_model()->parentid && !in_array(get_class($this), site_backup::backup_prechecks())) {
            return null;
        }
        return new \moodle_url('/admin/tool/vault/index.php',
            ['action' => 'newcheck', 'type' => $this->get_name(), 'sesskey' => sesskey()]);
    }

    /**
     * Link to this check full review (if applicable)
     *
     * @return \moodle_url|null
     */
    public function get_fullreport_url(): ?\moodle_url {
        // TODO link for the restore checks.
        return new \moodle_url('/admin/tool/vault/index.php',
            ['action' => 'details', 'id' => $this->get_model()->id]);
    }

    /**
     * Get parent
     *
     * @return operation_model|null
     */
    public function get_parent(): ?operation_model {
        return $this->parent;
    }

    /**
     * String explaining the status
     *
     * @return string
     */
    public function get_status_message(): string {
        return $this->success() ? 'Success' : 'Failed';
    }
}
