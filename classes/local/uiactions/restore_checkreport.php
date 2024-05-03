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
use tool_vault\output\check_display;

/**
 * Class restore_checkreport
 *
 * @package    tool_vault
 * @copyright  2024 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_checkreport extends base {

    /**
     * Display name of the section (for the breadcrumb)
     *
     * @return string
     */
    public static function get_display_name(): string {
        return get_string('precheckdetails', 'tool_vault');
    }

    /**
     * Process action
     */
    public function process() {
        // Note, there is no call to parent::process() because this page needs to be available to
        // see results of the CLI pre-check fails.
    }

    /**
     * Display
     *
     * @param \renderer_base $output
     * @return string
     */
    public function display(\renderer_base $output) {
        $id = optional_param('id', null, PARAM_INT);

        if ($id && ($check = \tool_vault\local\checks\check_base::load($id))) {
            $data = (new check_display($check, true))->export_for_template($output);
            return $output->render_from_template('tool_vault/check_details', $data);
        }

        return '';
    }
}
