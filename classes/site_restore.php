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
use tool_vault\local\checks\restore_precheck_failed;
use tool_vault\local\checks\version_restore;
use tool_vault\local\helpers\dbops;
use tool_vault\local\helpers\files_restore;
use tool_vault\local\helpers\plugindata;
use tool_vault\local\helpers\siteinfo;
use tool_vault\local\helpers\tempfiles;
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
            throw new \moodle_exception('error_restoresnotallowed', 'tool_vault');
        }
        $backupkey = $params['backupkey'];
        if ($records = restore_model::get_records([constants::STATUS_SCHEDULED])) {
            // Pressed button twice maybe?
            return new static(reset($records));
        }
        if (restore_model::get_records([constants::STATUS_INPROGRESS])) {
            throw new \moodle_exception('error_anotherrestoreisinprogress', 'tool_vault');
        }
        if (backup_model::get_records([constants::STATUS_INPROGRESS, constants::STATUS_SCHEDULED])) {
            throw new \moodle_exception('error_anotherbackupisinprogress', 'tool_vault');
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
            throw new \moodle_exception('error_apikeynotvalid', 'tool_vault');
        }
        if (!api::are_restores_allowed()) {
            throw new \moodle_exception('error_restoresnotallowed', 'tool_vault');
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
                throw new restore_precheck_failed($chk);
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
        tempfiles::cleanup();
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
        $filepath = $structurefiles[constants::FILE_CONFIGOVERRIDE];
        if (!file_exists($filepath)) {
            throw new \moodle_exception('error_filenotfound', 'tool_vault', '', $filepath);
        }
        $confs = json_decode(file_get_contents($filepath), true);
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
     * Executed before we delete the contents of the DB table during restore. Allows to save data that needs to be preserved.
     *
     * @param dbtable $table
     * @param dbtable|null $originaltable
     * @return array
     */
    protected function before_table_restore(dbtable $table, ?dbtable $originaltable): array {
        global $DB;
        $tablename = $table->get_xmldb_table()->getName();

        $data = ['preservedrecords' => []];
        if ($originaltable) {
            // If some plugins are marked as "Preserve during restore" (tool_vault is one of them) - save the current
            // data from this table.
            $data['preservedrecords'] = $this->preserve_some_plugins_data($table);
        }

        if ($tablename === 'user' && api::get_setting_checkbox('restorepreservepassword')) {
            $data['password'] = $DB->get_field_select('user', 'password', 'username = ? AND auth = ?', ['admin', 'manual']);
            if (empty($data['password'])) {
                $this->add_to_log('Could not preserve password of the "admin" user, user does not exist on this site '.
                    'or does not have a password', constants::LOGLEVEL_WARNING);
            }
        }

        return $data;
    }

    /**
     * Executed after we restore contents of the DB table. Allows to re-insert data that needed to be preserved.
     *
     * @param \tool_vault\local\xmldb\dbtable $table
     * @param array $data data that was returned by {@see self::before_table_restore()}
     * @return void
     */
    protected function after_table_restore(dbtable $table, array $data) {
        global $DB;
        $tablename = $table->get_xmldb_table()->getName();

        // Apply config overrides.
        if ($tablename === 'config' || $tablename === 'config_overrides') {
            $this->apply_config_overrides($tablename);
        }

        // Delete data associated with the preserved plugins and re-insert their data.
        $this->restore_preserved_plugins_data($table, $data['preservedrecords']);

        if ($tablename === 'user' && api::get_setting_checkbox('restorepreservepassword') && !empty($data['password'])) {
            $userid = $DB->get_field_select('user', 'id', 'username = ? AND auth = ?', ['admin', 'manual']) ?: null;
            if ($userid) {
                $DB->update_record('user', (object)['id' => $userid, 'password' => $data['password']]);
            } else {
                $this->add_to_log('Could not preserve password of the "admin" user, user does not exist on the backed up site '.
                    'or has a different authentication method', constants::LOGLEVEL_WARNING);
            }
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

        $totaltables = count($tables);
        $this->add_to_log('Starting database restore ('.$totaltables.' tables)...');

        $structurefiles = $this->get_files_restore(constants::FILENAME_DBSTRUCTURE)->get_all_files();
        $filepath = $structurefiles[constants::FILE_SEQUENCE] ?? null;
        if (!file_exists($filepath)) {
            throw new \moodle_exception('error_filenotfound', 'tool_vault', '', $filepath);
        }
        $sequences = $filepath ? json_decode(file_get_contents($filepath), true) : [];

        $tablescnt = 0;
        $lastlog = time();

        $helper = $this->get_files_restore(constants::FILENAME_DBDUMP);
        $totalorigsize = $helper->get_total_orig_size();
        $totalextracted = 0;

        $logprogress = function($lastlog, $tablescnt, $totalextracted, $force = false) use ($totaltables, $totalorigsize) {
            if (!$force && time() - $lastlog <= constants::LOG_FREQUENCY) {
                return $lastlog;
            }
            $totalcnt = (string)$totaltables;
            $percent = sprintf("%3d", (int)(100.0 * $totalextracted / $totalorigsize));
            $totalsize = display_size($totalorigsize);
            $cnt = str_pad($tablescnt, strlen($totalcnt), " ", STR_PAD_LEFT);
            $size = str_pad(display_size($totalextracted), max(9, strlen($totalsize)), " ", STR_PAD_LEFT);
            $this->add_to_log("Restored {$cnt}/{$totalcnt} tables, {$size}/{$totalsize} ({$percent}%)");
            return time();
        };

        while (($tabledata = $helper->get_next_table()) !== null) {
            [$tablename, $filesfortable] = $tabledata;
            if (!array_key_exists($tablename, $tables)) {
                // Must be skipped.
                continue;
            }
            $table = $tables[$tablename];

            // The existing respective table on this site.
            $originaltable = $this->dbstructure->get_tables_actual()[$tablename] ?? null;

            // Tool vault may need to preserve some data from the table before deleting it.
            $preserveddata = $this->before_table_restore($table, $originaltable);

            if ($originaltable) {
                // Truncate table.
                $DB->delete_records($tablename);
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
                $totalextracted += filesize($filepath);
                $data = json_decode(file_get_contents($filepath), true);
                if ($data) {
                    $this->add_to_log("- File ".basename($filepath)." -- table $tablename -- inserting ".count($data)." records",
                        constants::LOGLEVEL_VERBOSE);
                    $fields = array_shift($data);
                    dbops::insert_records($tablename, $fields, $data, $this);
                }
                unlink($filepath);
                // Add to log.
                $lastlog = $logprogress($lastlog, $tablescnt, $totalextracted);
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

            // Restore preserved data, apply config overrides, reset admin password, etc.
            $this->after_table_restore($table, $preserveddata);

            // Add to log.
            $tablescnt++;
            $lastlog = $logprogress($lastlog, $tablescnt, $totalextracted, $tablescnt == $totaltables);
        }

        // Drop all extra tables.
        $this->drop_extra_tables(array_keys($tables));

        $this->add_to_log('Finished database restore');
    }

    /**
     * Drop every table that has a definition in any install.xml files but is not present in the backup
     *
     * In the situation like this it means that the backup was made in a previous version
     * of core/plugin and the table will be created during the upgrade process. If it already exists
     * the upgrade process will throw an exception.
     *
     * Only do it in cases when:
     * - core tables if the current moodle version is higher than the one in the backup
     * - plugins that are installed on this site but missing in the backup
     * - plugins that are installed on this site and have a higher version than in the backup
     *
     * @param string[] $tablesinbackup
     * @return void
     */
    protected function drop_extra_tables(array $tablesinbackup) {
        global $DB;

        $components = [];

        /** @var version_restore $precheckcore */
        $precheckcore = $this->prechecks[version_restore::get_name()] ?? null;
        if ($precheckcore && $precheckcore->core_needs_upgrade()) {
            $components[] = 'core';
        }

        /** @var plugins_restore $precheck */
        $precheck = $this->prechecks[plugins_restore::get_name()] ?? null;
        if ($precheck) {
            $preservedplugins = siteinfo::get_excluded_plugins_restore();
            $components = array_merge($components,
                array_diff(array_keys($precheck->plugins_needing_upgrade() + $precheck->extra_plugins()), $preservedplugins));
        }

        if (empty($components)) {
            return;
        }

        // Find all tables that need to be dropped.
        $extratables = [];
        foreach ($this->dbstructure->get_tables_definitions() as $tablename => $table) {
            if (in_array($table->get_component(), $components) && !in_array($tablename, $tablesinbackup)) {
                $extratables[$tablename] = $table;
            }
        }

        // Drop extra tables.
        if ($extratables) {
            $this->add_to_log('Dropping database tables that are not present in the backup: ' .
                join(', ', array_keys($extratables)).'...');
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
                    $cnt = tempfiles::remove_temp_dir($CFG->dataroot.DIRECTORY_SEPARATOR.$file);
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
            $faileddetails = $this->get_error_message_for_server($t);
            api::update_restore_ignoring_errors($restorekey, ['faileddetails' => $faileddetails], constants::STATUS_FAILED);
        }
    }
}
