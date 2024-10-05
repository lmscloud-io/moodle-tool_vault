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
use tool_vault\local\checks\plugins_restore;
use tool_vault\local\helpers\plugincode;

/**
 * Class install_plugin_form
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class install_plugin_form extends dynamic_form {
    /**
     * Checks if current user has access to this form, otherwise throws exception
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('moodle/site:config', $this->get_context_for_dynamic_submission());
    }

    /**
     * Returns context where this form is used
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    /**
     * Pre-check id
     *
     * @return int
     */
    protected function get_id(): int {
        return $this->optional_param('id', 0, PARAM_INT);
    }

    /**
     * Plugin name
     *
     * @return string
     */
    protected function get_pluginname(): string {
        return $this->optional_param('pluginname', 0, PARAM_RAW);
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return \tool_vault\local\uiactions\restore_checkreport::url(['id' => $this->get_id()]);
    }

    /**
     * Load in existing data as form defaults
     */
    public function set_data_for_dynamic_submission(): void {
        $this->set_data([
            'id' => $this->get_id(),
            'pluginname' => $this->optional_param('pluginname', '', PARAM_PLUGIN),
            'version' => $this->optional_param('version', '', PARAM_TEXT),
            'source' => $this->optional_param('source', '', PARAM_TEXT),
            'downloadurl' => $this->optional_param('downloadurl', '', PARAM_URL),
        ]);
    }

    /**
     * Form definition
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'source');
        $mform->setType('source', PARAM_TEXT);
        $mform->addElement('hidden', 'pluginname');
        $mform->setType('pluginname', PARAM_PLUGIN);
        $mform->addElement('hidden', 'version');
        $mform->setType('version', PARAM_TEXT);
        $mform->addElement('hidden', 'downloadurl');
        $mform->setType('downloadurl', PARAM_URL);

        $mform->addElement('html', 'Code of the plugin '.$this->get_pluginname().
            ' will be added to the folder '.plugincode::guess_plugin_path_relative($this->get_pluginname()).
            ' . Once you added code for all necessary plugins you will need to run Moodle upgrade to install these plugins.');
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     */
    public function process_dynamic_submission() {
        $pluginname = $this->get_pluginname();
        $downloadurl = $this->optional_param('downloadurl', '', PARAM_URL);
        if (!plugincode::can_write_to_plugin_dir($pluginname)) {
            throw new \moodle_exception('Can not write');
        }
        plugincode::install_addon_from_moodleorg($downloadurl, $pluginname);
    }
}
