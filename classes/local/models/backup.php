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
 * Model for local backup
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @property-read string $backupkey
 * @property-read string $status
 * @property-read int $timecreated
 * @property-read int $timemodified
 * @property-read string $metadata
 * @property-read string $logs
 */
class backup {
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
     * Magic getter
     *
     * @param string $name
     * @return mixed|null
     */
    public function __get(string $name) {
        return $this->data[$name] ?? null;
    }

    /**
     * Metadata
     *
     * @return array|mixed
     */
    public function get_metadata() {
        return !empty($this->data['metadata']) ? json_decode($this->data['metadata'], true) : [];
    }

    /**
     * Get logs, no more than 4 lines
     *
     * @return string
     */
    public function get_logs_shortened() {
        $lines = preg_split('/\\n/', trim((string)$this->logs));
        if (count($lines) > 4) {
            return
                $lines[0]."\n".
                $lines[1]."\n".
                "...\n".
                $lines[count($lines) - 2]."\n".
                $lines[count($lines) - 1];
        }
        return trim((string)$this->logs);
    }

    /**
     * Get display title
     *
     * @return string
     */
    public function get_title() {
        if ($this->backupkey) {
            return 'Backup ' . $this->backupkey;
        } else if ($this->status === constants::STATUS_SCHEDULED) {
            return 'Backup (scheduled)';
        } else {
            return 'Backup';
        }
    }

    /**
     * Get status and time modified
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_subtitle() {
        return 'Status '.$this->status.' : '.userdate($this->timemodified, get_string('strftimedatetimeshort', 'langconfig'));
    }
}
