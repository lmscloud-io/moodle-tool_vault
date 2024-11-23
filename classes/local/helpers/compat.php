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
 * Compatibility methods for functions that are not available in all Moodle versions
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class compat {

    /**
     * Function make_backup_temp_directory that was not available in 3.4
     *
     * @param mixed $directory
     * @param mixed $exceptiononerror
     * @return string|false
     */
    public static function make_backup_temp_directory($directory, $exceptiononerror = true) {
        global $CFG;
        if (function_exists('make_backup_temp_directory')) {
            return make_backup_temp_directory($directory, $exceptiononerror);
        }

        $backupdir = defined('PHPUNIT_BACKUPTEMPDIR') ? PHPUNIT_BACKUPTEMPDIR :
            (isset($CFG->backuptempdir) ? $CFG->backuptempdir : null);
        if (!empty($backupdir) && $backupdir !== "$CFG->tempdir/backup") {
            check_dir_exists($backupdir, true, true);
            protect_directory($backupdir);
            return make_writable_directory("$backupdir/$directory", $exceptiononerror);
        } else {
            protect_directory($CFG->tempdir);
            return make_writable_directory("$CFG->tempdir/backup/$directory", $exceptiononerror);
        }
    }
}
