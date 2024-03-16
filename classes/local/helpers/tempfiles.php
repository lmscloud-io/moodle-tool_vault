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
 * Helper to work with temporary files
 *
 * @package    tool_vault
 * @copyright  2024 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tempfiles {
    /** @var array */
    protected static $createddirs = [];

    /**
     * Make temporary directory within backup temp dir
     *
     * @param string $prefix
     * @return string
     */
    public static function make_temp_dir(string $prefix = ''): string {
        global $CFG;
        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST && defined('PHPUNIT_BACKUPTEMPDIR')) {
            $oldvalue = $CFG->backuptempdir;
            $CFG->backuptempdir = PHPUNIT_BACKUPTEMPDIR;
        }
        $backupdir = make_backup_temp_directory('tool_vault');
        if (isset($oldvalue)) {
            $CFG->backuptempdir = $oldvalue;
        }
        $dir = self::make_unique_writable_directory($backupdir, $prefix);
        self::$createddirs[$dir] = true;
        return $dir;
    }

    /**
     * Same as core make_unique_writable_directory() but with a prefix
     *
     * @param string $basedir
     * @param string $prefix
     * @return string
     */
    protected static function make_unique_writable_directory(string $basedir, string $prefix = ''): string {
        if (!is_dir($basedir) || !is_writable($basedir)) {
            // The basedir is not writable. We will not be able to create the child directory.
            throw new \invalid_dataroot_permissions($basedir . ' is not writable. Unable to create a unique directory within it.');
        }

        do {
            $uniquedir = $basedir . DIRECTORY_SEPARATOR . uniqid($prefix);
        } while (
            // Ensure that basedir is still writable - if we do not check, we could get stuck in a loop here.
            is_writable($basedir) &&

            // Make the new unique directory. If the directory already exists, it will return false.
            !make_writable_directory($uniquedir, true) &&

            // Ensure that the directory now exists.
            file_exists($uniquedir) && is_dir($uniquedir)
        );

        // Check that the directory was correctly created.
        if (!file_exists($uniquedir) || !is_dir($uniquedir) || !is_writable($uniquedir)) {
            throw new \invalid_dataroot_permissions('Unique directory creation failed.');
        }

        return $uniquedir;
    }

    /**
     * Remove directory recursively
     *
     * @param string $dir
     * @return int count of removed files
     */
    public static function remove_temp_dir(string $dir): int {
        if (!file_exists($dir)) {
            return 0;
        }
        if (!is_dir($dir)) {
            return (int)unlink($dir);
        }
        $cnt = 0;
        $it = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($it,
            \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                $cnt += (int)unlink($file->getRealPath());
            }
        }
        rmdir($dir);
        unset(self::$createddirs[$dir]);
        return $cnt;
    }

    /**
     * Get free space in the temp dir
     *
     * @return bool|float
     */
    public static function get_free_space() {
        $dir = make_backup_temp_directory('tool_vault');
        return disk_free_space($dir);
    }

    /**
     * Check if directory is empty
     *
     * @param string $dir
     * @return bool
     */
    public static function dir_is_empty(string $dir): bool {
        $handle = opendir($dir);
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != "..") {
                closedir($handle);
                return false;
            }
        }
        closedir($handle);
        return true;
    }

    /**
     * Remove all temporary directories and files that were not removed earlier
     *
     * @return void
     */
    public static function cleanup(): void {
        foreach (self::$createddirs as $dir => $unused) {
            if (file_exists($dir)) {
                debugging('Removing abandonded temporary directory: ' . $dir, DEBUG_DEVELOPER);
                self::remove_temp_dir($dir);
            } else {
                unset(self::$createddirs[$dir]);
            }
        }
    }
}
