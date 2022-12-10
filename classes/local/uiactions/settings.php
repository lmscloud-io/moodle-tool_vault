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

use tool_vault\form\backup_settings_form;
use tool_vault\form\general_settings_form;
use tool_vault\form\restore_settings_form;

/**
 * Tab settings
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings extends base {

    /**
     * Display
     *
     * @param \renderer_base $output
     * @return string
     */
    public function display(\renderer_base $output) {
        $rv = '';
        $rv .= $output->heading(get_string('generalsettingsheader', 'tool_vault'), 3);
        $rv .= (new general_settings_form(false))->render();

        $rv .= $output->heading(get_string('backupsettingsheader', 'tool_vault'), 3);
        $rv .= (new backup_settings_form(false))->render();

        $rv .= $output->heading(get_string('restoresettingsheader', 'tool_vault'), 3);
        $rv .= (new restore_settings_form(false))->render();

        return $rv;
    }
}