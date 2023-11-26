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
use tool_vault\local\uiactions\settings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Base settings form
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_settings_form extends \moodleform {

    /** @var bool */
    protected $editable = true;
    /** @var \moodle_url */
    protected $action = null;
    /** @var array */
    protected $settingsnames = [];

    /**
     * Constructor
     *
     * @param bool $editable
     */
    public function __construct(bool $editable = true) {
        $this->editable = $editable;
        $this->action = $this->action ?? settings::url();
        parent::__construct(new \moodle_url($this->action), null, 'post', '', null, $this->editable);
    }

    /**
     * Set data and add buttons
     *
     * @param string $btnlabel
     * @return void
     */
    protected function set_data_and_add_buttons(string $btnlabel) {
        $data = [];
        foreach ($this->settingsnames as $name) {
            $data[$name] = api::get_config($name);
        }
        $this->set_data($data);
        if (!$this->editable) {
            $this->_form->addElement('html',
                \html_writer::div(\html_writer::link($this->action, $btnlabel, ['class' => 'btn btn-secondary']),
                    'pb-3'));
        } else {
            $this->add_action_buttons();
        }
    }

    /**
     * Add a textarea to the form
     *
     * @param string $elementname
     * @param string $title
     * @param string $help
     * @return void
     */
    protected function add_textarea(string $elementname, string $title, string $help = '') {
        if (!in_array($elementname, $this->settingsnames)) {
            debugging('Did you forget to include '.$elementname.' in the $settingsnames?', DEBUG_DEVELOPER);
        }
        $mform = $this->_form;
        $mform->addElement($this->editable ? 'textarea' : 'static', $elementname,
            $title);
        $mform->setType($elementname, PARAM_RAW);
        if ($this->editable && strlen($help)) {
            $mform->addElement('static', $elementname.'_desc', '', $help);
        }
    }

    /**
     * Split string into array
     *
     * @param string $value
     * @return string[]
     */
    protected function split_list(string $value) {
        return preg_split('/[\\s,]/', trim($value ?? ''), -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Strip $CFG->prefix from the tablename
     *
     * @param string $tablename
     * @return string
     */
    protected function strip_db_prefix(string $tablename) {
        global $CFG;
        return preg_replace('/^'.preg_quote($CFG->prefix, '/').'/i', '', $tablename);
    }

    /**
     * Validate plugin name
     *
     * @param string $plugin
     * @return string|null error message or null if there are no errors
     */
    protected function validate_plugin_name(string $plugin) {
        $plugin = strtolower($plugin);
        if ($plugin !== clean_param($plugin, PARAM_PLUGIN) || strpos($plugin, '_') === false) {
            return 'Name "'.s($plugin).'" is not a valid plugin name';
        }
        list($type, $name) = explode('_', $plugin, 2);
        if (!array_key_exists($type, \core_component::get_plugin_types())) {
            return 'Name "'.s($plugin).'" is not a valid plugin name';
        }
        if (in_array($type, siteinfo::unsupported_plugin_types_to_exclude())) {
            return 'Plugins of type "'.$type.'" can not be excluded';
        }
        if (siteinfo::plugin_has_xmldb_uninstall_function($plugin)) {
            return 'Plugin "'.$plugin.
                '" can not be processed because it has a custom uninstall function "xmldb_'.$plugin.'_uninstall()"';
        }
        if (siteinfo::plugin_has_subplugins($plugin)) {
            return 'Plugin "'.$plugin.'" can not be processed because it has subplugins';
        }
        return null;
    }

    /**
     * Validate dataroot path
     *
     * @param string $path
     * @return string|null error message or null if there are no errors
     */
    protected function validate_path(string $path) {
        if (preg_replace("#[/\\\\]#", '', clean_param($path, PARAM_FILE)) !== $path) {
            return "Path '".s($path)."' is not a valid path";
        }
        return null;
    }
}
