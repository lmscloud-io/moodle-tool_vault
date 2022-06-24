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

defined('MOODLE_INTERNAL') || die();

use tool_vault\api;

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Enter API or register and obtain a new API
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class register_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        // TODO strings.
        $mform->addElement('radio', 'scope', 'I have an API key', null, 'apikey');
        $mform->addElement('text', 'apikey', 'API key');
        $mform->setType('apikey', PARAM_ALPHANUMEXT);
        $mform->hideIf('apikey', 'scope', 'ne', 'apikey');
        $mform->addElement('radio', 'scope', 'I want to register', null, 'register');
        $mform->setDefault('scope', 'apikey');
        $this->add_action_buttons(false, 'Proceed');
    }

    /**
     * Validation
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        $errors = [];
        if ($data['scope'] === 'apikey' && !api::validate_api_key($data['apikey'])) {
            $errors['apikey'] = 'This key is not valid';
        }
        return $errors;
    }

    /**
     * Process
     *
     * @return void
     */
    public function process() {
        $data = $this->get_data();
        $action = $data->scope;
        if ($action === 'register') {
            \tool_vault\api::register();
        } else {
            api::store_config('apikey', $data->apikey);
        }
    }
}
