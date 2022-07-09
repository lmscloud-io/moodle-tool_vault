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

namespace tool_vault\output;

use tool_vault\local\checks\base;

/**
 * Tab overview
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section_overview extends section_base {

    /**
     * Process tab actions
     */
    public function process() {
        global $PAGE;
        $action = optional_param('action', null, PARAM_ALPHANUMEXT);

        if ($action === 'newcheck' && confirm_sesskey()) {
            base::schedule_new(required_param('type', PARAM_ALPHANUMEXT));
            redirect($PAGE->url);
        }
    }
}