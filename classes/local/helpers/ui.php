<?php
// This file is part of Moodle - http://moodle.org/
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
        if (!empty($params['section']) && $params['section'] === 'overview') {
            unset($params['section']);
        }
        return new \moodle_url('/admin/tool/vault/index.php', $params);
    }

    /**
     * Overview section URL
     *
     * @param array $params
     * @return \moodle_url
     */
    public static function overviewurl(array $params = []): \moodle_url {
        return self::baseurl($params + ['section' => 'overview']);
    }

    /**
     * Backup section URL
     *
     * @param array $params
     * @return \moodle_url
     */
    public static function backupurl(array $params = []): \moodle_url {
        return self::baseurl($params + ['section' => 'backup']);
    }

    /**
     * Restore section URL
     *
     * @param array $params
     * @return \moodle_url
     */
    public static function restoreurl(array $params = []): \moodle_url {
        return self::baseurl($params + ['section' => 'restore']);
    }

    /**
     * Settings section URL
     *
     * @param array $params
     * @return \moodle_url
     */
    public static function settingsurl(array $params = []): \moodle_url {
        return self::baseurl($params + ['section' => 'settings']);
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
}
