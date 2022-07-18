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

use tool_vault\local\checks\base;
use tool_vault\local\checks\configoverride;
use tool_vault\local\checks\dbstatus;
use tool_vault\local\checks\diskspace;
use tool_vault\local\models\backup;
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
class site_backup {
    /** @var backup */
    protected $backup;
    /** @var \tool_vault\local\checks\base[] */
    protected $prechecks = [];

    /**
     * Constructor
     *
     * @param backup $backup
     */
    public function __construct(backup $backup) {
        $this->backup = $backup;
    }

    /**
     * Should the table be skipped from the backup
     *
     * @param dbtable $table
     * @return bool
     */
    public function is_table_skipped(dbtable $table): bool {
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
        if (backup::get_records([constants::STATUS_SCHEDULED])) {
            // Pressed button twice maybe?
            return;
        }
        if (backup::get_records([constants::STATUS_INPROGRESS])) {
            throw new \moodle_exception('Another backup is in progress');
        }

        $backup = new backup((object)[]);
        $backup->set_status(constants::STATUS_SCHEDULED)->set_details(['usercreated' => $USER->id])->save();
        $backup->add_log("Backup scheduled");
        backup_task::schedule();
    }

    /**
     * Start backup
     *
     * @return self
     */
    public static function start_backup(): self {
        global $CFG, $USER, $DB;
        if (!api::is_registered()) {
            throw new \moodle_exception('API key not found');
        }
        $backup = backup::get_scheduled_backup();
        if (!$backup) {
            throw new \moodle_exception('No scheduled backup');
        }

        $params = [
            // TODO - what other metadata do we want - languages, installed plugins, estimated size?
            'wwwroot' => $CFG->wwwroot,
            'dbengine' => $DB->get_dbfamily(),
            'version' => $CFG->version,
            'branch' => $CFG->branch,
            'tool_vault_version' => get_config('tool_vault', 'version'),
            'email' => $USER->email,
            'name' => fullname($USER),
        ];
        $res = api::api_call('backups', 'PUT', $params);
        if (!empty($res['backupkey'])) {
            $backup
                ->set_backupkey($res['backupkey'])
                ->set_status(constants::STATUS_INPROGRESS)
                ->set_details($params)
                ->save();
            $backup->add_log('Backup started, backup key is '.$res['backupkey']);
        } else {
            // API rejected - aobve limit/quota. TODO store details of failure? how to notify user?
            $backup
                ->set_status(constants::STATUS_FAILEDTOSTART)
                ->set_details($params)
                ->save();
            $backup->add_log('Failed to start the backup with the cloud service');
            // @codingStandardsIgnoreLine
            throw new \coding_exception('Unknown response: '.print_r($res, true));
        }
        $instance = new static($backup);
        return $instance;
    }

    /**
     * Mark backup as failed
     *
     * @param \Throwable $t
     * @return void
     */
    public function mark_as_failed(\Throwable $t) {
        $this->backup->set_status(constants::STATUS_FAILED)->save();
        $this->backup->add_log('Backup failed: '.$t->getMessage());
    }

    /**
     * Execute backup
     *
     * @return void
     */
    public function execute() {
        $backup = $this->backup;
        if (!$backup || $backup->status !== constants::STATUS_INPROGRESS) {
            throw new \moodle_exception('Backup in progress not found');
        }

        /** @var base[] $prechecks */
        $prechecks = [
            dbstatus::class,
            diskspace::class,
            configoverride::class,
        ];
        foreach ($prechecks as $classname) {
            $backup->add_log('Checking: '.$classname::get_display_name().'...');
            if (($chk = dbstatus::create_and_run()) && $chk->success()) {
                $this->prechecks[$chk->get_name()] = $chk;
                $backup->add_log('...OK');
            } else {
                throw new \moodle_exception('...'.$classname::get_display_name().' failed');
            }
        }

        $backup->add_log('Exporting database...');
        $filepath = $this->export_db(constants::FILENAME_DBDUMP . '.zip');
        $size1 = filesize($filepath);
        $backup->add_log('...done');
        $backup->add_log('Uploading database backup ('.display_size($size1).')...');
        api::upload_backup_file($this->backup->backupkey, $filepath, 'application/zip');
        $backup->add_log('...done');

        $backup->add_log('Exporting dataroot...');
        $filepath = $this->export_dataroot(constants::FILENAME_DATAROOT . '.zip');
        $size2 = filesize($filepath);
        $backup->add_log('...done');
        $backup->add_log('Uploading dataroot backup ('.display_size($size2).')...');
        api::upload_backup_file($this->backup->backupkey, $filepath, 'application/zip');
        $backup->add_log('...done');

        $backup->add_log('Exporting files...');
        $filepaths = $this->export_filedir();
        $size3 = 0;
        foreach ($filepaths as $filepath) {
            $size3 += filesize($filepath);
        }
        $backup->add_log('...done');
        $backup->add_log('Uploading files backup ('.display_size($size3).')...');
        foreach ($filepaths as $filepath) {
            api::upload_backup_file($this->backup->backupkey, $filepath, 'application/zip');
        }
        $backup->add_log('...done');

        $backup->add_log('Total size of backup: '.display_size($size1 + $size2 + $size3));
        api::api_call("backups/{$this->backup->backupkey}", 'PATCH', ['status' => 'finished']);
        $backup->set_status(constants::STATUS_FINISHED)->save();
        $backup->add_log('Backup finished');

        // TODO notify user, register in our config.
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
     * Exports one database table
     *
     * @param dbtable $table
     * @param string $filepath
     * @return void
     */
    public function export_table_data(dbtable $table, string $filepath) {
        global $DB;
        $fields = array_map(function(\xmldb_field $f) {
            return $f->getName();
        }, $table->get_xmldb_table()->getFields());
        $sortby = in_array('id', $fields) ? 'id' : reset($fields);
        $fieldslist = join(',', $fields);

        $fp = fopen($filepath, 'w');
        fwrite($fp, "[\n" . json_encode($fields));
        $rs = $DB->get_recordset($table->get_xmldb_table()->getName(), [], $sortby, $fieldslist);
        foreach ($rs as $record) {
            fwrite($fp, ",\n".json_encode(array_values((array)$record)));
        }
        $rs->close();
        fwrite($fp, "\n]");
        fclose($fp);
    }

    /**
     * Exports the whole moodle database
     *
     * @param string $exportfilename
     * @return string path to the zip file with the export
     */
    public function export_db(string $exportfilename) {
        global $CFG;
        $dir = make_request_directory();
        $structure = $this->get_db_structure();
        $tables = [];
        foreach ($structure->get_tables_actual() as $table => $tableobj) {
            if (!$this->is_table_skipped($tableobj)) {
                $filepath = $dir.DIRECTORY_SEPARATOR.$table.'.json';
                $this->export_table_data($tableobj, $filepath);
                $tables[$table] = $tableobj;
            }
        }
        $structurefilename = constants::FILE_STRUCTURE;
        file_put_contents($dir.DIRECTORY_SEPARATOR.$structurefilename,
            $this->dbstructure->output(array_keys($tables)));

        $sequencesfilename = constants::FILE_SEQUENCE;
        file_put_contents($dir.DIRECTORY_SEPARATOR.$sequencesfilename,
            json_encode(array_intersect_key($structure->retrieve_sequences(), $tables)));

        $zipfilepath = $dir.DIRECTORY_SEPARATOR.$exportfilename;
        $ziparchive = new \zip_archive();
        if ($ziparchive->open($zipfilepath, \file_archive::CREATE)) {
            $ziparchive->add_file_from_pathname('xmldb.xsd', $CFG->dirroot.'/lib/xmldb/xmldb.xsd');
            $ziparchive->add_file_from_pathname($structurefilename, $dir.DIRECTORY_SEPARATOR.$structurefilename);
            $ziparchive->add_file_from_pathname($sequencesfilename, $dir.DIRECTORY_SEPARATOR.$sequencesfilename);
            foreach ($tables as $table => $tableobj) {
                $ziparchive->add_file_from_pathname($table.'.json', $dir.DIRECTORY_SEPARATOR.$table.'.json');
            }
            if ($precheck = $this->prechecks[configoverride::get_name()] ?? null) {
                if ($confs = $precheck->get_config_overrides_for_backup()) {
                    $ziparchive->add_file_from_string(constants::FILE_CONFIGOVERRIDE, json_encode($confs));
                }
            }
            $ziparchive->close();
        } else {
            // TODO?
            throw new \moodle_exception('Can not create ZIP file');
        }

        unlink($dir.DIRECTORY_SEPARATOR.$structurefilename);
        unlink($dir.DIRECTORY_SEPARATOR.$sequencesfilename);
        foreach ($tables as $table => $tableobj) {
            unlink($dir.DIRECTORY_SEPARATOR.$table.'.json');
        }

        return $zipfilepath;
    }

    /**
     * Export $CFG->dataroot
     *
     * @param string $exportfilename
     * @return string path to the zip file with the export
     */
    public function export_dataroot(string $exportfilename) {
        global $CFG;
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
                $this->backup->add_log('- can not back up file with contenthash '.$chash.' - skipping');
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
}
