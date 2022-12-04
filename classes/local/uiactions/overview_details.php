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

namespace tool_vault\local\uiactions;

use tool_vault\output\check_display;

/**
 * Details of a backup pre-check
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview_details extends base {

    /**
     * Display
     *
     * @param \renderer_base $output
     * @return string
     */
    public function display(\renderer_base $output) {
        $id = optional_param('id', null, PARAM_INT);

        if ($id && ($check = \tool_vault\local\checks\check_base::load($id)) && $check->has_details()) {
            $data = (new check_display($check, true))->export_for_template($output);
            $data['details'] = $check->detailed_report();
            return $output->render_from_template('tool_vault/check_details', $data);
        }

        // TODO show error?
        return '';
    }
}
