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

use tool_vault\local\checks\check_base;
use tool_vault\local\checks\plugins_restore;
use tool_vault\local\helpers\dbops;
use tool_vault\local\helpers\files_restore;
use tool_vault\local\helpers\plugindata;
use tool_vault\local\helpers\siteinfo;
use tool_vault\local\models\backup_model;
use tool_vault\local\models\restore_model;
use tool_vault\local\operations\operation_base;
use tool_vault\local\restoreactions\restore_action;
use tool_vault\local\xmldb\dbstructure;
use tool_vault\local\xmldb\dbtable;

// Mdlcode-disable cannot-parse-db-tablename.

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
    /** @var files_restore[] */
    protected $filesrestore = [];

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
     * Get the helper to restore files of the specified type
     *
     * @param string $filetype
     * @return files_restore
     */
    public function get_files_restore(string $filetype): files_restore {
        if (!array_key_exists($filetype, $this->filesrestore)) {
            $this->filesrestore[$filetype] = new files_restore($this, $filetype);
        }
        return $this->filesrestore[$filetype];
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
        $encryptionkey = api::prepare_encryption_key($params['passphrase'] ?? '');
        $model
            ->set_status( constants::STATUS_SCHEDULED)
            ->set_backupkey($backupkey)
            ->set_details([
                'id' => $USER->id ?? '',
                'username' => $USER->username ?? '',
                'fullname' => $USER ? fullname($USER) : '',
                'email' => $USER->email ?? '',
                'encryptionkey' => $encryptionkey,
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
        $restorekey = api::request_new_restore_key(['backupkey' => $this->model->backupkey]);
        $this->model->set_pid_for_logging($pid);
        $this->model
            ->set_status(constants::STATUS_INPROGRESS)
            ->set_details(['restorekey' => $restorekey])
            ->save();
    }

    /**
     * Perform restore
     *
     * @return void
     * @throws \moodle_exception
     */
    public function execute() {
        $this->add_to_log('Preparing to restore');

        $this->prechecks = site_restore_dryrun::execute_prechecks(
            $this->get_files_restore(constants::FILENAME_DBSTRUCTURE), $this->model, $this);

        foreach ($this->prechecks as $chk) {
            if (!$chk->success()) {
                $this->add_to_log('Aborting restore process. Restore pre-checks failed.', constants::LOGLEVEL_ERROR);
                $this->model
                    ->set_status(constants::STATUS_FAILED)
                    ->set_details(['encryptionkey' => ''])
                    ->save();
                api::update_restore_ignoring_errors($this->model->get_details()['restorekey'], [], constants::STATUS_FAILED);
                return;
            }
        }

        $this->prepare_restore_db();

        // From this moment on we can not throw any exceptions, we have to try to restore as much as possible skipping problems.
        $this->add_to_log('Restore started');

        restore_action::execute_before_restore($this);
        $this->restore_db();
        restore_action::execute_after_db_restore($this);
        $this->restore_dataroot();
        restore_action::execute_after_dataroot_restore($this);
        $this->restore_filedir();
        restore_action::execute_after_restore($this);

        $this->model
            ->set_status(constants::STATUS_FINISHED)
            ->set_details(['encryptionkey' => ''])
            ->save();
        $this->get_files_restore(constants::FILENAME_DBSTRUCTURE)->finish();
        api::update_restore_ignoring_errors($this->model->get_details()['restorekey'], [], constants::STATUS_FINISHED);
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
     */
    public function prepare_restore_db() {
        $helper = $this->get_files_restore(constants::FILENAME_DBSTRUCTURE);
        $filepath = $helper->get_all_files()[constants::FILE_STRUCTURE];

        $this->dbstructure = dbstructure::load_from_backup($filepath);

        // TODO do all the checks that all tables exist and have necessary fields.
    }

    /**
     * Apply config overrides
     *
     * @param string $tablename
     * @return void
     */
    protected function apply_config_overrides(string $tablename) {
        $structurefiles = $this->get_files_restore(constants::FILENAME_DBSTRUCTURE)->get_all_files();
        if (!array_key_exists(constants::FILE_CONFIGOVERRIDE, $structurefiles)) {
            return;
        }
        $confs = json_decode(file_get_contents($structurefiles[constants::FILE_CONFIGOVERRIDE]), true);
        foreach ($confs as $conf) {
            if ($tablename === 'config' && empty($conf['plugin'])) {
                set_config($conf['name'], $conf['value']);
            } else if ($tablename === 'config_plugins' && !empty($conf['plugin'])
                    && !in_array($conf['plugin'], siteinfo::get_excluded_plugins_restore())) {
                set_config($conf['name'], $conf['value'], $conf['plugin']);
            }
        }
    }

    /**
     * Allows to preserve data associated with the selected plugins before the table is truncated
     *
     * @param dbtable $table
     * @return array
     * @throws \dml_exception
     */
    protected function preserve_some_plugins_data(dbtable $table) {
        global $DB;
        $tablename = $table->get_xmldb_table()->getName();
        if (!in_array($tablename, plugindata::get_tables_with_possible_plugin_data_to_preserve())) {
            return [];
        }
        $plugins = siteinfo::get_excluded_plugins_restore();
        $backupuserid = $this->model->get_metadata()['userid'] ?? 2;
        [$sql, $params, $fields] = plugindata::get_sql_for_plugins_data_in_table_to_preserve($tablename, $plugins, $backupuserid);
        // TODO what if the process was aborted in the middle of this table's restore - this should better be saved in a file.
        return $DB->get_records_select($tablename, $sql, $params, 'id', $fields);
    }

    /**
     * Adds back preserved data associated with the selected plugins
     *
     * This will also remove all data associated with the selected plugins from the backup data
     *
     * @param dbtable $table
     * @param array $records
     * @return void
     */
    protected function restore_preserved_plugins_data(dbtable $table, array $records) {
        global $DB;
        $tablename = $table->get_xmldb_table()->getName();
        if (!in_array($tablename, plugindata::get_tables_with_possible_plugin_data())) {
            return;
        }
        $plugins = siteinfo::get_excluded_plugins_restore();
        [$sql, $params] = plugindata::get_sql_for_plugins_data_in_table($tablename, $plugins);
        $DB->delete_records_select($tablename, $sql, $params);
        if (!empty($records)) {
            $DB->insert_records($tablename, $records);
        }
    }

    /**
     * Restore db
     *
     * @return void
     */
    public function restore_db() {
        global $DB;

        $tables = array_filter($this->dbstructure->get_backup_tables(), function(dbtable $tableobj, $tablename) {
            return !siteinfo::is_table_preserved_in_restore($tablename, $tableobj);
        }, ARRAY_FILTER_USE_BOTH);

        $this->add_to_log('Starting database restore ('.count($tables).' tables)...');

        $structurefiles = $this->get_files_restore(constants::FILENAME_DBSTRUCTURE)->get_all_files();
        $filepath = $structurefiles[constants::FILE_SEQUENCE] ?? null;
        $sequences = $filepath ? json_decode(file_get_contents($filepath), true) : [];

        $totaltables = count($tables);
        $tablescnt = $lasttablescnt = 0;
        $lastlog = time();

        $helper = $this->get_files_restore(constants::FILENAME_DBDUMP);
        while (($tabledata = $helper->get_next_table()) !== null) {
            [$tablename, $filesfortable] = $tabledata;
            if (!array_key_exists($tablename, $tables)) {
                // Must be skipped.
                continue;
            }
            $table = $tables[$tablename];

            // The existing respective table on this site.
            $originaltable = $this->dbstructure->get_tables_actual()[$tablename] ?? null;

            if ($originaltable) {
                // If some plugins are marked as "Preserve during restore" (tool_vault is one of them) - save the current
                // data from this table.
                $preservedrecords = $this->preserve_some_plugins_data($table);
                // Truncate table.
                $DB->delete_records($tablename);
            } else {
                $preservedrecords = [];
            }

            // Alter table structure in the DB if needed.
            if ($altersql = $table->get_alter_sql($originaltable)) {
                try {
                    $DB->change_database_structure($altersql);
                    if ($originaltable) {
                        $this->add_to_log('- table '.$tablename.' structure is modified');
                    }
                } catch (\Throwable $t) {
                    $this->add_to_log('- table '.$tablename.' structure is modified, failed to apply modifications: '.
                        $t->getMessage(), constants::LOGLEVEL_WARNING);
                }
            }

            // Insert new data.
            foreach ($filesfortable as $filepath) {
                $data = json_decode(file_get_contents($filepath), true);
                if ($data) {
                    $this->add_to_log("- File ".basename($filepath)." -- table $tablename -- inserting ".count($data)." records",
                        constants::LOGLEVEL_VERBOSE);
                    $fields = array_shift($data);
                    dbops::insert_records($tablename, $fields, $data, $this);
                }
                unlink($filepath);
            }

            // Change sequences.
            if ($altersql = $table->get_fix_sequence_sql($sequences[$tablename] ?? 0)) {
                try {
                    $DB->change_database_structure($altersql);
                } catch (\Throwable $t) {
                    $this->add_to_log("- failed to change sequence for table $tablename: ".$t->getMessage(),
                        constants::LOGLEVEL_WARNING);
                }
            }

            // Apply config overrides.
            if ($tablename === 'config' || $tablename === 'config_overrides') {
                $this->apply_config_overrides($tablename);
            }

            // Delete data associated with the preserved plugins and re-insert their data.
            $this->restore_preserved_plugins_data($table, $preservedrecords);

            // Add to log.
            $tablescnt++;
            if (time() - $lastlog > constants::LOG_FREQUENCY) {
                $this->add_to_log(get_string('logrestoredtables', 'tool_vault', (object)[
                    'cnt' => sprintf("%".strlen(''.$totaltables)."d", $tablescnt),
                    'totalcnt' => $totaltables,
                    'percent' => sprintf("%3d", (int)(100.0 * $tablescnt / $totaltables)),
                ]));
                $lastlog = time();
                $lasttablescnt = $tablescnt;
            }
        }

        if ($lasttablescnt < $tablescnt) {
            $this->add_to_log(get_string('logrestoredtables', 'tool_vault', (object)[
                'cnt' => sprintf("%".strlen(''.$totaltables)."d", $tablescnt),
                'totalcnt' => $tablescnt,
                'percent' => '100',
            ]));
        }

        // Drop all extra tables.
        $this->drop_tables_from_extra_plugins();

        $this->add_to_log('Finished database restore');
    }

    /**
     * Drop tables all plugins that are "extra" (code present on this site but data absent in the backup)
     *
     * Because the 'config_plugins' table does not have the 'version' for these plugins,
     * next time admin logs in to the site they will be prompted to upgrade and install them.
     * If the database tables are present, the upgrade process will throw an exception.
     *
     * @return void
     */
    protected function drop_tables_from_extra_plugins() {
        global $DB;

        /** @var plugins_restore $precheck */
        $precheck = $this->prechecks[plugins_restore::get_name()] ?? null;
        if (!$precheck) {
            return;
        }
        $extraplugins = $precheck->extra_plugins();
        $extrapluginswithtables = [];
        $extratables = [];
        $preservedplugins = siteinfo::get_excluded_plugins_restore();
        foreach ($this->dbstructure->get_tables_definitions() as $tablename => $table) {
            if (array_key_exists($table->get_component(), $extraplugins)
                    && !in_array($table->get_component(), $preservedplugins)) {
                $extratables[$tablename] = $table;
                $extrapluginswithtables[$table->get_component()] = 1;
            }
        }
        if ($extratables) {
            $this->add_to_log('Dropping database tables from plugins that are not present in the backup: ' .
                join(', ', array_keys($extrapluginswithtables)).'...');
            foreach ($extratables as $table) {
                $DB->get_manager()->drop_table($table->get_xmldb_table());
            }
            $this->add_to_log('...done');
        }
    }

    /**
     * Restore dataroot
     *
     * @return void
     */
    public function restore_dataroot() {
        global $CFG;
        $this->add_to_log('Starting dataroot restore');
        $helper = $this->get_files_restore(constants::FILENAME_DATAROOT);

        if ($helper->is_first_archive()) {
            // Delete everything from the current dataroot (this will not be executed if we resume restore).
            $handle = opendir($CFG->dataroot);
            while (($file = readdir($handle)) !== false) {
                if (!siteinfo::is_dataroot_path_skipped_restore($file) && $file !== '.' && $file !== '..') {
                    $cnt = self::remove_recursively($CFG->dataroot.DIRECTORY_SEPARATOR.$file);
                    if (!file_exists($CFG->dataroot.DIRECTORY_SEPARATOR.$file)) {
                        continue;
                    } else if (is_dir($CFG->dataroot.DIRECTORY_SEPARATOR.$file)) {
                        $this->add_to_log("Failed to remove existing dataroot directory" .
                            ($cnt ? ", $cnt files in the directory were removed" : ''), constants::LOGLEVEL_WARNING);
                    } else {
                        $this->add_to_log('Failed to remove existing dataroot file', constants::LOGLEVEL_WARNING);
                    }
                }
            }
            closedir($handle);
        }

        // Start extracting files.
        while (($nextfile = $helper->get_next_file()) !== null) {
            [$path, $file] = $nextfile;
            $newpath = $CFG->dataroot.DIRECTORY_SEPARATOR.$file;
            if (is_dir($path) && !is_dir($newpath)) {
                try {
                    make_writable_directory($newpath);
                } catch (\Throwable $t) {
                    $this->add_to_log($t->getMessage(), constants::LOGLEVEL_WARNING);
                }
            } else if (!is_dir($newpath)) {
                $dir = dirname($newpath);
                if (!(file_exists($dir) && is_dir($dir) && is_writable($dir))) {
                    // We already showed the warning when we could not create a dir.
                    continue;
                }
                if (!file_exists($newpath) || is_writable($newpath)) {
                    rename($path, $newpath);
                } else {
                    $this->add_to_log('- existing path '.$file.' in dataroot could not be replaced',
                        constants::LOGLEVEL_WARNING);
                }
            }
        }

        $this->add_to_log('Finished dataroot restore');
    }

    /**
     * Restore filedir
     *
     * This function works with any file storage (local or remote)
     *
     * @return void
     */
    public function restore_filedir() {
        $this->add_to_log('Starting files restore');
        $fs = get_file_storage();
        $helper = $this->get_files_restore(constants::FILENAME_FILEDIR);
        while (($nextfile = $helper->get_next_file()) !== null) {
            [$filepath, $subpath] = $nextfile;
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
        }
        $this->add_to_log('Finished files restore');
    }

    /**
     * Remove directory recursively
     *
     * @param string $dir
     * @return int count of removed files
     */
    public static function remove_recursively(string $dir): int {
        if (!file_exists($dir)) {
            return 0;
        }
        if (!is_dir($dir)) {
            return (int)unlink($dir);
        }
        $cnt = 0;
        $it = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($it,
            \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                $cnt += (int)unlink($file->getRealPath());
            }
        }
        rmdir($dir);
        return $cnt;
    }

    /**
     * Mark restore as failed
     *
     * @param \Throwable $t
     * @return void
     */
    public function mark_as_failed(\Throwable $t) {
        parent::mark_as_failed($t);
        $this->model->set_details(['encryptionkey' => ''])->save();
        $restorekey = $this->model->get_details()['restorekey'] ?? '';
        if ($restorekey) {
            api::update_restore_ignoring_errors($restorekey, ['faileddetails' => $t->getMessage()], constants::STATUS_FAILED);
        }
    }
}
