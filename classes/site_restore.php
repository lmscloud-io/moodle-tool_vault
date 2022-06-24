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

use tool_vault\task\restore_task;

/**
 * Perform site restore
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class site_restore {
    /** @var string */
    const STATUS_INPROGRESS = 'inprogress';
    /** @var string */
    const STATUS_SCHEDULED = 'scheduled';
    /** @var string */
    const STATUS_FINISHED = 'finished';
    /** @var string */
    const STATUS_FAILED = 'failed';

    /** @var \stdClass */
    protected $restore;

    /**
     * Get scheduled restore
     *
     * @return false|mixed|null
     */
    public static function get_scheduled_restore() {
        global $DB;
        $records = $DB->get_records('tool_vault_restores', ['status' => self::STATUS_SCHEDULED]);
        return $records ? reset($records) : null;
    }

    /**
     * Schedule restore
     *
     * @param string $backupkey
     * @return void
     */
    public static function schedule_restore(string $backupkey) {
        global $DB, $USER;
        $now = time();
        $backupmetadata = api::get_remote_backup($backupkey, self::STATUS_FINISHED);
        $DB->insert_record('tool_vault_restores', [
            'backupkey' => $backupkey,
            'status' => self::STATUS_SCHEDULED,
            'timecreated' => $now,
            'timemodified' => $now,
            'backupmetadata' => json_encode($backupmetadata),
            'userdata' => json_encode([
                'id' => $USER->id,
                'username' => $USER->username,
                'fullname' => fullname($USER),
                'email' => $USER->email,
            ]),
            'logs' => api::format_date_for_logs($now)." "."Restore scheduled",
        ]);
        restore_task::schedule();
    }

    /**
     * Update record in restores table
     *
     * @param array $data
     * @param string $log
     * @return void
     */
    protected function update_restore(array $data, string $log) {
        global $DB;
        $restore = $DB->get_record('tool_vault_restores', ['id' => $this->restore->id]);
        $data['id'] = $this->restore->id;
        $now = time();
        if ($data['status'] ?? '' === self::STATUS_INPROGRESS && $restore->status === self::STATUS_SCHEDULED) {
            $data['timestarted'] = $now;
        }
        if ($data['status'] ?? '' === self::STATUS_FINISHED) {
            $data['timefinished'] = $now;
        }
        $data['timemodified'] = $now;
        if (strlen($log)) {
            $data['logs'] = $restore->logs.api::format_date_for_logs($now)." ".$log."\n";
        }
        $DB->update_record('tool_vault_restores', (object)$data);

    }

    /**
     * Perform restore
     *
     * @return void
     */
    public function execute() {
        $restore = self::get_scheduled_restore();
        if (!$restore) {
            throw new \moodle_exception('No restores scheduled');
        }
        $this->restore = $restore;
        try {
            api::get_remote_backup($this->restore->backupkey, self::STATUS_FINISHED);
        } catch (\moodle_exception $e) {
            $error = "Backup with the key {$restore->backupkey} is no longer avaialable";
            $this->update_restore(['status' => self::STATUS_FAILED], $error);
            throw new \moodle_exception($error);
        }
        $this->update_restore(['status' => self::STATUS_INPROGRESS], 'Restore started');

        // Download files.
        $tempdir = make_request_directory();
        $filename = 'dbdump.sql';
        $filepath = $tempdir.DIRECTORY_SEPARATOR.$filename;

        try {
            mtrace("Downloading file $filename ...");
            api::download_backup_file($this->restore->backupkey, $filepath);
            mtrace(file_get_contents($filepath));
        } catch (\Throwable $t) {
            mtrace($t->getMessage());
            $this->update_restore(['status' => self::STATUS_FAILED], 'Could not download file '.basename($filepath));
            return;
        }

        // TODO.

        $this->update_restore(['status' => self::STATUS_FINISHED], 'Restore finished');
    }
}
