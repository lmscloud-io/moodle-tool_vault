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
     * Get description
     *
     * @return string
     */
    public function get_description(): string {
        return $this->info['description'] ?? '';
    }

    /**
     * Get is encrypted
     *
     * @return bool
     */
    public function get_encrypted(): bool {
        return $this->info['encrypted'] ?? false;
    }

    /**
     * Get finished time (if the process finished)
     *
     * @return int
     */
    public function get_finished_time(): int {
        if (!in_array($this->status, [constants::STATUS_INPROGRESS, constants::STATUS_SCHEDULED])) {
            return $this->info['timefinished'] ?? $this->timemodified;
        }
        return 0;
    }

    /**
     * Is same site
     *
     * @return bool
     */
    public function is_same_site(): bool {
        return $this->info['samesite'] ?? false;
    }

    /**
     * Get total size
     *
     * @return int
     */
    public function get_total_size(): int {
        return $this->info['totalsize'] ?? 0;
    }
}
