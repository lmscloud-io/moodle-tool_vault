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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_vault\local\uiactions;

use tool_vault\local\models\backup_model;
use tool_vault\local\models\dryrun_model;
use tool_vault\local\models\operation_model;
use tool_vault\local\models\restore_model;
use tool_vault\output\check_display;
use tool_vault\output\last_operation;

/**
 * Tab overview
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview extends base {

    /**
     * Display
     *
     * @param \renderer_base $output
     * @return string
     */
    public function display(\renderer_base $output) {
        $rv = '';

        $lastoperation = operation_model::get_last_of([backup_model::class, restore_model::class, dryrun_model::class]);
        if ($lastoperation && $lastoperation->show_as_last_operation()) {
            $data = (new last_operation($lastoperation))->export_for_template($output);
            $rv .= $output->render_from_template('tool_vault/last_operation', $data);
        }

        foreach (\tool_vault\local\checks\check_base::get_all_checks() as $check) {
            $data = (new check_display($check))->export_for_template($output);
            $rv .= $output->render_from_template('tool_vault/check_summary', $data);
        }

        return $rv;
    }
}
