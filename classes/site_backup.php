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

namespace tool_vault;

use tool_vault\task\backup_task;

/**
 * Perform site backup
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class site_backup {
    /** @var string */
    protected $backupkey;

    /** @var string */
    const STATUS_INPROGRESS = 'inprogress';
    /** @var string */
    const STATUS_SCHEDULED = 'scheduled';
    /** @var string */
    const STATUS_FINISHED = 'finished';
    /** @var string */
    const STATUS_FAILED = 'failed';
    /** @var string */
    const STATUS_FAILEDTOSTART = 'failedtostart';

    /**
     * Constructor
     *
     * @param string $backupkey
     */
    public function __construct(string $backupkey) {
        $this->backupkey = $backupkey;
    }

    /**
     * List of all backups performed on this server
     *
     * @param array|null $statuses
     * @return array
     */
    protected static function get_backups(?array $statuses = null) {
        global $DB;
        if ($statuses) {
            [$sql, $params] = $DB->get_in_or_equal($statuses);
            $sql = 'status '.$sql;
        } else {
            $sql = '1=1';
        }
        return $DB->get_records_select('tool_vault_backups', $sql, $params ?? [], 'timecreated DESC');
    }

    /**
     * Schedules new backup
     *
     * @return void
     */
    public static function schedule_backup() {
        global $DB, $USER;
        if ($backups = self::get_backups([self::STATUS_SCHEDULED])) {
            // Pressed button twice maybe?
            return;
        }
        if ($backups = self::get_backups([self::STATUS_INPROGRESS])) {
            throw new \moodle_exception('Another active backup found');
        }
        self::insert_backup(['status' => self::STATUS_SCHEDULED, 'usercreated' => $USER->id], "Backup scheduled");
        backup_task::schedule();
    }

    /**
     * If there is a scheduled backup, return it
     *
     * @return false|mixed
     */
    public static function get_scheduled_backup(): ?\stdClass {
        $backups = self::get_backups([self::STATUS_SCHEDULED]);
        return $backups ? reset($backups) : null;
    }

    /**
     * If there is a backup in progress, return it
     *
     * @return \stdClass|null
     */
    public static function get_backup_in_progress(): ?\stdClass {
        $backups = self::get_backups([self::STATUS_INPROGRESS]);
        return $backups ? reset($backups) : null;
    }

    /**
     * Get the last backup scheduled on this server
     *
     * @return false|mixed
     */
    public static function get_last_backup(): ?\stdClass {
        $backups = self::get_backups();
        return $backups ? reset($backups) : null;
    }

    /**
     * Insert a record in a backups DB table
     *
     * @param array $data
     * @param string $log
     * @return void
     */
    protected static function insert_backup(array $data, string $log) {
        global $DB;
        $now = time();
        $data['timecreated'] = $now;
        $data['timemodified'] = $now;
        $data['logs'] = "[".userdate($now, get_string('strftimedatetimeaccurate', 'core_langconfig'))."] ".$log."\n";
        $DB->insert_record('tool_vault_backups', (object)$data);
    }

    /**
     * Update a record in a backups DB table
     *
     * @param int $id
     * @param array $data
     * @param string $log
     * @return void
     */
    protected static function update_backup(int $id, array $data, string $log) {
        global $DB;
        $backup = $DB->get_record('tool_vault_backups', ['id' => $id]);
        $data['id'] = $id;
        $now = time();
        if ($data['statis'] ?? '' === self::STATUS_INPROGRESS && $backup->status === self::STATUS_SCHEDULED) {
            $data['timestarted'] = $now;
        }
        if ($data['statis'] ?? '' === self::STATUS_FINISHED) {
            $data['timefinished'] = $now;
        }
        if (in_array($data['statis'] ?? '', [self::STATUS_FAILEDTOSTART, self::STATUS_FAILED])) {
            $data['timefailes'] = $now;
        }
        $data['timemodified'] = $now;
        $data['logs'] = $backup->logs."[".userdate($now, get_string('strftimedatetimeaccurate', 'core_langconfig'))."] ".$log."\n";
        $DB->update_record('tool_vault_backups', (object)$data);
    }

    /**
     * Obtain backupkey
     *
     * @return mixed
     */
    public static function start_backup() {
        global $CFG, $USER, $DB;
        $backup = self::get_scheduled_backup();
        if (!$backup) {
            throw new \moodle_exception('No scheduled backup');
        }

        $params = [
            // TODO - what other metadata do we want - languages, installed plugins, estimated size?
            'wwwroot' => $CFG->wwwroot,
            'dbengine' => $DB->get_dbfamily(),
            'version' => $CFG->version,
            'tool_vault_version' => get_config('tool_vault', 'version'),
            'email' => $USER->email,
            'name' => fullname($USER),
        ];
        $res = api::api_call('backups', 'PUT', $params);
        $metadata = json_encode($params);
        if (!empty($res['backupkey'])) {
            self::update_backup($backup->id,
                ['status' => self::STATUS_INPROGRESS, 'backupkey' => $res['backupkey'], 'metadata' => $metadata],
                'Backup started');
        } else {
            // API rejected - aobve limit/quota. TODO store details of failure? how to notify user?
            self::update_backup($backup->id, ['status' => self::STATUS_FAILEDTOSTART, 'metadata' => $metadata],
                'Failed to start the backup with the cloud service');
            // @codingStandardsIgnoreLine
            throw new \coding_exception('Unknown response: '.print_r($res, true));
        }
        return $res['backupkey'];
    }

    /**
     * Execute backup
     *
     * @return void
     */
    public function execute() {
        global $DB;
        $backup = $DB->get_record('tool_vault_backups',
            ['backupkey' => $this->backupkey, 'status' => self::STATUS_INPROGRESS], '*', MUST_EXIST);

        // TODO.
        $tempdir = make_request_directory();
        $filename = 'dbdump.sql';
        $filepath = $tempdir.DIRECTORY_SEPARATOR.$filename;
        $fh = fopen($filepath, 'w+');
        // TODO error handling if empty($fh).
        fwrite($fh, 'hello');
        fclose($fh);

        api::upload_backup_file($this->backupkey, $filepath, 'text/plain');

        api::api_call("backups/{$this->backupkey}", 'PATCH', ['status' => 'finished']);
        self::update_backup($backup->id, ['status' => self::STATUS_FINISHED], 'Backup finished');

        // TODO notify user, register in our config.
    }
}
