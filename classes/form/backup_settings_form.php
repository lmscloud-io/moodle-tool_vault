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

namespace tool_vault\form;

use tool_vault\api;
use tool_vault\local\helpers\siteinfo;
use tool_vault\local\uiactions\settings_backup;

/**
 * Backup settings
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_settings_form extends base_settings_form {
    /** @var string[] */
    const BACKUPSETTINGSNAMES = [
        'backupexcludetables',
        'backupexcludeindexes',
        'backupexcludedataroot',
        'backupexcludeplugins',
    ];

    /**
     * Constructor
     *
     * @param bool $editable
     */
    public function __construct(bool $editable = true) {
        $this->action = settings_backup::url();
        $this->settingsnames = self::BACKUPSETTINGSNAMES;
        parent::__construct($editable);
    }

    /**
     * Form definition
     */
    protected function definition() {
        global $CFG;
        $this->add_textarea('backupexcludetables',
            'Exclude tables',
            'Backup process will analyse all tables which names start with the prefix "'.
            $CFG->prefix.'" and include them in the backup. You can specify here the list of tables '.
            'that should not be backed up. The tables that are defined in xmldb schema of the core or '.
            'of any installed plugins can not be excluded.');

        $this->add_textarea('backupexcludeindexes',
            'Exclude indexes',
            'To exclude extra indexes from the backup list them here as TABLENAME.INDEXNAME.');

        $this->add_textarea('backupexcludedataroot',
            'Exclude paths in dataroot',
            'All paths within dataroot folder will be included in the backup except for: '.
            'filedir (backed up separately), '.join(', ', siteinfo::common_excluded_dataroot_paths()).
            '. If you want to exclude more paths list them here.');

        $this->add_textarea('backupexcludeplugins',
            'Exclude plugins',
            'Only for plugins with server-specific configuration, for example, file storage or session management. '.
            'For other plugins include them in backup and uninstall after restore. '.
            'Note that this will only exclude data in plugin\'s own tables, settings, associated files, scheduled '.
            'tasks and other known common types of plugin-related data. It may not be accurate for complicated plugins '.
            'or plugins with dependencies.');

        $this->set_data_and_add_buttons('Edit backup settings');
    }

    /**
     * Validate table name
     *
     * @param string $table
     * @param array $dbtables
     * @return string|null error text or null if there are no errors
     */
    protected function validate_table_name($table, $dbtables) {
        global $CFG;
        $tablewithoutprefix = $this->strip_db_prefix($table);
        if (!empty($CFG->prefix) && $table === $tablewithoutprefix) {
            return 'Table "' . $table . '" does not start with prefix "' . $CFG->prefix.
                '", this table will not be included in the backup anyway.';
        } else if (!in_array(strtolower($tablewithoutprefix), $dbtables)) {
            return 'Table "' . $tablewithoutprefix . '" does not exist in the database';
        }
        return null;
    }

    /**
     * Validate index name
     *
     * @param string $index
     * @param array $dbtables
     * @return string|null
     */
    protected function validate_index_name($index, $dbtables) {
        global $DB;
        $parts = preg_split('/\\./', $index);
        if (count($parts) != 2) {
            return $index . ' : index must have format: "TABLENAME.INDEXNAME"';
        } else if ($error = $this->validate_table_name($parts[0], $dbtables)) {
            return $error;
        } else {
            $tablewithoutprefix = $this->strip_db_prefix($parts[0]);
            $indexnames = array_keys($DB->get_indexes($tablewithoutprefix));
            if (!in_array($parts[1], $indexnames)) {
                return 'Index "' . $parts[1] . '" is not found in table "' . $parts[0] . '"' .
                    '; available indexes: ' . join(', ', $indexnames);
            }
        }
        return null;
    }

    /**
     * Form validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $CFG, $DB;
        $errors = parent::validation($data, $files);
        $tables = $this->split_list($data['backupexcludetables']);
        if ($tables) {
            $dbtables = $DB->get_tables();
            foreach ($tables as $table) {
                if ($error = $this->validate_table_name($table, $dbtables)) {
                    $errors['backupexcludetables'] = $error;
                }
            }
        }

        $indexes = $this->split_list($data['backupexcludeindexes']);
        if ($indexes) {
            $dbtables = $dbtables ?? $DB->get_tables();
            foreach ($indexes as $index) {
                if ($error = $this->validate_index_name($index, $dbtables)) {
                    $errors['backupexcludeindexes'] = $error;
                }
            }
        }

        $paths = $this->split_list($data['backupexcludedataroot']);
        if ($paths) {
            foreach ($paths as $path) {
                if ($error = $this->validate_path($path)) {
                    $errors['backupexcludedataroot'] = $error;
                    break;
                }
            }
        }

        $plugins = $this->split_list($data['backupexcludeplugins']);
        if ($plugins) {
            foreach ($plugins as $plugin) {
                if ($error = $this->validate_plugin_name($plugin)) {
                    $errors['backupexcludeplugins'] = $error;
                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * Process form
     *
     * @return void
     */
    public function process() {
        $data = $this->get_data();
        foreach ($this->settingsnames as $name) {
            $elements = preg_split('/[\\s,]/', trim($data->$name), -1, PREG_SPLIT_NO_EMPTY);
            api::store_config($name, join(', ', $elements));
        }
    }

    /**
     * There are backup settings
     *
     * @return bool
     */
    public static function has_backup_settings(): bool {
        return !empty(api::get_config('backupexcludetables')) ||
            !empty(api::get_config('backupexcludeindexes'));
    }
}
