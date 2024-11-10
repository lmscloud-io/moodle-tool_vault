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

/**
 * Backup logs
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class logs extends base {

    /**
     * Display name of the section (for the breadcrumb)
     *
     * @return string
     */
    public static function get_display_name(): string {
        return get_string('history', 'tool_vault');
    }

    /**
     * Export for output
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template($output): array {
        global $CFG, $USER;

        $result = [];

        $backups = backup_model::get_records(null, null, 0, 20); // TODO pagination?
        $result['backups'] = [];
        foreach ($backups as $backup) {
            $result['backups'][] = (new \tool_vault\output\backup_details($backup, null, false))->export_for_template($output);
        }
        $result['haspastbackups'] = !empty($result['backups']);

        return $result;
    }

    /**
     * Display
     *
     * @param \renderer_base $output
     * @return string
     */
    public function display(\renderer_base $output) {
        return $output->render_from_template('tool_vault/logs',
            $this->export_for_template($output));
    }
}
