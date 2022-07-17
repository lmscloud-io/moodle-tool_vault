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
 * Base model for operation
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @property-read int $id
 * @property-read string $type
 * @property-read string $backupkey
 * @property-read string $status
 * @property-read int $timecreated
 * @property-read int $timemodified
 * @property-read string $remotedetails
 * @property-read string $details
 * @property-read string $accesskey
 */
abstract class operation {
    /** @var array */
    protected $data;
    /** @var string */
    const TABLE = 'tool_vault_operation';
    /** @var string */
    const LOGTABLE = 'tool_vault_log';
    /** @var string */
    protected static $defaulttype;
    /** @var string */
    protected static $defaulttypeprefix;
    /** @var string[] */
    protected static $fields = [
        'type',
        'backupkey',
        'status',
        'details',
        'remotedetails',
        'accesskey',
    ];

    /**
     * Constructor
     *
     * @param \stdClass $record
     */
    public function __construct(?\stdClass $record = null) {
        $record = $record ?? new \stdClass();
        foreach ($record as $key => $value) {
            if (!in_array($key, array_merge(self::$fields, ['id', 'timecreated', 'timemodified']))) {
                throw new \coding_exception('Unknown field '.$key);
            }
        }
        if (!isset($record->type)) {
            if (static::$defaulttype) {
                $record->type = static::$defaulttype;
            } else {
                throw new \coding_exception('Record must contain property type');
            }
        }
        $this->data = (array)$record;
    }

    /**
     * Set error
     *
     * @param \Throwable $t
     * @return $this
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
     * @return $this
     */
    public function set_status(string $status): self {
        $this->data['status'] = $status;
        return $this;
    }

    /**
     * Set backup key
     *
     * @param string $backupkey
     * @return $this
     */
    public function set_backupkey(string $backupkey): self {
        $this->data['backupkey'] = $backupkey;
        return $this;
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
            $this->data['id'] = $DB->insert_record(self::TABLE, $this->data);
        } else {
            $DB->update_record(self::TABLE, $this->data);
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
     * Get details
     *
     * @return array
     */
    public function get_remote_details(): array {
        if (isset($this->data['remotedetails'])) {
            return json_decode($this->data['remotedetails'], true);
        } else {
            return [];
        }
    }

    /**
     * Set details
     *
     * @param array $details
     * @return $this
     */
    public function set_details(array $details): self {
        $this->data['details'] = json_encode($details + $this->get_details());
        return $this;
    }

    /**
     * Set remote details
     *
     * @param array $details
     * @return $this
     */
    public function set_remote_details(array $details): self {
        $this->data['remotedetails'] = json_encode($details + $this->get_remote_details());
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

    /**
     * Generate access key
     *
     * @return $this
     */
    public function generate_access_key(): self {
        $this->data['accesskey'] = random_string(32);
        return $this;
    }

    /**
     * Retrieve record by id
     *
     * @param int $id
     * @return static|null
     */
    public static function get_by_id(int $id): ?self {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['id' => $id]);
        return $record && (!self::$defaulttype || $record->type === self::$defaulttype) ? new static($record) : null;
    }

    /**
     * Add log line to this operation
     *
     * @param string $message
     * @param int $loglevel
     * @return void
     */
    public function add_log(string $message, int $loglevel = 0) {
        if (!$this->id) {
            throw new \coding_exception('Can not add logs, save the record first');
        }
        global $DB;
        $DB->insert_record(self::LOGTABLE, [
            'operationid' => $this->id,
            'timecreated' => time(),
            'loglevel' => $loglevel,
            'message' => $message
        ]);
    }

    /**
     * Get records
     *
     * @param string $sql
     * @param array $params
     * @param string $sort
     * @return operation[]
     */
    protected static function get_records_select(string $sql, array $params = [], string $sort = 'timecreated DESC'): array {
        global $DB;
        $records = $DB->get_records_select(self::TABLE, $sql, $params ?? [], $sort);
        return array_map(function($b) {
            return new static($b);
        }, $records);
    }

    /**
     * Format log line
     *
     * @param \stdClass|null $log
     * @return string
     */
    protected function format_log_line(?\stdClass $log): string {
        return $log ?
            "[".userdate($log->timecreated, get_string('strftimedatetimeaccurate', 'core_langconfig'))."] " . $log->message :
            '...';
    }

    /**
     * Get logs, no more than 4 lines
     *
     * @return string
     */
    public function get_logs_shortened(): string {
        global $DB;
        $logs = $DB->get_records(self::LOGTABLE, ['operationid' => $this->id], 'timecreated, id', '*', 0, 5);
        if (count($logs) >= 5) {
            // Display first two logs and last two logs.
            $logs2 = $DB->get_records(self::LOGTABLE, ['operationid' => $this->id], 'timecreated DESC, id DESC', '*', 0, 2);
            $logs = array_slice($logs, 0, 2);
            $logs[] = null;
            $logs[] = array_pop($logs2);
            $logs[] = array_pop($logs2);
        }
        return join("\n", array_map([$this, 'format_log_line'], $logs));
    }

    /**
     * Get all logs
     *
     * @return string
     */
    public function get_logs(): string {
        global $DB;
        $logs = $DB->get_records(self::LOGTABLE, ['operationid' => $this->id], 'timecreated, id');
        return join("\n", array_map([$this, 'format_log_line'], $logs));
    }

    /**
     * Get records with specified statuses
     *
     * @param array|null $statuses
     * @return static[]
     */
    public static function get_records(?array $statuses = null): array {
        global $DB;
        if (static::$defaulttype) {
            $sql = 'type = :type';
            $params = ['type' => static::$defaulttype];
        } else if (static::$defaulttypeprefix) {
            $sql = 'type LIKE :type';
            $params = ['type' => static::$defaulttypeprefix.'%'];
        } else {
            throw new \coding_exception('Method can not be used in this class');
        }
        if ($statuses) {
            [$sql2, $params2] = $DB->get_in_or_equal($statuses, SQL_PARAMS_NAMED);
            $sql .= ' AND status '.$sql2;
        }
        return static::get_records_select($sql, ($params2 ?? []) + $params);
    }

    /**
     * Returns an instance by access key
     *
     * @param string $accesskey
     * @return static|null
     */
    public static function get_by_access_key(string $accesskey): ?self {
        global $DB;
        if (empty($accesskey)) {
            return null;
        }
        $record = $DB->get_record(self::TABLE, ['accesskey' => $accesskey]);
        if ($record) {
            if ($record->type === 'backup') {
                return new backup($record);
            } else if ($record->type === 'restore') {
                return new restore($record);
            }
        }
        return null;
    }
}
