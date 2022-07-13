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

use tool_monitor\output\managesubs\subs;
use tool_vault\local\xmldb\dbstructure;
use tool_vault\task\restore_task;

/**
 * Perform site restore
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class site_restore {

    /** @var \stdClass */
    protected $restore;

    /**
     * Get scheduled restore
     *
     * @return false|mixed|null
     */
    public static function get_scheduled_restore() {
        global $DB;
        $records = $DB->get_records('tool_vault_restores', ['status' => constants::STATUS_SCHEDULED]);
        return $records ? reset($records) : null;
    }

    /**
     * Should the dataroot subfolder be skipped and not deleted during restore
     *
     * @param string $path relative path under $CFG->dataroot
     * @return bool
     */
    public static function is_dir_skipped(string $path): bool {
        return site_backup::is_dir_skipped($path);
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
        $backupmetadata = api::get_remote_backup($backupkey, constants::STATUS_FINISHED);
        $DB->insert_record('tool_vault_restores', [
            'backupkey' => $backupkey,
            'status' => constants::STATUS_SCHEDULED,
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
        if ($data['status'] ?? '' === constants::STATUS_INPROGRESS && $restore->status === constants::STATUS_SCHEDULED) {
            $data['timestarted'] = $now;
        }
        if ($data['status'] ?? '' === constants::STATUS_FINISHED) {
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
            api::get_remote_backup($this->restore->backupkey, constants::STATUS_FINISHED);
        } catch (\moodle_exception $e) {
            $error = "Backup with the key {$restore->backupkey} is no longer avaialable";
            $this->update_restore(['status' => constants::STATUS_FAILED], $error);
            throw new \moodle_exception($error);
        }
        $this->update_restore(['status' => constants::STATUS_INPROGRESS], 'Restore started');

        // Download files.
        $tempdir = make_request_directory();
        $filename1 = constants::FILENAME_DBDUMP . '.zip';
        $filename2 = constants::FILENAME_DATAROOT . '.zip';
        $filename3 = constants::FILENAME_FILEDIR . '.zip';
        $filepath1 = $tempdir.DIRECTORY_SEPARATOR.$filename1;
        $filepath2 = $tempdir.DIRECTORY_SEPARATOR.$filename2;
        $filepath3 = $tempdir.DIRECTORY_SEPARATOR.$filename3;

        try {
            mtrace("Downloading file $filename1 ...");
            api::download_backup_file($this->restore->backupkey, $filepath1);
        } catch (\Throwable $t) {
            mtrace($t->getMessage());
            $this->update_restore(['status' => constants::STATUS_FAILED], 'Could not download file '.$filename1);
            return;
        }

        try {
            mtrace("Downloading file $filename2 ...");
            api::download_backup_file($this->restore->backupkey, $filepath2);
        } catch (\Throwable $t) {
            mtrace($t->getMessage());
            $this->update_restore(['status' => constants::STATUS_FAILED], 'Could not download file '.$filename2);
            return;
        }

        try {
            mtrace("Downloading file $filename3 ...");
            api::download_backup_file($this->restore->backupkey, $filepath3);
        } catch (\Throwable $t) {
            mtrace($t->getMessage());
            $this->update_restore(['status' => constants::STATUS_FAILED], 'Could not download file '.$filename3);
            return;
        }

        $structure = $this->prepare_restore_db($filepath1);
        $datarootfiles = $this->prepare_restore_dataroot($filepath2);
        $filedirpath = $this->prepare_restore_filedir($filepath3);
        unlink($filepath2);
        unlink($filepath3);

        $this->restore_db($structure, $filepath1);
        unlink($filepath1);
        $this->restore_dataroot($datarootfiles);
        $this->restore_filedir($filedirpath);
        // TODO more logging.

        $this->update_restore(['status' => constants::STATUS_FINISHED], 'Restore finished');

        $this->post_restore();
    }

    /**
     * Prepare to restore db
     *
     * @param string $filepath
     * @return dbstructure
     */
    public function prepare_restore_db(string $filepath) {
        $structurefilename = constants::FILE_STRUCTURE;

        $temppath = make_temp_directory(constants::FILENAME_DBDUMP);
        $zippacker = new \zip_packer();
        $zippacker->extract_to_pathname($filepath, $temppath, [$structurefilename, 'xmldb.xsd']);
        $structure = dbstructure::load_from_backup($temppath.DIRECTORY_SEPARATOR.$structurefilename);

        // TODO do all the checks that all tables exist and have necessary fields.

        return $structure;
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
     * Prepare restore filedir
     *
     * @param string $filepath
     * @return string
     */
    public function prepare_restore_filedir(string $filepath): string {
        $temppath = make_temp_directory(constants::FILENAME_FILEDIR);
        $zippacker = new \zip_packer();
        $zippacker->extract_to_pathname($filepath, $temppath);

        return $temppath;
    }

    /**
     * Restore db
     *
     * @param dbstructure $structure
     * @param string $zipfilepath
     * @return void
     */
    public function restore_db(dbstructure $structure, string $zipfilepath) {
        global $DB;
        $temppath = make_temp_directory(constants::FILENAME_DBDUMP);
        $zippacker = new \zip_packer();

        $sequencesfilename = constants::FILE_SEQUENCE;
        $zippacker->extract_to_pathname($zipfilepath, $temppath, [$sequencesfilename]);
        $filepath = $temppath.DIRECTORY_SEPARATOR.$sequencesfilename;
        $sequences = json_decode(file_get_contents($filepath), true);
        unlink($filepath);

        foreach ($structure->get_backup_tables() as $tablename => $table) {
            $zippacker->extract_to_pathname($zipfilepath, $temppath, [$tablename.".json"]);
            $filepath = $temppath.DIRECTORY_SEPARATOR.$tablename.".json";
            $data = json_decode(file_get_contents($filepath));
            if ($altersql = $table->get_alter_sql($structure->get_tables_actual()[$tablename] ?? null)) {
                $DB->change_database_structure($altersql);
            }
            $DB->execute('TRUNCATE TABLE {'.$tablename.'}');
            if ($data) {
                $fields = array_shift($data);
                foreach ($data as $row) {
                    $DB->insert_record_raw($tablename, array_combine($fields, $row), false, true, true);
                }
            }
            if ($altersql = $table->get_fix_sequence_sql($sequences[$tablename] ?? 0)) {
                try {
                    $DB->change_database_structure($altersql);
                } catch (\Throwable $t) {
                    mtrace("- failed to change sequence for table $tablename: ".$t->getMessage());
                }
            }
            unlink($filepath);
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
     * List all files in a directory recursively
     *
     * @param string $pathtodir
     * @param string $prefix
     * @return array
     */
    public static function dirlist_recursive(string $pathtodir, string $prefix = ''): array {
        $files = [];
        if ($handle = opendir($pathtodir)) {
            while (false !== ($entry = readdir($handle))) {
                if (substr($entry, 0, 1) === '.') {
                    continue;
                } else if (is_dir($pathtodir . DIRECTORY_SEPARATOR . $entry)) {
                    $thisfiles = self::dirlist_recursive($pathtodir . DIRECTORY_SEPARATOR . $entry,
                        $prefix . $entry . DIRECTORY_SEPARATOR);
                    $files += $thisfiles;
                } else {
                    $files[$prefix . $entry] = $pathtodir . DIRECTORY_SEPARATOR . $entry;
                }
            }
            closedir($handle);
        }
        return $files;
    }

    /**
     * Restore filedir
     *
     * This function works with any file storage (local or remote)
     *
     * @param string $restoredir
     * @return void
     */
    public function restore_filedir(string $restoredir) {
        $fs = get_file_storage();
        $files = self::dirlist_recursive($restoredir);
        foreach ($files as $subpath => $filepath) {
            $file = basename($filepath);
            if ($subpath !== substr($file, 0, 2) . DIRECTORY_SEPARATOR . substr($file, 2, 2) . DIRECTORY_SEPARATOR . $file) {
                // Integrity check.
                debugging("Skipping unrecognised file detected in the filedir archive: ".$subpath);
                continue;
            }
            $fs->add_file_to_pool($filepath, $file);
            unlink($filepath);
        }
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
