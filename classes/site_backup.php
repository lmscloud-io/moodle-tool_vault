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
use tool_vault\local\checks\configoverride;
use tool_vault\local\checks\dbstatus;
use tool_vault\local\checks\diskspace;
use tool_vault\local\logger;
use tool_vault\local\models\backup_model;
use tool_vault\local\xmldb\dbstructure;
use tool_vault\local\xmldb\dbtable;
use tool_vault\task\backup_task;

/**
 * Perform site backup
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class site_backup implements logger {
    /** @var backup_model */
    protected $model;
    /** @var \tool_vault\local\checks\check_base[] */
    protected $prechecks = [];

    /**
     * Constructor
     *
     * @param backup_model $model
     */
    public function __construct(backup_model $model) {
        $this->model = $model;
    }

    /**
     * Should the table be skipped from the backup
     *
     * @param dbtable $table
     * @return bool
     */
    public static function is_table_skipped(dbtable $table): bool {
        return preg_match('/^tool_vault[$_]/', strtolower($table->get_xmldb_table()->getName()));
    }

    /**
     * Should the dataroot subfolder/file be skipped from the backup
     *
     * @param string $path relative path under $CFG->dataroot
     * @return bool
     */
    public static function is_dataroot_path_skipped(string $path): bool {
        $defaultexcluded = in_array($path, [
                'filedir', // Files are retrieved separately.
                'cache',
                'localcache',
                'temp',
                'sessions',
                'trashdir',
                // For phpunit.
                'phpunit',
                'phpunittestdir.txt',
                'originaldatafiles.json',
                // Vault temp dir.
                '__vault_restore__'
            ]) || preg_match('/^\\./', $path);
        if ($defaultexcluded) {
            return true;
        }
        $paths = preg_split('/[\\s,]/', api::get_config('backupexcludedataroot'), -1, PREG_SPLIT_NO_EMPTY);
        return in_array($path, $paths);
    }

    /**
     * Schedules new backup
     *
     * @return void
     */
    public static function schedule_backup() {
        global $USER;
        if (backup_model::get_records([constants::STATUS_SCHEDULED])) {
            // Pressed button twice maybe?
            return;
        }
        if (backup_model::get_records([constants::STATUS_INPROGRESS])) {
            throw new \moodle_exception('Another backup is in progress');
        }

        $model = new backup_model((object)[]);
        $model->set_status(constants::STATUS_SCHEDULED)->set_details(['usercreated' => $USER->id])->save();
        $model->add_log("Backup scheduled");
        backup_task::schedule();
    }

    /**
     * Backup metadata
     *
     * @return array
     */
    protected function get_metadata() {
        global $CFG, $USER, $DB;
        return [
            // TODO - what other metadata do we want - languages, installed plugins, estimated size?
            'wwwroot' => $CFG->wwwroot,
            'dbengine' => $DB->get_dbfamily(),
            'version' => $CFG->version,
            'branch' => $CFG->branch,
            'tool_vault_version' => get_config('tool_vault', 'version'),
            'email' => $USER->email,
            'name' => fullname($USER),
        ];
    }

    /**
     * Start backup
     *
     * @param int $pid
     * @return self
     */
    public static function start_backup(int $pid): self {
        global $CFG, $USER, $DB;
        if (!api::is_registered()) {
            throw new \moodle_exception('API key not found');
        }
        $model = backup_model::get_scheduled_backup();
        if (!$model) {
            throw new \moodle_exception('No scheduled backup');
        }
        $model->set_pid_for_logging($pid);
        $instance = new static($model);

        $params = [
            'description' => $CFG->wwwroot.' by '.fullname($USER), // TODO from the form.
            'version' => $CFG->version,
            'branch' => $CFG->branch,
        ];
        try {
            $backupkey = api::request_new_backup_key($params);
        } catch (\Throwable $t) {
            // API rejected - above limit/quota. TODO store details of failure? how to notify user?
            $model
                ->set_status(constants::STATUS_FAILEDTOSTART)
                ->set_details($params)
                ->save();
            $instance->add_to_log('Failed to start the backup with the cloud service: '.$t->getMessage(),
                constants::LOGLEVEL_ERROR);
            throw $t;
        }
        $model
            ->set_backupkey($backupkey)
            ->set_status(constants::STATUS_INPROGRESS)
            ->set_details($params)
            ->save();
        $instance->add_to_log('Backup started, backup key is '.$backupkey);
        return $instance;
    }

    /**
     * Mark backup as failed
     *
     * @param \Throwable $t
     * @return void
     */
    public function mark_as_failed(\Throwable $t) {
        $this->model->set_status(constants::STATUS_FAILED)->save();
        $this->add_to_log('Backup failed: '.$t->getMessage(), constants::LOGLEVEL_ERROR);
        try {
            api::update_backup($this->model->backupkey, ['faileddetails' => $t->getMessage()], 'failed');
        } catch (\Throwable $tapi) {
            // One of the reason for the failed backup - impossible to communicate with the API,
            // in which case this request will also fail.
            $this->add_to_log('Could not mark remote backup as failed: '.$tapi->getMessage(), constants::LOGLEVEL_ERROR);
        }
    }

    /**
     * Prepare backup
     *
     * @return void
     */
    public function prepare() {
        /** @var check_base[] $prechecks */
        $prechecks = [
            dbstatus::class,
            diskspace::class,
            configoverride::class,
        ];
        foreach ($prechecks as $classname) {
            $this->add_to_log('Backup pre-check: '.$classname::get_display_name().'...');
            if (($chk = $classname::create_and_run($this->model)) && $chk->success()) {
                $this->prechecks[$chk->get_name()] = $chk;
                $this->add_to_log('...OK');
            } else {
                throw new \moodle_exception('...'.$classname::get_display_name().' failed');
            }
        }
    }

    /**
     * Execute backup
     *
     * @return void
     */
    public function execute() {
        if (!$this->model || $this->model->status !== constants::STATUS_INPROGRESS) {
            throw new \moodle_exception('Backup in progress not found');
        }

        $this->prepare();
        $totalsize = 0;

        $this->add_to_log('Exporting database...');
        $filepaths = $this->export_db();
        $this->add_to_log('...done');
        foreach ($filepaths as $filepath) {
            $totalsize += api::upload_backup_file($this->model->backupkey, $filepath, 'application/zip', $this);
        }

        $this->add_to_log('Exporting dataroot...');
        $filepath = $this->export_dataroot();
        $this->add_to_log('...done');
        $totalsize += api::upload_backup_file($this->model->backupkey, $filepath, 'application/zip', $this);

        $this->add_to_log('Exporting files...');
        $filepaths = $this->export_filedir();
        $this->add_to_log('...done');
        foreach ($filepaths as $filepath) {
            $totalsize += api::upload_backup_file($this->model->backupkey, $filepath, 'application/zip', $this);
        }

        $this->add_to_log('Total size of backup: '.display_size($totalsize));
        api::update_backup($this->model->backupkey, ['totalsize' => $totalsize], constants::STATUS_FINISHED);
        $this->model->set_status(constants::STATUS_FINISHED)->save();
        $this->add_to_log('Backup finished');

        // TODO notify user.
    }

    /**
     * Get total file size of several files
     *
     * @param array $filepaths
     * @return int
     */
    protected function get_total_files_size(array $filepaths) {
        $size3 = 0;
        foreach ($filepaths as $filepath) {
            $size3 += filesize($filepath);
        }
        return $size3;
    }

    /** @var dbstructure */
    protected $dbstructure = null;

    /**
     * Retrns DB structure
     *
     * @return dbstructure
     */
    public function get_db_structure(): dbstructure {
        if ($this->dbstructure === null) {
            $this->dbstructure = dbstructure::load();
        }
        return $this->dbstructure;
    }

    /**
     * Helper function, how many rows should we add to one chunk of the db table export
     *
     * @param string $tablename
     * @return int
     */
    protected function get_chunk_size(string $tablename) {
        /** @var diskspace $precheck */
        $precheck = $this->prechecks[diskspace::get_name()] ?? null;
        if ($precheck) {
            [$rowscnt, $size] = $precheck->get_table_size($tablename);
            $chunkscnt = ceil($size / constants::DBFILE_SIZE);
            $res = (!$rowscnt || !$chunkscnt) ? 0 : (int)($rowscnt / $chunkscnt);
            return (!$rowscnt || !$chunkscnt) ? 0 : (int)($rowscnt / $chunkscnt);
        } else {
            return 0;
        }
    }

    /**
     * Exports one database table
     *
     * @param dbtable $table
     * @param \zip_archive $ziparchive
     * @param string $dir
     * @return array list of added filepaths
     */
    public function export_table_data(dbtable $table, \zip_archive $ziparchive, string $dir): array {
        global $DB;
        $filepaths = [];

        $fields = array_map(function(\xmldb_field $f) {
            return $f->getName();
        }, $table->get_xmldb_table()->getFields());
        $sortby = in_array('id', $fields) ? 'id' : reset($fields);
        $fieldslist = join(',', $fields);

        $chunksize = $this->get_chunk_size($table->get_xmldb_table()->getName());
        $lastvalue = null;
        for ($cnt = 0; true; $cnt++) {
            $rs = $DB->get_recordset_select($table->get_xmldb_table()->getName(),
                ($lastvalue !== null) ? $sortby. ' > ?' : '', [$lastvalue],
                $sortby, $fieldslist, 0, $chunksize);
            if (!$rs->valid()) {
                $rs->close();
                break;
            }
            $filename = $table->get_xmldb_table()->getName().'.'.$cnt.'.json';
            $filepath = $dir.DIRECTORY_SEPARATOR.$filename;
            $fp = fopen($filepath, 'w');
            fwrite($fp, "[\n" . json_encode($fields));
            foreach ($rs as $record) {
                fwrite($fp, ",\n".json_encode(array_values((array)$record)));
                $lastvalue = $record->$sortby;
            }
            $rs->close();
            fwrite($fp, "\n]");
            $ziparchive->add_file_from_pathname($filename, $filepath);
            $filepaths[] = $filepath;
            if (!$chunksize) {
                break;
            }
        }
        return $filepaths;
    }

    /**
     * Export db structure and metadata
     *
     * @param array $tablenames
     * @param string $zipdir
     * @return string path to the zip archive
     */
    protected function export_dbstructure(array $tablenames, string $zipdir): string {
        global $CFG;

        $zipfilepathstruct = $zipdir.DIRECTORY_SEPARATOR.constants::FILENAME_DBSTRUCTURE.'.zip';
        $ziparchivestruct = new \zip_archive();
        if ($ziparchivestruct->open($zipfilepathstruct, \file_archive::CREATE)) {
            $ziparchivestruct->add_file_from_pathname('xmldb.xsd', $CFG->dirroot.'/lib/xmldb/xmldb.xsd');
            $ziparchivestruct->add_file_from_string(constants::FILE_STRUCTURE, $this->dbstructure->output($tablenames));
            $ziparchivestruct->add_file_from_string(constants::FILE_METADATA, json_encode($this->get_metadata()));
            // TODO add backup metadata.
            $ziparchivestruct->close();
        } else {
            // TODO?
            throw new \moodle_exception('Can not create ZIP file');
        }

        return $zipfilepathstruct;
    }

    /**
     * Exports the whole moodle database
     *
     * @return array paths to the zip file with the export
     */
    public function export_db(): array {

        $dir = make_request_directory();
        $zipdir = make_request_directory();

        $structure = $this->get_db_structure();
        $tables = [];
        foreach ($structure->get_tables_actual() as $table => $tableobj) {
            if (!$this->is_table_skipped($tableobj)) {
                $tables[$table] = $tableobj;
            }
        }
        $zipfilepathstruct = $this->export_dbstructure(array_keys($tables), $zipdir);

        $zipfilepath = $zipdir.DIRECTORY_SEPARATOR.constants::FILENAME_DBDUMP.'.zip';
        $ziparchive = new \zip_archive();
        if (!$ziparchive->open($zipfilepath, \file_archive::CREATE)) {
            // TODO?
            throw new \moodle_exception('Can not create ZIP file');
        }

        foreach ($tables as $tableobj) {
            $this->export_table_data($tableobj, $ziparchive, $dir);
        }

        $ziparchive->add_file_from_string(constants::FILE_SEQUENCE,
            json_encode(array_intersect_key($structure->retrieve_sequences(), $tables)));

        if ($precheck = $this->prechecks[configoverride::get_name()] ?? null) {
            if ($confs = $precheck->get_config_overrides_for_backup()) {
                $ziparchive->add_file_from_string(constants::FILE_CONFIGOVERRIDE, json_encode($confs));
            }
        }

        $ziparchive->close();
        site_restore::remove_recursively($dir);

        return [$zipfilepathstruct, $zipfilepath];
    }

    /**
     * Export $CFG->dataroot
     *
     * @return string path to the zip file with the export
     */
    public function export_dataroot() {
        global $CFG;
        $exportfilename = constants::FILENAME_DATAROOT . '.zip';
        $handle = opendir($CFG->dataroot);
        $files = [];
        while (($file = readdir($handle)) !== false) {
            if (!$this->is_dataroot_path_skipped($file)) {
                $files[$file] = $CFG->dataroot . DIRECTORY_SEPARATOR . $file;
            }
        }
        closedir($handle);

        $zipfilepath = make_request_directory().DIRECTORY_SEPARATOR.$exportfilename;
        $zippacker = new \zip_packer();
        // TODO use progress somehow?
        if (!$zippacker->archive_to_pathname($files, $zipfilepath)) {
            // TODO?
            throw new \moodle_exception('Failed to create dataroot archive');
        }
        return $zipfilepath;
    }

    /**
     * Export filedir
     *
     * This function works with any file storage (local or remote)
     *
     * @return string[]
     */
    public function export_filedir(): array {
        global $DB, $CFG;
        $fs = get_file_storage();
        $dir = make_request_directory();
        $zippacker = new \zip_packer();

        $records = $DB->get_records_sql('SELECT '.self::instance_sql_fields('f', 'r').'
            FROM (SELECT contenthash, min(id) AS id
                from {files}
                GROUP BY contenthash) filehash
            JOIN {files} f ON f.id = filehash.id
            LEFT JOIN {files_reference} r
                       ON f.referencefileid = r.id',
            []);
        $toarchive = [];
        foreach ($records as $filerecord) {
            $file = $fs->get_file_instance($filerecord);
            $chash = $filerecord->contenthash;
            $filename = substr($chash, 0, 2) .
                DIRECTORY_SEPARATOR . substr($chash, 2, 2) .
                DIRECTORY_SEPARATOR . $chash;
            $fullpath = $dir . DIRECTORY_SEPARATOR . $filename;
            if (!file_exists(dirname($fullpath))) {
                mkdir(dirname($fullpath), $CFG->directorypermissions, true);
            }
            if (!$file->copy_content_to($fullpath)) {
                $this->add_to_log('- can not back up file with contenthash '.$chash.' - skipping', constants::LOGLEVEL_WARNING);
                continue;
            }
            $toarchive[$filename] = $fullpath;
        }

        $exportfilename = constants::FILENAME_FILEDIR . '.zip';
        $zipfilepath = make_request_directory() .
            DIRECTORY_SEPARATOR . $exportfilename;
        if (!$zippacker->archive_to_pathname($toarchive, $zipfilepath)) {
            // TODO?
            throw new \moodle_exception('Failed to create filedir archive');
        }
        foreach ($toarchive as $filepath) {
            unlink($filepath);
        }

        return [$zipfilepath];
    }

    /**
     * Get the sql formated fields for a file instance to be created from a
     * {files} and {files_refernece} join.
     *
     * @param string $filesprefix the table prefix for the {files} table
     * @param string $filesreferenceprefix the table prefix for the {files_reference} table
     * @return string the sql to go after a SELECT
     */
    private static function instance_sql_fields($filesprefix, $filesreferenceprefix) {
        // Note, these fieldnames MUST NOT overlap between the two tables,
        // else problems like MDL-33172 occur.
        $filefields = array('contenthash', 'pathnamehash', 'contextid', 'component', 'filearea',
            'itemid', 'filepath', 'filename', 'userid', 'filesize', 'mimetype', 'status', 'source',
            'author', 'license', 'timecreated', 'timemodified', 'sortorder', 'referencefileid');

        $referencefields = array('repositoryid' => 'repositoryid',
            'reference' => 'reference',
            'lastsync' => 'referencelastsync');

        // Id is specifically named to prevent overlaping between the two tables.
        $fields = array();
        $fields[] = $filesprefix.'.id AS id';
        foreach ($filefields as $field) {
            $fields[] = "{$filesprefix}.{$field}";
        }

        foreach ($referencefields as $field => $alias) {
            $fields[] = "{$filesreferenceprefix}.{$field} AS {$alias}";
        }

        return implode(', ', $fields);
    }

    /**
     * Log action
     *
     * @param string $message
     * @param string $loglevel
     * @return void
     */
    public function add_to_log(string $message, string $loglevel = constants::LOGLEVEL_INFO) {
        if ($this->model && $this->model->id) {
            $logrecord = $this->model->add_log($message, $loglevel);
            if (!(defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
                mtrace($this->model->format_log_line($logrecord, false));
            }
        }
    }
}
