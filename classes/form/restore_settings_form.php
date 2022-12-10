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
use tool_vault\local\uiactions\settings_restore;

/**
 * Restore settings
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_settings_form extends base_settings_form {
    /** @var string[] */
    protected $settingsnames = [
        'allowrestore',
        'removemissing',
        'restorepreserveplugins',
        'restorepreservedataroot'
    ];

    /**
     * Constructor
     *
     * @param bool $editable
     */
    public function __construct(bool $editable = true) {
        $this->action = settings_restore::url();
        parent::__construct($editable);
    }

    /**
     * Form definition
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('select', 'allowrestore', 'Allow restores on this site', [
            0 => get_string('no'),
            1 => get_string('yes'),
        ]);

        $mform->addElement('select', 'removemissing', 'Automatically remove missing plugins after restore', [
            0 => get_string('no'),
            1 => get_string('yes'),
        ]);

        $this->add_textarea('restorepreservedataroot',
            'Preserve paths in dataroot',
            'All paths within dataroot folder will be removed except for: '.
            'filedir (backed up separately), '.join(', ', siteinfo::common_excluded_dataroot_paths()).
            '. If you want to keep more paths list them here.');

        $this->add_textarea('restorepreserveplugins',
            'Preserve plugins',
            'Only for plugins with server-specific configuration, for example, file storage or session management. '.
            'Restore process will attempt to preserve existing data associated with these plugins and not restore data from the '.
            'backup if the same plugin is included. '.
            'Note that this will only process data in plugin\'s own tables, settings, associated files, scheduled '.
            'tasks and other known common types of plugin-related data. It may not be accurate for complicated plugins '.
            'or plugins with dependencies.');

        $this->set_data_and_add_buttons('Edit restore settings');
    }

    /**
     * Form validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $paths = $this->split_list($data['restorepreservedataroot']);
        if ($paths) {
            foreach ($paths as $path) {
                if ($error = $this->validate_path($path)) {
                    $errors['restorepreservedataroot'] = $error;
                    break;
                }
            }
        }

        $plugins = $this->split_list($data['restorepreserveplugins']);
        if ($plugins) {
            foreach ($plugins as $plugin) {
                if ($error = $this->validate_plugin_name($plugin)) {
                    $errors['restorepreserveplugins'] = $error;
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
            if (in_array('name', ['allowrestore', 'removemissing'])) {
                $value = $data->$name;
            } else {
                $elements = preg_split('/[\\s,]/', trim($data->$name), -1, PREG_SPLIT_NO_EMPTY);
                $value = join(', ', $elements);
            }
            api::store_config($name, $value);
        }
    }
}
