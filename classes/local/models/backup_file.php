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
        $this->data = $b + [
                'details' => json_encode([]),
                'origsize' => 0,
                'filesize' => 0,
                'etag' => null,
                'seq' => 0,
            ];
        // TODO check required fields - operationid, filetype, status.
    }

    /**
     * Create from params
     *
     * @param string $filename may have suffix -1 -2, etc that will be converted to seq
     * @param array $params
     * @return static|null
     */
    public static function create(string $filename, array $params = []): ?self {
        if (pathinfo($filename, PATHINFO_EXTENSION) !== 'zip') {
            return null;
        }
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $seq = 0;
        if (preg_match('/^(.*?)-([\d]+)$/', $name, $matches)) {
            $seq = (int)$matches[2];
            $name = $matches[1];
        }
        return new backup_file([
                'filetype' => $name,
                'seq' => $seq,
            ] + $params);
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

    /**
     * Get details as array
     *
     * @return array
     */
    public function get_details(): array {
        $details = json_decode($this->details, true);
        return is_array($details) ? $details : [];
    }

    /**
     * Get a detail with specific name
     *
     * @param string $key
     * @return mixed|null
     */
    public function get_detail(string $key) {
        return $this->get_details()[$key] ?? null;
    }

    /**
     * Setter for the status
     *
     * @param string $status
     * @return self
     */
    public function set_status(string $status): self {
        $this->data['status'] = $status;
        return $this;
    }

    /**
     * Setter for the filesize
     *
     * @param int $filesize
     * @return self
     */
    public function set_filesize(int $filesize): self {
        $this->data['filesize'] = $filesize;
        return $this;
    }

    /**
     * Setter for the origsize
     *
     * @param int $origsize
     * @return self
     */
    public function set_origsize(int $origsize): self {
        $this->data['origsize'] = $origsize;
        return $this;
    }

    /**
     * File name
     *
     * @return string
     */
    public function get_file_name(): string {
        $postfix = $this->seq ? "-{$this->seq}" : "";
        return $this->filetype . $postfix . '.zip';
    }

    /**
     * Setter for an individual detail
     *
     * @param string $key
     * @param array|string|int $value
     * @return void
     */
    public function update_detail(string $key, $value) {
        $details = $this->get_details();
        $details[$key] = $value;
        $this->data['details'] = json_encode($details);
    }
}
