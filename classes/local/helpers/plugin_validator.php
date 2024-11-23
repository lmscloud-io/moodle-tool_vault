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

namespace tool_vault\local\helpers;

/**
 * Wrapper for core plugin validator with some additional features and simplifications
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_validator extends \core\update\validator {

    /** @var array */
    protected static $allowedassertions = ['component', 'moodleversion', 'plugintype'];

    /**
     * Validate a plugin and return an instance of this class
     *
     * @param string $zipcontentpath
     * @param array $zipcontentfiles
     * @param array $assertions
     * @return \tool_vault\local\helpers\plugin_validator
     */
    public static function validate($zipcontentpath, array $zipcontentfiles, array $assertions = []) {
        $validator = new static($zipcontentpath, $zipcontentfiles);

        foreach ($assertions as $key => $value) {
            if (!in_array($key, self::$allowedassertions)) {
                throw new \coding_exception('Can not assert '. $key);
            }
            $validator->assertions[$key] = $value;
        }

        $result = $validator->execute();
        if (!$result) {
            foreach ($validator->get_messages() as $message) {
                if ($message->level == self::ERROR) {
                    throw new \moodle_exception('validationerror', 'tool_vault', '',
                        $validator->get_full_error_message($message->msgcode, $message->addinfo));
                }
            }
            throw new \moodle_exception(get_string('validationerror', 'tool_vault', ['unknown']));
        }
        return $validator;
    }

    /**
     * Human-readable error message
     *
     * @param string $msgcode
     * @param array|string|null $addinfo
     * @return string
     */
    protected function get_full_error_message($msgcode, $addinfo) {
        // Some common errors with better explanations than in the parent class.
        if ($msgcode === 'pathwritable') {
            $pluginpath = plugincode::guess_plugin_path($this->get_component());
            if (!is_writable(dirname($pluginpath))) {
                return get_string('validationmsg_pathnotwritable', 'tool_vault', dirname($pluginpath));
            } else if (!is_writable($pluginpath)) {
                return get_string('validationmsg_pathnotwritable', 'tool_vault', $pluginpath);
            } else {
                return get_string('validationmsg_pathnotremovable', 'tool_vault', $pluginpath);
            }
        }
        if ($msgcode === 'requiresmoodle') {
            return get_string('validationmsg_requiresmoodle', 'tool_vault', $addinfo);
        }

        // For other errors take descriptions from the parent class.
        $error = $msgcode . ' ' . $this->message_code_name($msgcode);
        if ($info = $this->message_code_info($msgcode, $addinfo)) {
            $error .= substr($info, -1) === '.' ? '' : '.';
            $error .= ' ' . $info;
        }
        return $error;
    }

    /**
     * Returns false if the version.php file does not declare required information.
     *
     * @return bool
     */
    protected function validate_version_php() {
        global $CFG;

        if (!isset($this->assertions['moodleversion'])) {
            // Parent class requires 'moodleversion' but it is always the current moodle version.
            $this->assertions['moodleversion'] = $CFG->version;
        }

        if (!isset($this->assertions['plugintype'])) {
            // Parent class requires 'plugintype' to be present for some reason.
            if (!empty($this->assertions['component'])) {
                $component = $this->assertions['component'];
            } else {
                $fullpath = $this->extractdir.'/'.$this->rootdir.'/version.php';
                $info = $this->parse_version_php($fullpath);
                $component = $info['plugin->component'];
            }
            list($type, $name) = \core_component::normalize_component($component);
            $this->assertions['plugintype'] = $type;
        }

        $result = parent::validate_version_php();
        if ($result && !empty($this->assertions['component']) && $this->get_component() !== $this->assertions['component']) {
            $this->add_message(self::ERROR, 'componentmismatchname');
            return false;
        }

        return $result;
    }

    /**
     * Full plugin name (to be called after it is validated)
     *
     * @return string
     */
    public function get_component() {
        $component = $this->get_versionphp_info()['component'] ?? null;
        if ($component === null) {
            // Something was not parsed.
            $fullpath = $this->extractdir.'/'.$this->rootdir.'/version.php';
            $info = $this->parse_version_php($fullpath);
            $component = $info['plugin->component'];
        }
        return $component;
    }

    /**
     * Version of the plugin (to be called after it is validated)
     *
     * @return string
     */
    public function get_version() {
        return $this->get_versionphp_info()['version'] ?? '';
    }
}
