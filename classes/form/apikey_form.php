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
use core_form\dynamic_form;
use tool_vault\api;

/**
 * Allows to set API key
 *
 * @package    tool_vault
 * @copyright  2023 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class apikey_form extends dynamic_form {
    /**
     * Returns context where this form is used
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    /**
     * Checks if current user has access to this form, otherwise throws exception
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('moodle/site:config', $this->get_context_for_dynamic_submission());
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     *
     * This method can return scalar values or arrays that can be json-encoded, they will be passed to the caller JS.
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        api::set_api_key($data->apikey);
    }

    /**
     * Load in existing data as form defaults
     */
    public function set_data_for_dynamic_submission(): void {
        $apikey = $this->optional_param('apikey', '', PARAM_RAW);
        $this->set_data(['apikey' => $apikey]);
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/admin/tool/vault/index.php', ['addapikey' => 1]);
    }

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
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
