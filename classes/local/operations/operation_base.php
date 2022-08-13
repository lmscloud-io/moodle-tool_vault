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

namespace tool_vault\local\operations;

use tool_vault\constants;
use tool_vault\local\logger;
use tool_vault\local\models\operation_model;

/**
 * Base class for all operations
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class operation_base implements logger {
    /** @var operation_model */
    protected $model;

    /**
     * Schedules new backup
     *
     * @param array $params
     * @return operation_base
     */
    abstract public static function schedule(array $params = []): self;

    /**
     * Returns the operation model (has properties id, status, etc)
     *
     * @return operation_model
     */
    public function get_model(): ?operation_model {
        return $this->model;
    }

    /**
     * Mark operation as failed
     *
     * @param \Throwable $t
     * @return void
     */
    public function mark_as_failed(\Throwable $t) {
        if ($this->model->status === constants::STATUS_INPROGRESS) {
            $this->model->set_status(constants::STATUS_FAILEDTOSTART);
        } else {
            $this->model->set_status(constants::STATUS_FAILED);
        }
        $this->model->set_error($t)->save();
        $this->add_to_log('Operation failed: '.$t->getMessage(), constants::LOGLEVEL_ERROR);
    }

    /**
     * Start operation
     *
     * @param int $pid
     */
    public function start(int $pid) {
        $this->model->set_pid_for_logging($pid);
        $this->model
            ->set_status(constants::STATUS_INPROGRESS)
            ->save();
    }

    /**
     * Execute operation
     *
     * @return void
     */
    abstract public function execute();

    /**
     * Log action
     *
     * @param string $message
     * @param string $loglevel
     * @return void
     */
    public function add_to_log(string $message, string $loglevel = constants::LOGLEVEL_INFO) {
        if ($this->model && $this->model->id) {
            $logrecord = $this->model->add_log($message, $loglevel);
            if (!(defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
                mtrace($this->model->format_log_line($logrecord, false));
            }
        }
    }
}