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

/**
 * The files_restore_mock class.
 *
 * @package     tool_vault
 * @category    test
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_vault\fixtures;

/**
 * Mock class for files_restore helper
 */
class files_restore_mock extends \tool_vault\local\helpers\files_restore {
    /** @var array */
    protected static $archives = [];

    /**
     * Set archive to use
     *
     * @param string $filepath
     * @return void
     */
    public static function use_archive(string $filepath) {
        static::$archives[basename($filepath)] = $filepath;
    }

    /**
     * Overrides parent function to use the mock archive instead of downloading
     *
     * @return string path to the archive file
     */
    protected function download_backup_file(): string {
        $key = $this->backupfiles[$this->currentseq]->get_file_name();
        $file = static::$archives[$key];
        unset(static::$archives[$key]);
        return $file;
    }
}