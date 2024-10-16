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

namespace tool_vault\local\helpers;

use tool_vault\constants;
use tool_vault\local\models\operation_model;

/**
 * Class log_capture
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class log_capture {

    /** @var int The level of output buffering in place before starting. */
    protected static $oblevel = null;
    /** @var operation_model current operation */
    protected static $model = null;


    /**
     * Start capturing output
     *
     * @param \tool_vault\local\models\operation_model $model
     */
    public static function start_capturing(operation_model $model): void {
        if (self::is_current_output_buffer()) {
            // We cannot capture when we are already capturing.
            throw new \coding_exception('Logging is already in progress. Nested logging is not supported.');
        }

        self::$model = $model;

        // Note the level of the current output buffer.
        // Note: You cannot use ob_get_level() as it will return `1` when the default output buffer is enabled.
        if ($obstatus = ob_get_status()) {
            self::$oblevel = $obstatus['level'];
        } else {
            self::$oblevel = null;
        }

        // Start capturing output.
        ob_start([self::class, 'add_line'], 1);
    }

    /**
     * Whether we are capturing at all.
     *
     * @return  bool
     */
    protected static function is_capturing(): bool {
        $buffers = ob_get_status(true);
        foreach ($buffers as $ob) {
            if (self::class.'::add_line' == $ob['name']) {
                return true;
            }
        }

        return false;
    }

    /**
     * End capturing
     *
     * @return void
     */
    public static function finalise_log() {

        if (!self::is_capturing()) {
            // Not capturing anything.
            return;
        }

        // Ensure that all logs are closed.
        $buffers = ob_get_status(true);
        foreach (array_reverse($buffers) as $ob) {
            if (null !== self::$oblevel) {
                if ($ob['level'] <= self::$oblevel) {
                    // Only close as far as the initial output buffer level.
                    break;
                }
            }

            // End and flush this buffer.
            ob_end_flush();

            if (self::class.'::add_line' == $ob['name']) {
                break;
            }
        }
        self::$oblevel = null;

        // Flush any remaining buffer.
        self::flush();

        // Tidy up.
        self::$model = null;
    }

    /**
     * Capture one line of the output
     *
     * @param string $log
     * @return string
     */
    public static function add_line(string $log): string {
        if (self::is_current_output_buffer()) {
            if (self::$model && !self::$model->is_vault_output($log)) {
                // Add to the log unless it is already vault output (which means it is already in the db).
                self::$model->add_log($log, constants::LOGLEVEL_UNKNOWN);
            }
        }
        return $log;
    }

    /**
     * Flush the current output buffer.
     *
     * This function will ensure that we are the current output buffer handler.
     */
    public static function flush() {
        // We only call ob_flush if the current output buffer belongs to us.
        if (self::is_current_output_buffer()) {
            ob_flush();
        }
    }

    /**
     * Whether we are the current log collector.
     *
     * @return  bool
     */
    protected static function is_current_output_buffer(): bool {
        if ($ob = ob_get_status()) {
            return self::class.'::add_line' == $ob['name'];
        }
        return false;
    }
}
