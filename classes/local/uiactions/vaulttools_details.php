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
use tool_vault\local\helpers\ui;
use tool_vault\local\tools\tool_base;
use tool_vault\output\error_with_backtrace;

/**
 * Class tools_details
 *
 * @package    tool_vault
 * @copyright  2024 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vaulttools_details extends base {

    /**
     * Display name of the section (for the breadcrumb)
     *
     * @return string
     */
    public static function get_display_name(): string {
        return get_string('tools', 'tool_vault');
    }

    /**
     * Display
     *
     * @param \renderer_base $output
     * @return string
     */
    public function display(\renderer_base $output) {
        global $DB;
        $id = optional_param('id', 0, PARAM_INT);

        $tool = tool_base::load($id);

        if (!$tool) {
            throw new \moodle_exception('invalidrecordunknown');
        }

        $inprogress = $tool->is_in_progress();
        $logs = $tool->get_model()->get_logs();
        $errormessage = error_with_backtrace::create_from_model($tool->get_model())->export_for_template($output);

        $data = [
            'title' => $tool->get_display_name(),
            'subtitle' => get_string('status', 'moodle') . ': '.
                ui::format_status($tool->get_model()->status) . ', ' .
                userdate($tool->get_model()->timemodified, get_string('strftimedatetimeshort', 'langconfig')),
            'inprogress' => $inprogress,
            'errormessage' => $errormessage,
            'haslogs' => !empty($logs),
            'logs' => $logs,
        ];
        return $output->render_from_template('tool_vault/vaulttools_details', $data);
    }
}
