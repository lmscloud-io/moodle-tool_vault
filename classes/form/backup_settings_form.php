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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Backup settings
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_settings_form extends \moodleform {

    /** @var bool */
    protected $editable = true;
    /** @var \moodle_url */
    protected $action = null;

    /**
     * Constructor
     *
     * @param \moodle_url $action
     * @param bool $editable
     */
    public function __construct(\moodle_url $action, bool $editable = true) {
        $this->editable = $editable;
        $this->action = new \moodle_url($action, ['action' => 'backup']);
        parent::__construct(new \moodle_url($this->action), null, 'post', '', null, $this->editable);
    }

    /**
     * Form definition
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement($this->editable ? 'textarea' : 'static', 'backupexcludetables',
            'Exclude tables');
        $mform->setType('backupexcludetables', PARAM_RAW);
        // TODO Help: List of tables that will not be included in the backup. Do not include prefix.

        $mform->addElement($this->editable ? 'textarea' : 'static', 'backupexcludeindexes',
            'Exclude indexes');
        $mform->setType('backupexcludeindexes', PARAM_RAW);
        // TODO Help: List of tables that will not be included in the backup. Do not include prefix.

        $this->set_data([
            'backupexcludetables' => api::get_config('backupexcludetables'),
            'backupexcludeindexes' => api::get_config('backupexcludeindexes'),
        ]);
        if (!$this->editable) {
            $mform->addElement('html',
                \html_writer::div(\html_writer::link($this->action, 'Edit backup settings', ['class' => 'btn btn-secondary']),
                'pb-3'));
        } else {
            $this->add_action_buttons();
        }
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
        if (!in_array($table, $dbtables)) {
            if (!empty($CFG->prefix) && strpos($table, $CFG->prefix) === 0) {
                return 'Table {' . $table . '} does not exist in the database. Do not include prefix ' . $CFG->prefix;
            } else {
                return 'Table {' . $table . '} does not exist in the database';
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
        $tables = preg_split('/[\\s,]/', trim($data['backupexcludetables']), -1, PREG_SPLIT_NO_EMPTY);
        if ($tables) {
            $dbtables = $DB->get_tables();
            foreach ($tables as $table) {
                if ($error = $this->validate_table_name($table, $dbtables)) {
                    $errors['backupexcludetables'] = $error;
                }
            }
        }

        $indexes = preg_split('/[\\s,]/', trim($data['backupexcludeindexes']), -1, PREG_SPLIT_NO_EMPTY);
        if ($indexes) {
            $dbtables = $dbtables ?? $DB->get_tables();
            foreach ($indexes as $index) {
                $parts = preg_split('/\\./', $index);
                if (count($parts) != 2) {
                    $errors['backupexcludeindexes'] = $index.' : index must have format: "tablename.indexname"';
                } else if ($error = $this->validate_table_name($parts[0], $dbtables)) {
                    $errors['backupexcludeindexes'] = $error;
                } else {
                    $indexnames = array_keys($DB->get_indexes($parts[0]));
                    if (!in_array($parts[1], $indexnames)) {
                        $errors['backupexcludeindexes'] = 'Index '.$parts[1].' is not found in table {'.$parts[0].'}'.
                            '; available indexes: '. join(', ', $indexnames);
                    }
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
        $tables = preg_split('/[\\s,]/', trim($data->backupexcludetables), -1, PREG_SPLIT_NO_EMPTY);
        api::store_config('backupexcludetables', join(', ', $tables));
        $indexes = preg_split('/[\\s,]/', trim($data->backupexcludeindexes), -1, PREG_SPLIT_NO_EMPTY);
        api::store_config('backupexcludeindexes', join(', ', $indexes));
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
