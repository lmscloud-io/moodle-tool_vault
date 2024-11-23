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

namespace tool_vault\local\tools;

use tool_vault\constants;
use tool_vault\local\models\tool_model;
use tool_vault\local\operations\operation_base;

/**
 * Class tool_base
 *
 * @package    tool_vault
 * @copyright  2024 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class tool_base extends operation_base {
    /** @var tool_model */
    protected $model;

    /**
     * Constructor
     *
     * @param tool_model $model
     */
    protected function __construct(tool_model $model) {
        $this->model = $model;
    }

    /**
     * Display name of this tool
     *
     * @return string
     */
    abstract public static function get_display_name();

    /**
     * Tool description (for the listing page)
     *
     * @return string
     */
    public static function get_description() {
        return '';
    }

    /**
     * Label for the action button
     *
     * @return string
     */
    public static function get_action_button_label() {
        return get_string('toschedule', 'tool_vault');
    }

    /**
     * Schedules new tool execution
     *
     * @param array $params
     * @return operation_base
     */
    public static function schedule(array $params = []) {
        $type = $params['type'];
        $model = new tool_model((object)['status' => constants::STATUS_SCHEDULED], $type);
        $obj = self::instance($model);
        $model->save();
        return $obj;
    }

    /**
     * Creates an instance of this class from a model
     *
     * @param tool_model $model
     * @return static
     * @throws \coding_exception
     */
    protected static function instance(tool_model $model) {
        $toolname = $model->get_tool_name();
        if (clean_param($toolname, PARAM_ALPHANUMEXT) !== $toolname || !strlen($toolname)) {
            throw new \coding_exception('Tool name is not valid');
        }
        $classname = 'tool_vault\\local\\tools\\' . $toolname;
        if (class_exists($classname) && is_subclass_of($classname, self::class)) {
            $class = new \ReflectionClass($classname);
            if (!$class->isAbstract()) {
                return new $classname($model);
            }
        }
        throw new \coding_exception('Tool with the name ' . $toolname . ' does not exist -- ' . $classname);
    }

    /**
     * Evaluate check and store results in model details
     */
    abstract public function perform();

    /**
     * Evaluate check and store results in model details
     */
    public function execute() {
        $this->perform();
        $this->model->set_status(constants::STATUS_FINISHED)->save();
    }

    /**
     * Load check by id
     *
     * @param int $id
     * @return static|null
     */
    public static function load($id) {
        $model = tool_model::get_by_id($id);
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
     * Check is in progress
     *
     * @return bool
     */
    public function is_in_progress() {
        return ($this->model->status === constants::STATUS_SCHEDULED || $this->model->status === constants::STATUS_INPROGRESS);
    }
}
