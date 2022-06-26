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
     * Should the datadir subfolder be skipped and not deleted during restore
     *
     * @param string $path relative path under $CFG->datadir
     * @return bool
     */
    public function is_dir_skipped(string $path): bool {
        return in_array($path, [
                '__vault_restore__'
            ]) || preg_match('/^\\./', $path);
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
        $filename1 = 'dbdump.zip';
        $filename2 = 'dataroot.zip';
        $filepath1 = $tempdir.DIRECTORY_SEPARATOR.$filename1;
        $filepath2 = $tempdir.DIRECTORY_SEPARATOR.$filename2;

        try {
            mtrace("Downloading file $filename1 ...");
            api::download_backup_file($this->restore->backupkey, $filepath1);
        } catch (\Throwable $t) {
            mtrace($t->getMessage());
            $this->update_restore(['status' => self::STATUS_FAILED], 'Could not download file '.$filename1);
            return;
        }

        try {
            mtrace("Downloading file $filename2 ...");
            api::download_backup_file($this->restore->backupkey, $filepath2);
        } catch (\Throwable $t) {
            mtrace($t->getMessage());
            $this->update_restore(['status' => self::STATUS_FAILED], 'Could not download file '.$filename2);
            return;
        }

        $dbfiles = $this->prepare_restore_db($filepath1);
        $datarootfiles = $this->prepare_restore_dataroot($filepath2);
        unlink($filepath1);
        unlink($filepath2);

        $this->restore_db($dbfiles);
        $this->restore_dataroot($datarootfiles);
        // TODO more logging.

        $this->update_restore(['status' => self::STATUS_FINISHED], 'Restore finished');

        $this->post_restore();
    }

    /**
     * Prepare to restore db
     *
     * @param string $filepath
     * @return array
     */
    public function prepare_restore_db(string $filepath) {
        $temppath = make_temp_directory('dbdump');
        $zippacker = new \zip_packer();
        $zippacker->extract_to_pathname($filepath, $temppath);

        $handle = opendir($temppath);
        $files = [];
        while (($file = readdir($handle)) !== false) {
            if (!preg_match('/^\\./', $file)) {
                $p = pathinfo($file);
                $files[$p['filename']] = $temppath.DIRECTORY_SEPARATOR.$file;
            }
        }
        closedir($handle);

        // TODO do all the checks that all tables exist and have necessary fields.

        return $files;
    }

    /**
     * Prepare to restore dataroot
     *
     * @param string $filepath
     * @return array
     */
    public function prepare_restore_dataroot(string $filepath) {
        global $CFG;
        $temppath = $CFG->dataroot.DIRECTORY_SEPARATOR.'__vault_restore__';
        $this->remove_recursively($temppath);
        make_writable_directory($temppath);
        $zippacker = new \zip_packer();
        $zippacker->extract_to_pathname($filepath, $temppath);

        $handle = opendir($temppath);
        $files = [];
        while (($file = readdir($handle)) !== false) {
            if (!preg_match('/^\\./', $file)) {
                $files[$file] = $temppath.DIRECTORY_SEPARATOR.$file;
            }
        }
        closedir($handle);
        return $files;
    }

    /**
     * Restore db
     *
     * @param array $files
     * @return void
     */
    public function restore_db(array $files) {
        global $DB;
        foreach ($files as $tablename => $path) {
            $data = json_decode(file_get_contents($path));
            $DB->execute('TRUNCATE TABLE {'.$tablename.'}');
            if ($data) {
                $fields = array_shift($data);
                foreach ($data as $row) {
                    $DB->insert_record_raw($tablename, array_combine($fields, $row), false, true, true);
                }
                $this->seq($tablename);
            }
        }
    }

    /**
     * Fix sequence on db table
     *
     * for postgres: https://stackoverflow.com/questions/244243/how-to-reset-postgres-primary-key-sequence-when-it-falls-out-of-sync
     *
     * @param string $tablename
     * @return void
     */
    public function seq(string $tablename) {
        global $DB;
        $prefix = $DB->get_prefix();
        try {
            $DB->execute("SELECT setval('{$prefix}{$tablename}_id_seq', ".
                "COALESCE((SELECT MAX(id)+1 FROM {$prefix}{$tablename}), 1), false)");
        } catch (\Throwable $t) {
            mtrace("- failed to set seq for table $tablename: ".$t->getMessage());
        }
    }

    /**
     * Restore dataroot
     *
     * @param array $files
     * @return void
     */
    public function restore_dataroot(array $files) {
        global $CFG;
        $handle = opendir($CFG->dataroot);
        while (($file = readdir($handle)) !== false) {
            if (!$this->is_dir_skipped($file)) {
                $this->remove_recursively($CFG->dataroot.DIRECTORY_SEPARATOR.$file);
            }
        }
        closedir($handle);

        foreach ($files as $file => $path) {
            rename($path, $CFG->dataroot.DIRECTORY_SEPARATOR.$file);
        }

        $this->remove_recursively($CFG->dataroot.DIRECTORY_SEPARATOR.'__vault_restore__');
    }

    /**
     * Post restore
     *
     * @return void
     */
    public function post_restore() {
        purge_all_caches();
    }

    /**
     * Remove directory recursively
     *
     * @param string $dir
     * @return void
     */
    public function remove_recursively(string $dir) {
        if (!file_exists($dir)) {
            return;
        }
        if (!is_dir($dir)) {
            unlink($dir);
            return;
        }
        $it = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($it,
            \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
    }
}
