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
use tool_vault\local\helpers\files_backup;
use tool_vault\local\models\backup_model;
use tool_vault\local\models\restore_model;
use tool_vault\local\operations\operation_base;
use tool_vault\local\xmldb\dbstructure;
use tool_vault\local\xmldb\dbtable;

/**
 * Perform site backup
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class site_backup extends operation_base {
    /** @var backup_model */
    protected $model;
    /** @var \tool_vault\local\checks\check_base[] */
    protected $prechecks = [];
    /** @var files_backup[] */
    protected $filesbackups = [];

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
                'lock',
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
     * @param array $params
     * @return operation_base
     */
    public static function schedule(array $params = []): operation_base {
        global $USER, $CFG;
        if ($records = backup_model::get_records([constants::STATUS_SCHEDULED])) {
            // Pressed button twice maybe?
            return new static(reset($records));
        }
        if (backup_model::get_records([constants::STATUS_INPROGRESS])) {
            throw new \moodle_exception('Another backup is in progress');
        }
        if (restore_model::get_records([constants::STATUS_INPROGRESS, constants::STATUS_SCHEDULED])) {
            throw new \moodle_exception('Another restore is in progress');
        }

        $model = new backup_model((object)[]);
        $description = $params['description'] ?? ($CFG->wwwroot.' by '.fullname($USER)); // TODO move default to the form.
        $model->set_status(constants::STATUS_SCHEDULED)->set_details([
            'usercreated' => $USER->id,
            'description' => substr($description, 0, constants::DESCRIPTION_MAX_LENGTH),
        ])->save();
        $model->add_log("Backup scheduled");
        return new static($model);
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
            'email' => $USER->email ?? '',
            'name' => $USER ? fullname($USER) : '',
        ];
    }

    /**
     * Start backup
     *
     * @param int $pid
     */
    public function start(int $pid) {
        if (!api::is_registered()) {
            throw new \moodle_exception('errorapikeynotvalid', 'tool_vault');
        }
        $this->model->set_pid_for_logging($pid);
        $model = $this->model;

        $params = [
            'description' => $model->get_details()['description'] ?? '',
        ];
        $backupkey = api::request_new_backup_key($params);
        $model
            ->set_backupkey($backupkey)
            ->set_status(constants::STATUS_INPROGRESS)
            ->set_details($params)
            ->save();
        $this->add_to_log('Backup started, backup key is '.$backupkey);
    }

    /**
     * Get the helper to backup files of the specified type
     *
     * @param string $filetype
     * @return files_backup
     */
    public function get_files_backup(string $filetype): files_backup {
        if (!array_key_exists($filetype, $this->filesbackups)) {
            $this->filesbackups[$filetype] = new files_backup($this, $filetype);
        }
        return $this->filesbackups[$filetype];
    }

    /**
     * Mark backup as failed
     *
     * @param \Throwable $t
     * @return void
     */
    public function mark_as_failed(\Throwable $t) {
        parent::mark_as_failed($t);
        try {
            api::update_backup($this->model->backupkey, ['faileddetails' => $t->getMessage()], 'failed');
        } catch (\Throwable $tapi) {
            // One of the reason for the failed backup - impossible to communicate with the API,
            // in which case this request will also fail.
            $this->add_to_log('Could not mark remote backup as failed: '.$tapi->getMessage(), constants::LOGLEVEL_ERROR);
        }
    }

    /**
     * List of backup pre-checks that are executed before each backup and also independently on Overview tab
     *
     * @return string[]
     */
    public static function backup_prechecks(): array {
        return [
            dbstatus::class,
            diskspace::class,
            configoverride::class,
        ];
    }

    /**
     * Prepare backup
     *
     * @return void
     */
    public function prepare() {
        /** @var check_base[] $prechecks */
        $prechecks = self::backup_prechecks();
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
        $this->export_db();
        $this->export_dataroot();
        $this->export_filedir();

        $totalsize = 0;
        foreach ($this->filesbackups as $filesbackup) {
            $totalsize += $filesbackup->get_uploaded_size();
        }

        $this->add_to_log('Total size of backup: '.display_size($totalsize));
        api::update_backup($this->model->backupkey, ['totalsize' => $totalsize], constants::STATUS_FINISHED);
        $this->model->set_status(constants::STATUS_FINISHED)->save();
        $this->add_to_log('Backup finished');

        // TODO notify user.
    }

    /**
     * Return backup key
     *
     * @return string|null
     */
    public function get_backup_key(): ?string {
        return $this->model->backupkey;
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
            return (!$rowscnt || !$chunkscnt) ? 0 : (int)($rowscnt / $chunkscnt);
        } else {
            return 0;
        }
    }

    /**
     * Exports one database table
     *
     * @param dbtable $table
     * @param string $dir path to temp directory to store files before they are added to archive
     */
    public function export_table_data(dbtable $table, string $dir) {
        global $DB;

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
            $this->get_files_backup(constants::FILENAME_DBDUMP)
                ->add_table_file($table->get_xmldb_table()->getName(), $filepath);
            if (!$chunksize) {
                break;
            }
        }

        if (($table->get_xmldb_table()->getName() === 'config') &&
                ($precheck = $this->prechecks[configoverride::get_name()] ?? null) &&
                ($confs = $precheck->get_config_overrides_for_backup())) {
            $this->get_files_backup(constants::FILENAME_DBDUMP)
                ->add_file_from_string(constants::FILE_CONFIGOVERRIDE, json_encode($confs));
        }

        $this->get_files_backup(constants::FILENAME_DBDUMP)->finish_table();
    }

    /**
     * Export db structure and metadata
     *
     * @param array $tablenames
     */
    protected function export_dbstructure(array $tablenames) {
        global $CFG;

        $this->get_files_backup(constants::FILENAME_DBSTRUCTURE)
            ->add_file($CFG->dirroot.'/lib/xmldb/xmldb.xsd', null, true, false)
            ->add_file_from_string(constants::FILE_STRUCTURE, $this->dbstructure->output($tablenames))
            ->add_file_from_string(constants::FILE_METADATA, json_encode($this->get_metadata()))
            ->finish();
    }

    /**
     * Exports the whole moodle database
     *
     */
    public function export_db() {

        $this->add_to_log('Exporting database:');
        $dir = make_request_directory();

        $structure = $this->get_db_structure();
        $tables = [];
        foreach ($structure->get_tables_actual() as $table => $tableobj) {
            if (!$this->is_table_skipped($tableobj)) {
                $tables[$table] = $tableobj;
            }
        }

        $this->export_dbstructure(array_keys($tables));

        foreach ($tables as $tableobj) {
            $this->export_table_data($tableobj, $dir);
        }

        $this->get_files_backup(constants::FILENAME_DBDUMP)
            ->add_file_from_string(constants::FILE_SEQUENCE,
              json_encode(array_intersect_key($structure->retrieve_sequences(), $tables)))
            ->finish();
        $this->add_to_log('Database export completed');
    }

    /**
     * Export $CFG->dataroot
     */
    public function export_dataroot() {
        global $CFG;
        $this->add_to_log('Exporting dataroot:');
        $filesbackup = $this->get_files_backup(constants::FILENAME_DATAROOT);
        $lastfile = $filesbackup->get_last_backedup_file();

        $pathstoexport = [];
        $handle = opendir($CFG->dataroot);
        while (($file = readdir($handle)) !== false) {
            if (!$this->is_dataroot_path_skipped($file) && ($lastfile === null || strcmp($file, $lastfile) > 0)) {
                $pathstoexport[] = $file;
            }
        }
        closedir($handle);
        if (empty($pathstoexport)) {
            $this->add_to_log('Nothing to export in dataroot');
            return;
        }

        asort($pathstoexport, SORT_STRING);

        foreach ($pathstoexport as $file) {
            $filesbackup->add_file($CFG->dataroot . DIRECTORY_SEPARATOR . $file, $file, true, false);
        }

        $filesbackup->finish();
        $this->add_to_log('Dataroot export completed');
    }

    /**
     * Export one file from filedir
     *
     * @param \stored_file $file
     * @param string $tempdir
     * @return bool
     */
    protected function export_one_file(\stored_file $file, string $tempdir): bool {
        global $CFG;
        $chash = $file->get_contenthash();
        $filename = substr($chash, 0, 2) .
            DIRECTORY_SEPARATOR . substr($chash, 2, 2) .
            DIRECTORY_SEPARATOR . $chash;
        $fullpath = $tempdir . DIRECTORY_SEPARATOR . $filename;
        if (!file_exists(dirname($fullpath))) {
            mkdir(dirname($fullpath), $CFG->directorypermissions, true);
        }
        if ($file->copy_content_to($fullpath)) {
            $this->get_files_backup(constants::FILENAME_FILEDIR)
                ->add_file($fullpath, $filename);
            return true;
        }
        $this->add_to_log('- can not back up file with contenthash ' . $chash . ' - skipping', constants::LOGLEVEL_WARNING);
        return false;
    }

    /**
     * Export filedir
     *
     * This function works with any file storage (local or remote)
     */
    public function export_filedir() {
        global $DB;

        $this->add_to_log('Exporting files:');
        $fs = get_file_storage();
        $dir = make_request_directory();
        $filesbackup = $this->get_files_backup(constants::FILENAME_FILEDIR);
        $lasthash = ($lastfile = $filesbackup->get_last_backedup_file()) ? basename($lastfile) : null;
        $cntexported = 0;

        do {
            $subquery = ($lasthash ? ' WHERE contenthash > ?' : '');
            $sql = 'SELECT ' . self::instance_sql_fields('f', 'r') . "
                FROM (SELECT contenthash, min(id) AS id
                    FROM {files}
                    $subquery
                    GROUP BY contenthash
                ) filehash
                JOIN {files} f ON f.id = filehash.id
                LEFT JOIN {files_reference} r ON f.referencefileid = r.id
                ORDER BY filehash.contenthash";
            $records = $DB->get_records_sql($sql, [$lasthash], 0, constants::FILES_BATCH);
            foreach ($records as $filerecord) {
                $cntexported += (int)$this->export_one_file($fs->get_file_instance($filerecord), $dir);
                $lasthash = $filerecord->contenthash;
            }
        } while (count($records) >= constants::FILES_BATCH - 1);

        $filesbackup->finish();
        site_restore::remove_recursively($dir);
        $this->add_to_log('Files export completed, '.$cntexported.' files exported');
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
}
