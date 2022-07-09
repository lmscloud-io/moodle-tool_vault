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

/**
 * Model for check
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @property-read int $id
 * @property-read int $timecreated
 * @property-read string $type
 * @property-read int $timemodified
 * @property-read string $status
 * @property-read array $details
 */
class check {
    /** @var array */
    protected $data;

    /**
     * Constructor
     *
     * @param \stdClass $b
     */
    public function __construct(\stdClass $b) {
        $this->data = (array)$b;
    }

    /**
     * Set error
     *
     * @param \Throwable $t
     * @return self
     */
    public function set_error(\Throwable $t): self {
        $details = $this->get_details();
        $details['error'] = $t->getMessage()."\n".$t->getTraceAsString(); // TODO store as array?
        $this->data['details'] = json_encode($details);
        return $this;
    }

    /**
     * Set status
     *
     * @param string $status
     * @return self
     */
    public function set_status(string $status): self {
        $this->data['status'] = $status;
        return $this;
    }

    /**
     * Update or create in DB
     *
     * @return self
     */
    public function save(): self {
        global $DB;
        $this->data['timemodified'] = time();
        if (empty($this->data['id'])) {
            $this->data['timecreated'] = time();
            $this->data['id'] = $DB->insert_record('tool_vault_checks', $this->data);
        } else {
            $DB->update_record('tool_vault_checks', $this->data);
        }
        return $this;
    }

    /**
     * Get details
     *
     * @return array
     */
    public function get_details(): array {
        if (isset($this->data['details'])) {
            return json_decode($this->data['details'], true);
        } else {
            return [];
        }
    }

    /**
     * Set details
     *
     * @param array $details
     * @return self
     */
    public function set_details(array $details): self {
        $this->data['details'] = json_encode($details);
        return $this;
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
}
