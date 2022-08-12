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

use tool_vault\local\checks\check_base;
use tool_vault\local\models\backup_model;
use tool_vault\local\models\restore_model;
use tool_vault\local\operations\operation_base;
use tool_vault\local\xmldb\dbstructure;

/**
 * Perform site restore
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class site_restore extends operation_base {

    /** @var restore_model */
    protected $model;
    /** @var check_base[] */
    protected $prechecks = null;
    /** @var dbstructure */
    protected $dbstructure = null;

    /**
     * Constructor
     *
     * @param restore_model $model
     */
    public function __construct(restore_model $model) {
        $this->model = $model;
    }

    /**
     * Get the last restore performed on this server
     *
     * @return ?restore_model
     */
    public static function get_last_restore(): ?restore_model {
        $records = restore_model::get_records();
        return $records ? reset($records) : null;
    }

    /**
     * Schedule restore
     *
     * @param array $params
     * @return static
     */
    public static function schedule(array $params = []): operation_base {
        global $USER;
        if (empty($params['backupkey'])) {
            throw new \coding_exception('Parameter backupkey is required for site_restore::schedule()');
        }
        if (!api::are_restores_allowed()) {
            throw new \moodle_exception('restoresnotallowed', 'tool_vault');
        }
        $backupkey = $params['backupkey'];
        if ($records = restore_model::get_records([constants::STATUS_SCHEDULED])) {
            // Pressed button twice maybe?
            return new static(reset($records));
        }
        if (restore_model::get_records([constants::STATUS_INPROGRESS])) {
            throw new \moodle_exception('Another restore is in progress');
        }
        if (backup_model::get_records([constants::STATUS_INPROGRESS, constants::STATUS_SCHEDULED])) {
            throw new \moodle_exception('Another backup is in progress');
        }

        $model = new restore_model();
        $model
            ->set_status( constants::STATUS_SCHEDULED)
            ->set_backupkey($backupkey)
            ->set_details([
                'id' => $USER->id ?? '',
                'username' => $USER->username ?? '',
                'fullname' => $USER ? fullname($USER) : '',
                'email' => $USER->email ?? '',
            ])
            ->save();
        $model->add_log("Restore scheduled");
        return new static($model);
    }

    /**
     * Start scheduled restore
     *
     * @param int $pid
     */
    public function start(int $pid) {
        if (!api::is_registered()) {
            throw new \moodle_exception('errorapikeynotvalid', 'tool_vault');
        }
        if (!api::are_restores_allowed()) {
            throw new \moodle_exception('restoresnotallowed', 'tool_vault');
        }
        parent::start($pid);
    }

    /**
     * Perform restore
     *
     * @return void
     * @throws \moodle_exception
     */
    public function execute() {
        $this->model
            ->set_status(constants::STATUS_INPROGRESS)
            ->save();
        $this->add_to_log('Preparing to restore');

        $this->prechecks = site_restore_dryrun::execute_prechecks($this->model, $this);

        // Download files.
        $tempdir = make_request_directory();
        $filename0 = constants::FILENAME_DBSTRUCTURE . '.zip';
        $filename1 = constants::FILENAME_DBDUMP . '.zip';
        $filename2 = constants::FILENAME_DATAROOT . '.zip';
        $filename3 = constants::FILENAME_FILEDIR . '.zip';
        $filepath0 = $tempdir.DIRECTORY_SEPARATOR.$filename0;
        $filepath1 = $tempdir.DIRECTORY_SEPARATOR.$filename1;
        $filepath2 = $tempdir.DIRECTORY_SEPARATOR.$filename2;
        $filepath3 = $tempdir.DIRECTORY_SEPARATOR.$filename3;

        api::download_backup_file($this->model->backupkey, $filepath0, $this);
        api::download_backup_file($this->model->backupkey, $filepath1, $this);
        api::download_backup_file($this->model->backupkey, $filepath2, $this);
        api::download_backup_file($this->model->backupkey, $filepath3, $this);

        $this->prepare_restore_db($filepath0);
        $datarootfiles = $this->prepare_restore_dataroot($filepath2);
        $filedirpath = $this->prepare_restore_filedir($filepath3);
        unlink($filepath2);
        unlink($filepath3);

        // From this moment on we can not throw any exceptions, we have to try to restore as much as possible skipping problems.
        $this->add_to_log('Restore started');

        $this->before_restore();

        $this->restore_db($filepath1);
        unlink($filepath1);
        $this->restore_dataroot($datarootfiles);
        $this->restore_filedir($filedirpath);

        $this->post_restore();
        $this->model->set_status(constants::STATUS_FINISHED)->save();
        $this->add_to_log('Restore finished');
    }

    /**
     * Retrns DB structure
     *
     * @return dbstructure
     */
    public function get_db_structure(): ?dbstructure {
        return $this->dbstructure;
    }

    /**
     * Prepare to restore db
     *
     * @param string $filepath
     */
    public function prepare_restore_db(string $filepath) {
        $structurefilename = constants::FILE_STRUCTURE;
        $this->add_to_log('Extracting database structure...');

        $temppath = make_request_directory();
        $zippacker = new \zip_packer();
        $zippacker->extract_to_pathname($filepath, $temppath, [$structurefilename, 'xmldb.xsd']);
        $this->dbstructure = dbstructure::load_from_backup($temppath.DIRECTORY_SEPARATOR.$structurefilename);

        // TODO do all the checks that all tables exist and have necessary fields.

        $this->add_to_log('...done');
    }

    /**
     * Prepare to restore dataroot
     *
     * @param string $filepath
     * @return array
     */
    public function prepare_restore_dataroot(string $filepath) {
        global $CFG;
        $this->add_to_log('Extracting dataroot files...');
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
        $this->add_to_log('...done');
        return $files;
    }

    /**
     * Prepare restore filedir
     *
     * @param string $filepath
     * @return string
     */
    public function prepare_restore_filedir(string $filepath): string {
        $this->add_to_log('Extracting filedir files...');
        $temppath = make_request_directory();
        $zippacker = new \zip_packer();
        $zippacker->extract_to_pathname($filepath, $temppath);

        $this->add_to_log('...done');
        return $temppath;
    }

    /**
     * Restore db
     *
     * @param string $zipfilepath
     * @return void
     */
    public function restore_db(string $zipfilepath) {
        global $DB;
        $tables = $this->dbstructure->get_backup_tables();
        $this->add_to_log('Restoring database ('.count($tables).' tables)...');
        $temppath = make_request_directory();
        $zippacker = new \zip_packer();

        $allfiles = array_column($zippacker->list_files($zipfilepath), 'pathname');

        $sequencesfilename = constants::FILE_SEQUENCE;
        $zippacker->extract_to_pathname($zipfilepath, $temppath, [$sequencesfilename]);
        $filepath = $temppath.DIRECTORY_SEPARATOR.$sequencesfilename;
        $sequences = json_decode(file_get_contents($filepath), true);
        unlink($filepath);

        foreach ($tables as $tablename => $table) {
            if ($altersql = $table->get_alter_sql($this->dbstructure->get_tables_actual()[$tablename] ?? null)) {
                try {
                    $DB->change_database_structure($altersql);
                    $this->add_to_log('- table '.$tablename.' structure is modified');
                } catch (\Throwable $t) {
                    $this->add_to_log('- table '.$tablename.' structure is modified, failed to apply modifications: '.
                        $t->getMessage(), constants::LOGLEVEL_WARNING);
                }
            }

            $DB->execute('TRUNCATE TABLE {'.$tablename.'}');

            $filesfortable = [];
            foreach ($allfiles as $filename) {
                if (preg_match('/^'.preg_quote($tablename, '/').'\\.([\\d]+)\\.json$/', $filename, $matches)) {
                    $filesfortable[(int)$matches[1]] = $filename;
                }
            }
            ksort($filesfortable);
            foreach ($filesfortable as $filename) {
                $zippacker->extract_to_pathname($zipfilepath, $temppath, [$filename]);
                $filepath = $temppath.DIRECTORY_SEPARATOR.$filename;
                $data = json_decode(file_get_contents($filepath), true);
                if ($data) {
                    $fields = array_shift($data);
                    foreach ($data as $row) {
                        try {
                            $DB->insert_record_raw($tablename, array_combine($fields, $row), false, true, true);
                        } catch (\Throwable $t) {
                            $this->add_to_log("- failed to insert record with id {$row['id']} into table $tablename: ".
                                $t->getMessage(), constants::LOGLEVEL_WARNING);
                        }
                    }
                }
                unlink($filepath);
            }
            if ($altersql = $table->get_fix_sequence_sql($sequences[$tablename] ?? 0)) {
                try {
                    $DB->change_database_structure($altersql);
                } catch (\Throwable $t) {
                    $this->add_to_log("- failed to change sequence for table $tablename: ".$t->getMessage(),
                        constants::LOGLEVEL_WARNING);
                }
            }
        }

        // Extract config overrides.
        $zippacker->extract_to_pathname($zipfilepath, $temppath, [constants::FILE_CONFIGOVERRIDE]);
        $filepath = $temppath.DIRECTORY_SEPARATOR.constants::FILE_CONFIGOVERRIDE;
        if (file_exists($filepath)) {
            $confs = json_decode(file_get_contents($filepath), true);
            foreach ($confs as $conf) {
                set_config($conf['name'], $conf['value'], $conf['plugin']);
            }
            unlink($filepath);
        }

        $this->add_to_log('...database restore completed');
    }

    /**
     * Restore dataroot
     *
     * @param array $files
     * @return void
     */
    public function restore_dataroot(array $files) {
        global $CFG;
        $this->add_to_log('Restoring datadir...');
        foreach ($files as $file => $path) {
            // TODO what if we can not delete some files?
            self::remove_recursively($CFG->dataroot.DIRECTORY_SEPARATOR.$file);
            if (file_exists($CFG->dataroot.DIRECTORY_SEPARATOR.$file)) {
                $this->add_to_log('- existing path '.$file.' in dataroot could not be removed',
                    constants::LOGLEVEL_WARNING);
                // TODO try to move files one by one.
            } else {
                rename($path, $CFG->dataroot.DIRECTORY_SEPARATOR.$file);
                $this->add_to_log("- added ".$file);
            }
        }

        self::remove_recursively($CFG->dataroot.DIRECTORY_SEPARATOR.'__vault_restore__');
        $this->add_to_log('...datadir restore completed');
    }

    /**
     * List all files in a directory recursively
     *
     * @param string $pathtodir
     * @param string $prefix
     * @return array array [filepathlocal => filepathfull] (filepathfull == $pathtodir.'/'/filepathlocal)
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
        $this->add_to_log('Restoring files to file storage...');
        $fs = get_file_storage();
        $files = self::dirlist_recursive($restoredir);
        foreach ($files as $subpath => $filepath) {
            $file = basename($filepath);
            if ($subpath !== substr($file, 0, 2) . DIRECTORY_SEPARATOR . substr($file, 2, 2) . DIRECTORY_SEPARATOR . $file) {
                // Integrity check.
                debugging("Skipping unrecognised file detected in the filedir archive: ".$subpath);
                continue;
            }
            try {
                $fs->add_file_to_pool($filepath, $file);
            } catch (\Throwable $t) {
                $this->add_to_log('- could not add file with contenthash '.$file.' to file system: '.$t->getMessage(),
                    constants::LOGLEVEL_WARNING);
            }
            unlink($filepath);
        }
        $this->add_to_log('...files restore completed');
    }

    /**
     * Post restore
     *
     * @return void
     */
    public function before_restore() {
        $this->add_to_log('Killing all sessions');
        \core\session\manager::kill_all_sessions();
        $this->add_to_log('...done');
    }

    /**
     * Post restore
     *
     * @return void
     */
    public function post_restore() {
        $this->add_to_log('Starting post-restore actions');
        $this->add_to_log('Purging all caches...');
        purge_all_caches();
        $this->add_to_log('...done');
        $this->add_to_log('Killing all sessions');
        \core\session\manager::kill_all_sessions();
        $this->add_to_log('...done');
    }

    /**
     * Remove directory recursively
     *
     * @param string $dir
     * @return void
     */
    public static function remove_recursively(string $dir) {
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
