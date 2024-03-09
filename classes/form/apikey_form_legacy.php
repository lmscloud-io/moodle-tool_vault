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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_vault\form;
use tool_vault\api;

require_once($CFG->libdir . "/formslib.php");

/**
 * Class apikey_form_legacy
 *
 * @package    tool_vault
 * @copyright  2024 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class apikey_form_legacy extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $returnurl = $this->_customdata['returnurl'] ?? '';
        $mform->addElement("hidden", "returnurl", $returnurl);
        $mform->setType('returnurl', PARAM_URL);
        $mform->addElement('text', 'apikey', 'API key', ['style' => 'width:100%']);
        $mform->setType('apikey', PARAM_RAW);
        $mform->addRule('apikey', null, 'required', null, 'client');
        $this->add_action_buttons();
    }

    /**
     * Validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = [];
        if (!strlen($data['apikey'])) {
            $errors['apikey'] = get_string('required');
        } else if (strlen($data['apikey']) && !api::validate_api_key($data['apikey'])) {
            $errors['apikey'] = get_string('error_apikeynotvalid', 'tool_vault');
        }
        return $errors;
    }
}
