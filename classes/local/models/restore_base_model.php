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
 * Base model for restore dry-run (checks only) and restore
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class restore_base_model extends operation_model {

    /**
     * Remote metadata
     *
     * @return array
     */
    public function get_metadata(): array {
        return ($this->get_remote_details()['metadata'] ?? []) + ($this->get_remote_details()['info'] ?? []);
    }

    /**
     * Remote files as array
     *
     * @return array
     */
    public function get_files(): array {
        return ($this->get_remote_details()['files'] ?? []);
    }

    /**
     * DB structure XML
     *
     * @return string
     */
    public function get_dbstructure_xml(): string {
        return ($this->get_remote_details()['dbstructure'] ?? '');
    }

    /**
     * Get description
     *
     * @return string
     */
    public function get_description(): string {
        return $this->get_remote_details()['info']['description'] ?? '';
    }

    /**
     * Get is encrypted
     *
     * @return bool
     */
    public function get_encrypted(): bool {
        return $this->get_remote_details()['info']['encrypted'] ?? false;
    }

    /**
     * Get performed by
     *
     * @return string
     */
    public function get_performedby(): string {
        $performedby = $this->get_details()['fullname'] ?? '';
        if (!empty($this->get_details()['email'])) {
            $performedby .= " <{$this->get_details()['email']}>";
        }
        return $performedby;
    }
}
