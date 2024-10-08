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

/**
 * Helper class for UI elements
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ui {

    /**
     * Base URL
     *
     * @param array $params
     * @return \moodle_url
     */
    public static function baseurl(array $params = []): \moodle_url {
        if (!empty($params['section']) && $params['section'] === 'main') {
            unset($params['section']);
        }
        return new \moodle_url('/admin/tool/vault/index.php', $params);
    }

    /**
     * Link to progress page of backup/restore
     *
     * @param array $params
     * @return \moodle_url
     */
    public static function progressurl(array $params = []): \moodle_url {
        return new \moodle_url('/admin/tool/vault/progress.php', ['accesskey' => $params['accesskey']]);
    }

    /**
     * Format time
     *
     * @param int $time
     * @return string
     */
    public static function format_time(int $time): string {
        return $time ? userdate($time, get_string('strftimedatetimeshort', 'langconfig')) : '';
    }

    /**
     * Format time for CLI
     *
     * @param int $time
     * @return string
     */
    public static function format_time_cli(int $time): string {
        return $time ? userdate($time, '%Y-%m-%d %H:%M', 99, false, false) : '';
    }

    /**
     * Duration (used for max_execution_time)
     *
     * @param int $value
     * @return string
     */
    public static function format_duration(int $value): string {
        if ($value <= MINSECS) {
            return get_string('numseconds', 'moodle', $value);
        }
        $parts = [];
        $hrs = floor($value / HOURSECS);
        $mins = floor(($value % HOURSECS) / MINSECS);
        $secs = $value % MINSECS;
        if ($hrs) {
            $parts[] = get_string('numhours', 'moodle', $hrs);
        }
        if ($mins) {
            $parts[] = get_string('numminutes', 'moodle', $mins);
        }
        if ($secs) {
            $parts[] = get_string('numseconds', 'moodle', $secs);
        }
        return
            get_string('numseconds', 'moodle', $value) . ' (' .
            implode(' ', $parts) . ')';
    }

    /**
     * Format status
     *
     * @param string $status
     * @return string
     */
    public static function format_status(string $status): string {
        if ($status === constants::STATUS_FAILED) {
            return get_string('status_failed', 'tool_vault');
        } else if ($status === constants::STATUS_FAILEDTOSTART) {
            return get_string('status_failedtostart', 'tool_vault');
        } else if ($status === constants::STATUS_INPROGRESS) {
            return get_string('status_inprogress', 'tool_vault');
        } else if ($status === constants::STATUS_FINISHED) {
            return get_string('status_finished', 'tool_vault');
        } else if ($status === constants::STATUS_SCHEDULED) {
            return get_string('status_scheduled', 'tool_vault');
        } else {
            // There should not be any other statuses but just in case.
            return ucfirst($status);
        }
    }

    /**
     * Format is encrypted info
     *
     * @param int $value
     * @return string
     */
    public static function format_encrypted(int $value): string {
        return $value ? get_string('yes') : get_string('no');
    }

    /**
     * Format description
     *
     * @param string|null $value
     * @return string
     */
    public static function format_description(?string $value): string {
        return s($value ?? '');
    }
}
