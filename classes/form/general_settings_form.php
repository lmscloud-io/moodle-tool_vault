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
use tool_vault\local\helpers\ui;
use tool_vault\local\uiactions\settings_forgetapikey;
use tool_vault\local\uiactions\settings_general;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * General settings
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class general_settings_form extends \moodleform {

    /** @var bool */
    protected $editable = true;
    /** @var \moodle_url */
    protected $action = null;

    /**
     * Constructor
     *
     * @param bool $editable
     */
    public function __construct(bool $editable = false) {
        $this->editable = $editable;
        $this->action = settings_general::url(['returnurl' => $this->get_redirect_url()->out_as_local_url(false)]);
        parent::__construct(new \moodle_url($this->action), null, 'post', '', null, $this->editable);
    }

    /**
     * Return url
     *
     * @return \moodle_url
     */
    public function get_redirect_url(): \moodle_url {
        global $PAGE;
        if ($returnurl = $this->optional_param('returnurl', null, PARAM_LOCALURL)) {
            return new \moodle_url($returnurl);
        }
        return $PAGE->url;
    }

    /**
     * Form definition
     */
    protected function definition() {
        global $CFG;
        $mform = $this->_form;

        if ($this->editable) {
            $mform->addElement('text', 'apikey', 'API key');
            $mform->setType('apikey', PARAM_RAW);
        } else if (api::is_registered()) {
            $forgeturl = settings_forgetapikey::url();
            $mform->addElement('static', 'staticapikey', 'API key',
                substr(api::get_api_key(), 0, 8) . '... ' .
                \html_writer::link(api::get_frontend_url(), get_string('managelsmvault', 'tool_vault'), ['target' => '_blank']) .
                ' &nbsp; ' .
                \html_writer::link($forgeturl, get_string('logoutfromvault', 'tool_vault')));
        } else {
            $mform->addElement('html', 'You need to register with Vault cloud to be able to backup or restore the site.');
        }

        $this->set_data([
            'apikey' => api::get_config('apikey'),
        ]);

        // Buttons.
        if (!$this->editable) {
            if (!api::is_registered()) {
                $registerurl = new \moodle_url(api::get_frontend_url() . '/getapikey',
                    ['siteid' => api::get_site_id(), 'siteurl' => $CFG->wwwroot, 'sesskey' => sesskey()]);
                $onclick = "MyWindow=window.open('".$registerurl->out(false).
                    "','MyWindow','width=800,height=600,top=100,left=100'); return false;";
                // TODO center/resize popup: https://stackoverflow.com/questions/4068373/center-a-popup-window-on-screen .
                $buttonregister = \html_writer::link($registerurl,
                    'Login / Sign up', ['class' => 'btn btn-secondary', 'onclick' => $onclick]);
                $addapikey = \html_writer::link($this->action, 'I have an API key', ['class' => 'btn btn-secondary']);
                $mform->addElement('html',
                    \html_writer::div($buttonregister . ' ' . $addapikey, 'pb-3'));
            }
        } else {
            $this->add_action_buttons();
        }
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
        if ($this->editable && strlen($data['apikey']) && !api::validate_api_key($data['apikey'])) {
            $errors['apikey'] = get_string('errorapikeynotvalid', 'tool_vault');
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
        api::set_api_key($data->apikey);
    }
}
