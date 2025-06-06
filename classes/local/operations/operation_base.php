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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_vault\local\operations;

use tool_vault\api;
use tool_vault\constants;
use tool_vault\local\checks\backup_precheck_failed;
use tool_vault\local\checks\restore_precheck_failed;
use tool_vault\local\helpers\log_capture;
use tool_vault\local\helpers\tempfiles;
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
     * Returns the error message to store on server
     *
     * @param \Throwable $t
     * @return string
     */
    public static function get_error_message_for_server(\Throwable $t): string {
        global $CFG, $DB;
        $message = $t->getMessage();
        if ($t instanceof \moodle_exception) {
            // When debug display is off, the debuginfo is not added to the message, add it here.
            $hasdebugdeveloper = (
                isset($CFG->debugdisplay) &&
                isset($CFG->debug) &&
                $CFG->debugdisplay &&
                $CFG->debug === DEBUG_DEVELOPER
            );
            if (!$hasdebugdeveloper && $t->debuginfo) {
                $message = "$message ($t->debuginfo)";
            }
        }
        $message .= "\n\nPHP ".PHP_VERSION." / Moodle {$CFG->release} / DB ".$DB->get_dbfamily()."\n";
        if ($t instanceof backup_precheck_failed || $t instanceof restore_precheck_failed) {
            $message .= "\n" . $t->extra_info();
        }
        $message .= "\n" . $t->getTraceAsString();
        return $message;
    }

    /**
     * Mark operation as failed
     *
     * @param \Throwable $t
     * @return void
     */
    public function mark_as_failed(\Throwable $t) {
        if ($this->model->status === constants::STATUS_INPROGRESS) {
            $this->model->set_status(constants::STATUS_FAILED);
        } else {
            $this->model->set_status(constants::STATUS_FAILEDTOSTART);
        }
        $this->model->set_error($t);
        if (!empty($this->model->get_details()['encryptionkey'])) {
            $this->model->set_details(['encryptionkey' => '']);
        }
        try {
            $this->model->save();
        // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        } catch (\Throwable $tnew) {
            // Sometimes mysql locks up so badly, it can't even insert or update logs, it triggers
            // "Mysql has gone away" exception.
        }
        $this->add_to_log_from_exception_handler('Operation failed: '.$t->getMessage(), constants::LOGLEVEL_ERROR);
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
        if ($loglevel == constants::LOGLEVEL_VERBOSE && !api::get_config('debug')) {
            return;
        }
        if ($this->model && $this->model->id) {
            $part = $message;
            $logrecord = $this->model->add_log($part, $loglevel);
            if (!(defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
                mtrace($this->model->format_log_line($logrecord, false));
            }
        }
    }

    /**
     * Log action if possible (never throw exceptions from this function)
     *
     * @param string $message
     * @param string $loglevel
     * @return void
     */
    public function add_to_log_from_exception_handler(string $message, string $loglevel = constants::LOGLEVEL_INFO) {
        try {
            $this->add_to_log($message, $loglevel);
        // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        } catch (\Throwable $t) {
            // Sometimes mysql locks up so badly, it can't even insert or update logs, it triggers
            // "Mysql has gone away" exception.
        }
    }

    /**
     * Starts and executes the operation, marks it as faled on exception or on shutdown
     *
     * @param int $pid process id for logging
     * @return bool
     */
    public function safe_start_and_execute(int $pid): bool {
        \core_shutdown_manager::register_function([$this, 'on_shutdown']);
        $rv = false;
        log_capture::start_capturing($this->model);
        try {
            $this->start($pid);
            $this->execute();
            $rv = true;
        } catch (\Throwable $t) {
            $this->mark_as_failed($t);
            tempfiles::cleanup();
        }
        log_capture::finalise_log();
        return $rv;
    }

    /**
     * Shutdown handler
     *
     * @return void
     */
    public function on_shutdown() {
        $status = $this->model->status;
        if ($status === constants::STATUS_INPROGRESS) {
            $source = defined('TOOL_VAULT_CLI_SCRIPT') && TOOL_VAULT_CLI_SCRIPT ?
                get_string('cliprocess', 'tool_vault') :
                get_string('scheduledtask', 'tool_vault');
            $this->mark_as_failed(new \moodle_exception('error_shutdown', 'tool_vault', '', $source));
            tempfiles::cleanup();
            log_capture::finalise_log();
        }
    }
}
