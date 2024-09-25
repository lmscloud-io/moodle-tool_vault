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

/**
 * Class plugincode
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugincode {

    /**
     * Get list of directories with add-on plugins (do not list the same dir twice for the subplugins)
     *
     * @return array
     */
    public static function get_addon_directories_list() {
        $excludedplugins = siteinfo::get_excluded_plugins_backup();
        $pluginlistfull = siteinfo::get_plugins_list_full();
        $pluginlist = array_diff_key($pluginlistfull, array_fill_keys($excludedplugins, true));
        unset($pluginlist['tool_vault']);

        $paths = [];
        foreach ($pluginlist as $pluginname => $plugininfo) {
            if (!empty($plugininfo['isaddon'])) {
                $parent = empty($plugininfo['parent']) ? null : $pluginlistfull[$plugininfo['parent']];
                if (!$parent || empty($parent['isaddon'])) {
                    $paths[] = $plugininfo['path'];
                }
            }
        }

        return $paths;
    }

    /**
     * Get number of add-on plugins (except tool_vault)
     *
     * @return int
     */
    public static function get_addon_plugins_count(): int {
        $excludedplugins = siteinfo::get_excluded_plugins_backup();
        $pluginlistfull = siteinfo::get_plugins_list_full();
        $pluginlist = array_diff_key($pluginlistfull, array_fill_keys($excludedplugins, true));
        unset($pluginlist['tool_vault']);

        $count = 0;
        foreach ($pluginlist as $pluginname => $plugininfo) {
            if (!empty($plugininfo['isaddon'])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get size of a single directory
     *
     * @param string $path
     * @return int
     */
    public static function get_directory_size(string $path): int {
        $bytestotal = 0;
        $path = realpath($path);
        if ($path !== false && $path != '' && file_exists($path)) {
            foreach (new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $object) {
                // TODO check that $object->getPath() does not contain /.git/ etc. Or find a way to exclude them from iterator.
                try {
                    $bytestotal += $object->getSize();
                // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
                } catch (\Throwable $e) {
                    // Means we will not be able to copy this file anyway.
                }
            }
        }
        return $bytestotal;
    }

    /**
     * Get total size of all add-on plugins code
     *
     * @return int
     */
    public static function get_total_addon_size(): int {
        global $CFG;
        $paths = self::get_addon_directories_list();
        $total = 0;
        foreach ($paths as $path) {
            $total += self::get_directory_size($CFG->dirroot . '/' . $path);
        }
        return $total;
    }
}
