<?php
// This file is part of plugin tool_vault - https://lmsvault.io
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

use tool_vault\local\checks\backup_precheck_failed;
use tool_vault\local\checks\check_base;
use tool_vault\local\checks\configoverride;
use tool_vault\local\checks\dbstatus;
use tool_vault\local\checks\diskspace;
use tool_vault\local\checks\environ;
use tool_vault\local\helpers\files_backup;
use tool_vault\local\helpers\plugindata;
use tool_vault\local\helpers\siteinfo;
use tool_vault\local\helpers\tempfiles;
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
     * Schedules new backup
     *
     * @param array $params
     * @return operation_base
     */
    public static function schedule(array $params = []): operation_base {
        global $USER;
        if (!(defined('CLI_SCRIPT') && CLI_SCRIPT)
                && $records = backup_model::get_records([constants::STATUS_SCHEDULED])) {
            // Pressed button twice maybe?
            return new static(reset($records));
        }
        if (backup_model::get_records([constants::STATUS_INPROGRESS, constants::STATUS_SCHEDULED])) {
            throw new \moodle_exception('error_anotherbackupisinprogress', 'tool_vault');
        }
        if (restore_model::get_records([constants::STATUS_INPROGRESS, constants::STATUS_SCHEDULED])) {
            throw new \moodle_exception('error_anotherrestoreisinprogress', 'tool_vault');
        }

        $model = new backup_model((object)[]);
        $encryptionkey = api::prepare_encryption_key($params['passphrase'] ?? '');
        $model->set_status(constants::STATUS_SCHEDULED)->set_details([
            'usercreated' => $USER->id,
            'description' => substr($params['description'] ?? '', 0, constants::DESCRIPTION_MAX_LENGTH),
            'bucket' => $params['bucket'] ?? '',
            'expiredays' => $params['expiredays'] ?? '',
            'encryptionkey' => $encryptionkey,
            'encrypted' => (bool)strlen($encryptionkey),
            'fullname' => $USER ? fullname($USER) : '',
            'email' => $USER->email ?? '',
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
        $precheck = $this->prechecks[diskspace::get_name()] ?? null;
        $excludedplugins = siteinfo::get_excluded_plugins_backup();
        $pluginlist = array_diff_key(siteinfo::get_plugins_list_full(), array_fill_keys($excludedplugins, true));
        return [
            // TODO - what other metadata do we want - languages, estimated size?
            'wwwroot' => $CFG->wwwroot,
            'dbengine' => $DB->get_dbfamily(),
            'version' => $CFG->version,
            'branch' => $CFG->branch,
            'release' => $CFG->release,
            'tool_vault_version' => get_config('tool_vault', 'version'),
            'email' => $USER->email ?? '',
            'name' => $USER ? fullname($USER) : '',
            'userid' => ($USER && $USER->id) ? $USER->id : (get_admin()->id),
            'dbtotalsize' => $precheck ? $precheck->get_model()->get_details()['dbtotalsize'] : 0,
            'plugins' => $pluginlist,
        ];
    }

    /**
     * Start backup
     *
     * @param int $pid
     */
    public function start(int $pid) {
        if (!api::is_registered()) {
            throw new \moodle_exception('error_apikeynotvalid', 'tool_vault');
        }
        $this->model->set_pid_for_logging($pid);
        $model = $this->model;

        $params = [
            'description' => $model->get_details()['description'] ?? '',
            'encrypted' => !empty($model->get_details()['encrypted']),
            'bucket' => $model->get_details()['bucket'] ?? '',
            'expiredays' => (int)($model->get_details()['expiredays'] ?? 0),
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
        if (!$this->model->backupkey) {
            return;
        }
        try {
            $faileddetails = $this->get_error_message_for_server($t);
            api::update_backup($this->model->backupkey, ['faileddetails' => $faileddetails], constants::STATUS_FAILED);
        } catch (\Throwable $tapi) {
            // One of the reason for the failed backup - impossible to communicate with the API,
            // in which case this request will also fail.
            $this->add_to_log_from_exception_handler('Could not mark remote backup as failed: '.
                $tapi->getMessage(), constants::LOGLEVEL_WARNING);
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
            environ::class,
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
                if ($chk->warning()) {
                    $this->add_to_log('...Warning: ' . strip_tags($chk->get_status_message()), constants::LOGLEVEL_WARNING);
                } else {
                    $this->add_to_log('...OK');
                }
            } else if ($chk) {
                throw new backup_precheck_failed($chk);
            } else {
                throw new \moodle_exception('error_unabletorunprecheck', 'tool_vault',
                    '', $classname::get_display_name());
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
            throw new \moodle_exception('error_backupinprogressnotfound', 'tool_vault');
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
        $this->model
            ->set_status(constants::STATUS_FINISHED)
            ->set_details(['encryptionkey' => ''])
            ->save();
        $this->add_to_log('Backup finished');

        // TODO notify user.

        // Reset remote backups caches.
        api::store_config('cachedremotebackupstime', null);
        api::store_config('cachedremotebackups', null);

        tempfiles::cleanup();
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
        $dbgen = $DB->get_manager()->generator;

        $fields = array_map(function(\xmldb_field $f) {
            return $f->getName();
        }, $table->get_xmldb_table()->getFields());
        $quotedfields = array_map(function($f) use ($dbgen) {
            return $dbgen->getEncQuoted($f);
        }, $fields);
        $sortby = in_array('id', $fields) ? 'id' : reset($quotedfields);
        $fieldslist = join(',', $quotedfields);

        $chunksize = $this->get_chunk_size($table->get_xmldb_table()->getName());
        $lastvalue = null;
        for ($cnt = 0; true; $cnt++) {
            [$sql, $params] = plugindata::get_sql_for_plugins_data_in_table($table->get_xmldb_table()->getName(),
                siteinfo::get_excluded_plugins_backup(), true);
            if ($lastvalue !== null) {
                $sql .= (strlen($sql) ? ' AND ' : '') . $sortby. ' > :lastvalue';
            }
            // Mdlcode-disable-next-line cannot-parse-db-tablename.
            $rs = $DB->get_recordset_select($table->get_xmldb_table()->getName(), $sql, $params + ['lastvalue' => $lastvalue],
                $sortby, $fieldslist, 0, $chunksize);
            $hasrows = $rs->valid();
            if ($cnt && !$hasrows) {
                $rs->close();
                break;
            }
            $filename = $table->get_xmldb_table()->getName().'.'.$cnt.'.json';
            $filepath = $dir.DIRECTORY_SEPARATOR.$filename;
            $fp = fopen($filepath, 'w');
            fwrite($fp, "[\n" . json_encode($fields));
            if ($hasrows) {
                foreach ($rs as $record) {
                    fwrite($fp, ",\n".json_encode(array_values((array)$record)));
                    $lastvalue = $record->$sortby;
                }
            }
            $rs->close();
            fwrite($fp, "\n]");
            $this->get_files_backup(constants::FILENAME_DBDUMP)
                ->add_table_file($table->get_xmldb_table()->getName(), $filepath);
            if (!$chunksize || !$hasrows) {
                break;
            }
        }

        $this->get_files_backup(constants::FILENAME_DBDUMP)->finish_table();
    }

    /**
     * Export db structure and metadata
     *
     * @param array $tablenames
     */
    public function export_dbstructure(array $tablenames) {
        global $CFG;

        $structure = $this->get_db_structure();
        $confprecheck = $this->prechecks[configoverride::get_name()] ?? null;
        $confs = $confprecheck ? $confprecheck->get_config_overrides_for_backup() : [];

        $this->get_files_backup(constants::FILENAME_DBSTRUCTURE)
            ->add_file($CFG->dirroot.'/lib/xmldb/xmldb.xsd', null, true, false)
            ->add_file_from_string(constants::FILE_STRUCTURE, $structure->output($tablenames))
            ->add_file_from_string(constants::FILE_METADATA, json_encode($this->get_metadata()))
            ->add_file_from_string(constants::FILE_SEQUENCE,
                json_encode(array_intersect_key($structure->retrieve_sequences(), array_combine($tablenames, $tablenames))))
            ->add_file_from_string(constants::FILE_CONFIGOVERRIDE, json_encode($confs))
            ->finish();
    }

    /**
     * Exports the whole moodle database
     *
     */
    public function export_db() {

        $this->add_to_log('Starting database backup');
        $dir = tempfiles::make_temp_dir('dbbackup-');

        $structure = $this->get_db_structure();
        $tables = [];
        foreach ($structure->get_tables_actual() as $table => $tableobj) {
            $deftable = $structure->find_table_definition($table);
            if (!siteinfo::is_table_excluded_from_backup($table, $deftable)) {
                $tables[$table] = $tableobj;
            }
        }

        foreach ($tables as $tableobj) {
            $this->export_table_data($tableobj, $dir);
        }
        $this->get_files_backup(constants::FILENAME_DBDUMP)->finish();

        $this->export_dbstructure(array_keys($tables));

        $this->add_to_log('Finished database backup');
        tempfiles::remove_temp_dir($dir);
    }

    /**
     * Export $CFG->dataroot
     */
    public function export_dataroot() {
        global $CFG;
        $this->add_to_log('Starting dataroot backup');
        $filesbackup = $this->get_files_backup(constants::FILENAME_DATAROOT);
        $lastfile = $filesbackup->get_last_backedup_file();

        $pathstoexport = [];
        $handle = opendir($CFG->dataroot);
        while (($file = readdir($handle)) !== false) {
            if (!siteinfo::is_dataroot_path_skipped_backup($file)
                    && $file !== '.' && $file !== '..'
                    && ($lastfile === null || strcmp($file, $lastfile) > 0)) {
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
        $this->add_to_log('Finished dataroot backup');
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
            $ext = pathinfo($file->get_filename(), PATHINFO_EXTENSION);
            $isarchive = in_array(strtolower($ext), constants::COMPRESSED_FILE_EXTENSIONS);
            $this->get_files_backup(constants::FILENAME_FILEDIR)
                ->add_file($fullpath, $filename, true, true, $isarchive);
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

        $this->add_to_log('Starting files backup');
        $fs = get_file_storage();
        $dir = tempfiles::make_temp_dir('mainfilesbackup-');
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
        tempfiles::remove_temp_dir($dir);
        $this->add_to_log('Finished files backup, '.$cntexported.' files exported');
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
        $filefields = ['contenthash', 'pathnamehash', 'contextid', 'component', 'filearea',
            'itemid', 'filepath', 'filename', 'userid', 'filesize', 'mimetype', 'status', 'source',
            'author', 'license', 'timecreated', 'timemodified', 'sortorder', 'referencefileid', ];

        $referencefields = ['repositoryid' => 'repositoryid',
            'reference' => 'reference',
            'lastsync' => 'referencelastsync', ];

        // Id is specifically named to prevent overlaping between the two tables.
        $fields = [];
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
