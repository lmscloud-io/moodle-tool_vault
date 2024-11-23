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

namespace tool_vault\local\uiactions;

use tool_vault\local\tools\tool_base;

/**
 * Class tools
 *
 * @package    tool_vault
 * @copyright  2024 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vaulttools extends base {

    /**
     * Display name of the section (for the breadcrumb)
     *
     * @return string
     */
    public static function get_display_name() {
        return get_string('tools', 'tool_vault');
    }

    /**
     * Process action
     */
    public function process() {
        parent::process();
        $tool = optional_param('type', null, PARAM_ALPHANUMEXT);
        if ($tool) {
            require_sesskey();
            $tool = tool_base::schedule(['type' => $tool]);
            redirect(vaulttools_details::url(['id' => $tool->get_model()->id]));
        }
    }

    /**
     * Export for output
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template($output) {
        global $CFG, $USER;

        /** @var tool_base[] $tools */
        $tools = [
        ];

        $result = ['tools' => []];

        foreach ($tools as $toolclass) {
            $type = basename(preg_replace('/\\\\/', '/', $toolclass), '');
            $url = $this->url(['type' => $type, 'sesskey' => sesskey()]);
            $result['tools'][] = [
                'title' => $toolclass::get_display_name(),
                'description' => $toolclass::get_description(),
                'actionurl' => $url->out(false),
                'actionlabel' => $toolclass::get_action_button_label(),
            ];
        }
        $result['hastools'] = !empty($tools);

        return $result;
    }

    /**
     * Display
     *
     * @param \renderer_base $output
     * @return string
     */
    public function display(\renderer_base $output) {
        return $output->render_from_template('tool_vault/vaulttools',
            $this->export_for_template($output));
    }
}
