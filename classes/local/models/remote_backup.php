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

namespace tool_vault\local\models;

use tool_vault\constants;

/**
 * Model for remote backup
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @property-read int $timecreated
 * @property-read string $backupkey
 * @property-read int $timefinished
 * @property-read string $status
 * @property-read array $info
 * @property-read array $files
 */
class remote_backup {
    /** @var array */
    protected $data;

    /**
     * Constructor
     *
     * @param array $b
     */
    public function __construct(array $b) {
        $this->data = $b;
    }

    /**
     * Magic getter
     *
     * @param string $name
     * @return mixed|null
     */
    public function __get(string $name) {
        return $this->data[$name] ?? null;
    }

    /**
     * Convert to object
     *
     * @return \stdClass
     */
    public function to_object(): \stdClass {
        return (object)$this->data;
    }

    /**
     * Get the list of dbdump files in this backup
     *
     * @return remote_backup_file[]
     */
    public function get_dbdump_files(): array {
        return $this->get_files_with_prefix(constants::FILENAME_DBDUMP);
    }

    /**
     * Get the list of datadir files in this backup
     *
     * @return remote_backup_file[]
     */
    public function get_dataroot_files(): array {
        return $this->get_files_with_prefix(constants::FILENAME_DATAROOT);
    }

    /**
     * Get the list of filedir files in this backup
     *
     * @return remote_backup_file[]
     */
    public function get_filedir_files(): array {
        return $this->get_files_with_prefix(constants::FILENAME_FILEDIR);
    }

    /**
     * Returns the list of backup files that have given prefix, ordered by the number after the prefix
     *
     * @param string $prefix
     * @return remote_backup_file[]
     */
    protected function get_files_with_prefix(string $prefix): array {
        $res = [];
        foreach ($this->files ?? [] as $file) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '([\d]*)\.zip$/', $file['name'], $matches)) {
                $res[] = new remote_backup_file($file + ['ord' => (int)$matches[1]]);
            }
        }
        usort($res, function($a, $b) {
            return $a->ord - $b->ord;
        });
        return $res;
    }

    /**
     * Throws exception if the version of this site is not compatible with the version of the remote backup
     *
     * @throws \moodle_exception
     */
    public function ensure_version_compatibility() {
        global $CFG;
        $branch = $this->info['branch'] ?? null;

        if ("{$branch}" !== "{$CFG->branch}") {
            $branch = $this->backup->info['branch'] ?? '';
            $error = "Can not restore backup made on a different branch (major version) of Moodle. ".
                "This backup branch is '{$branch}' and this site branch is '{$CFG->branch}'";
        } else if ((int)$this->info['version'] > (int)$CFG->version) {
            $error = "Site version number has to be not lower than the version in the backup. ".
                "This backup is {$this->backup->info['version']} and this site is {$CFG->version}";
        } else {
            return;
        }

        throw new \moodle_exception($error);
    }
}
