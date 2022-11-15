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
 * Restore settings
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_settings_form extends \moodleform {

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
        $this->action = new \moodle_url($action, ['action' => 'restore']);
        parent::__construct(new \moodle_url($this->action), null, 'post', '', null, $this->editable);
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

        $this->set_data([
            'allowrestore' => (int)(bool)api::get_config('allowrestore'),
            'removemissing' => (int)(bool)api::get_config('removemissing'),
        ]);
        if (!$this->editable) {
            $mform->addElement('html',
                \html_writer::div(\html_writer::link($this->action, 'Edit restore settings', ['class' => 'btn btn-secondary']),
                    'pb-3'));
        } else {
            $this->add_action_buttons();
        }
    }

    /**
     * Process form
     *
     * @return void
     */
    public function process() {
        $data = $this->get_data();
        api::store_config('allowrestore', $data->allowrestore);
        api::store_config('removemissing', $data->removemissing);
    }
}
