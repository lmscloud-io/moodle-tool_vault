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
    public static function make_temp_dir($prefix = '') {
        global $CFG;
        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST && defined('PHPUNIT_BACKUPTEMPDIR') && isset($CFG->backuptempdir)) {
            $oldvalue = $CFG->backuptempdir;
            $CFG->backuptempdir = PHPUNIT_BACKUPTEMPDIR;
        }
        $backupdir = compat::make_backup_temp_directory('tool_vault');
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
    protected static function make_unique_writable_directory($basedir, $prefix = '') {
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
    public static function remove_temp_dir($dir) {
        if (!file_exists($dir)) {
            return 0;
        }
        if (!is_dir($dir)) {
            return (int)unlink($dir);
        }
        $cnt = 0;
        try {
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
            // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        } catch (\Exception $e) {
            // Some subpaths were not readable.
        }
        unset(self::$createddirs[$dir]);
        return $cnt;
    }

    /**
     * Get free space in the temp dir
     *
     * @param int $minrequiredspace check that there is at least this amount of space
     * @return bool|float number of bytes available or 'true' if there is enough space but the exact number
     *     could not be evaluated.
     */
    public static function get_free_space($minrequiredspace) {
        $dir = compat::make_backup_temp_directory('tool_vault');
        if (function_exists('disk_free_space') && ($freespace = @disk_free_space($dir)) > 0) {
            return $freespace;
        }

        return self::get_free_space_fallback($dir, $minrequiredspace);
    }

    /**
     * When function disk_free_space() is not available, try to place a big file in the temp directory.
     *
     * @param string $dir
     * @param int $minrequiredspace check that there is at least this amount of space
     * @return bool|int number of bytes available or 'true' if there is enough space but the exact number
     *     could not be evaluated.
     */
    public static function get_free_space_fallback($dir, $minrequiredspace) {
        $tempfile = $dir . DIRECTORY_SEPARATOR . 'temp';
        $fh = null;
        $size = 0;
        try {
            $fh = fopen($tempfile, 'w');

            while ($size < $minrequiredspace) {
                $chunk = min($minrequiredspace - $size + 1, 1024 * 100);
                fwrite($fh, str_repeat('a', $chunk));
                $size += $chunk;
            }
            // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        } catch (\Exception $e) {
            // Exception means that we cold not write more data into the file and we ran out of space.
            // It probably looks like this:
            // fwrite(): Write of 16384 bytes failed with errno=28 No space left on device.
        }

        if ($fh) {
            fclose($fh);
        }
        @unlink($tempfile);
        return $size > $minrequiredspace ? true : $size;
    }

    /**
     * Check if directory is empty
     *
     * @param string $dir
     * @return bool
     */
    public static function dir_is_empty($dir) {
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
    public static function cleanup() {
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
