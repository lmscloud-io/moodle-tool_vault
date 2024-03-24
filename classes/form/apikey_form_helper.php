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

/**
 * Both dynamic form and legacy form for entering API key need to use common functions
 *
 * @package    tool_vault
 * @copyright  2024 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class apikey_form_helper {

    /**
     * Form definition.
     *
     * @param \MoodleQuickForm $mform
     */
    public static function definition(\MoodleQuickForm $mform) {
        $mform->addElement('text', 'apikey', 'API key', ['style' => 'width:100%']);
        $mform->setType('apikey', PARAM_RAW_TRIMMED);
        $mform->addRule('apikey', null, 'required', null, 'client');
    }

    /**
     * Pre-check submitted API key
     *
     * @param array $data
     * @param array $errors
     */
    public static function validation($data, array &$errors): void {
        if (!strlen($data['apikey'] ?? '')) {
            $errors['apikey'] = get_string('required');
        } else if (strlen($data['apikey']) < 15) {
            $errors['apikey'] = get_string('error_apikeytooshort', 'tool_vault');
        } else if (strlen($data['apikey']) > 100) {
            $errors['apikey'] = get_string('error_apikeytoolong', 'tool_vault');
        } else if (!preg_match('/^[a-zA-Z0-9]+$/', $data['apikey'])) {
            $errors['apikey'] = get_string('error_apikeycharacters', 'tool_vault');
        } else if (!api::validate_api_key($data['apikey'])) {
            $errors['apikey'] = get_string('error_apikeynotvalid', 'tool_vault');
        }
    }
}
