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
 * Model for remote backup file
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @property-read int $id
 * @property-read int $operationid
 * @property-read string $filetype
 * @property-read int $seq
 * @property-read string $status
 * @property-read int $filesize
 * @property-read int $origsize
 * @property-read string $details
 * @property-read string $etag
 * @property-read int $timecreated
 * @property-read int $timemodified
 */
class backup_file {
    /** @var string */
    const TABLE = 'tool_vault_backup_file';
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
     * Update or create in DB
     *
     * @return $this
     */
    public function save(): self {
        global $DB;
        $this->data['timemodified'] = time();
        if (empty($this->data['id'])) {
            $this->data['timecreated'] = time();
            if (!array_key_exists('seq', $this->data)) {
                $maxseq = $DB->get_field_sql('SELECT MAX(seq) FROM {'.self::TABLE.'} WHERE operationid = ? AND filetype = ?',
                    [$this->data['operationid'], $this->data['filetype']]);
                $this->data['seq'] = ($maxseq === null) ? 1 : ($maxseq + 1);
            }
            $this->data['id'] = $DB->insert_record(self::TABLE, $this->data);
        } else {
            $DB->update_record(self::TABLE, $this->data);
        }
        return $this;
    }
}
