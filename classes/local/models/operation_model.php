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
 * @property-read int $parentid
 */
abstract class operation_model {
    /** @var array */
    protected $data;
    /** @var int process id, not stored in DB but used for logging */
    protected $pid = 0;
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
        'parentid',
    ];

    /**
     * Constructor
     *
     * @param \stdClass $record
     */
    public function __construct(?\stdClass $record = null) {
        $record = $record ?? new \stdClass();
        foreach ($record as $key => $value) {
            if (!in_array($key, array_merge(self::$fields, ['id', 'timecreated', 'timemodified', 'parentid']))) {
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
        $details['error'] = $t->getMessage();
        $details['errorbacktrace'] = $t->getTraceAsString();
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
     * Get error stroed in the details
     *
     * @return bool
     */
    public function has_error(): bool {
        return ($this->get_details()['error'] ?? null) !== null;
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
     * Validate type
     *
     * @param string $type
     * @return bool
     */
    public static function validate_type(string $type) {
        return static::$defaulttype && $type === static::$defaulttype;
    }

    /**
     * Create an instance from a record - looks for a matching model class
     *
     * @param \stdClass $record
     * @return static|null
     */
    public static function instance(\stdClass $record): ?self {
        if (static::validate_type($record->type)) {
            return new static($record);
        }
        $classes = \core_component::get_component_classes_in_namespace('tool_vault', 'local\models');
        foreach (array_keys($classes) as $class) {
            $rc = new \ReflectionClass($class);
            if ($rc->isInstantiable() && is_subclass_of($class, self::class) &&
                    $class::validate_type($record->type)) {
                return new $class($record);
            }
        }
        return null;
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
        return $record && static::validate_type($record->type) ? new static($record) : null;
    }

    /**
     * Set pid for logging
     *
     * @param int $pid
     * @return void
     */
    public function set_pid_for_logging(int $pid) {
        $this->pid = $pid;
    }

    /**
     * Add log line to this operation
     *
     * @param string $message
     * @param string $loglevel
     * @return \stdClass
     */
    public function add_log(string $message, string $loglevel = constants::LOGLEVEL_INFO): \stdClass {
        if (!$this->id) {
            throw new \coding_exception('Can not add logs, save the record first');
        }
        global $DB;
        $record = (object)[
            'operationid' => $this->id,
            'timecreated' => time(),
            'loglevel' => $loglevel,
            'message' => substr($message, 0, 1333),
            'pid' => $this->pid,
        ];
        $record->id = $DB->insert_record(self::LOGTABLE, $record);
        return $record;
    }

    /**
     * Get records
     *
     * @param string $sql
     * @param array $params
     * @param string $sort
     * @return operation_model[]
     */
    protected static function get_records_select(string $sql, array $params = [], string $sort = 'timecreated DESC'): array {
        global $DB;
        $records = $DB->get_records_select(self::TABLE, $sql, $params ?? [], $sort);
        return array_filter(array_map(function($b) {
            return static::instance($b);
        }, $records));
    }

    /**
     * Format log line
     *
     * @param \stdClass|null $log
     * @param bool $usehtml
     * @return string
     */
    public function format_log_line(?\stdClass $log, bool $usehtml = true): string {
        if (!$log) {
            $class = 'tool_vault-log tool_vault-log-level-skipped';
            return $usehtml ? \html_writer::span('...', $class) : '...';
        }
        $class = 'tool_vault-log tool_vault-log-level-'.($log->loglevel ?: constants::LOGLEVEL_INFO);
        $message =
            "[".userdate($log->timecreated, get_string('strftimedatetimeshort', 'core_langconfig'))."] ".
            ($log->loglevel ? "[{$log->loglevel}] " : '') .
            ($log->pid ? "[pid {$log->pid}] " : '') .
            $log->message;
        return $usehtml ? \html_writer::span($message, $class) : $message;
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
     * @param string $sort
     * @return static[]
     */
    public static function get_records(?array $statuses = null, string $sort = 'timecreated DESC'): array {
        global $DB;
        if (static::$defaulttype) {
            $sql = 'type = :type';
            $params = ['type' => static::$defaulttype];
        } else if (static::$defaulttypeprefix) {
            $sql = 'type LIKE :type';
            $params = ['type' => static::$defaulttypeprefix.'%'];
        } else {
            $sql = '1=1';
            $params = [];
        }
        if ($statuses) {
            [$sql2, $params2] = $DB->get_in_or_equal($statuses, SQL_PARAMS_NAMED);
            $sql .= ' AND status '.$sql2;
        }
        return static::get_records_select($sql, ($params2 ?? []) + $params, $sort);
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
                return new backup_model($record);
            } else if ($record->type === 'restore') {
                return new restore_model($record);
            }
        }
        return null;
    }

    /**
     * Get last timestamp recorded on the model or on the logs
     *
     * @return int
     */
    public function get_last_modified(): int {
        global $DB;
        $sql = 'SELECT MAX(timecreated) FROM {'.self::LOGTABLE.'} WHERE operationid = ?';
        return max($this->timecreated, $this->timemodified,
            $DB->get_field_sql($sql, [$this->id]));
    }
}
