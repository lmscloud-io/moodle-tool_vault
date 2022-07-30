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

use renderer_base;
use tool_vault\local\models\backup_model;

/**
 * Backup details
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_details implements \templatable {
    /** @var backup_model */
    protected $backup;

    /**
     * Constructor
     *
     * @param backup_model $backup
     */
    public function __construct(backup_model $backup) {
        $this->backup = $backup;
    }

    /**
     * Export for output
     *
     * @param \tool_vault\output\renderer $output
     * @return array
     */
    public function export_for_template($output) {
        $url = new \moodle_url('/admin/tool/vault/index.php', ['section' => 'backup']);
        return [
            'sectionurl' => $url->out(false),
            'title' => $this->backup->get_title(),
            'subtitle' => $this->backup->get_subtitle(),
            'details' =>
                '<h4>Metadata</h4>'.
                // @codingStandardsIgnoreLine
                '<pre>'.print_r($this->backup->get_details(), true).'</pre>' .
                '<h4>Logs</h4>'.
                // @codingStandardsIgnoreLine
                '<pre>'.print_r($this->backup->get_logs(), true).'</pre>',
        ];
    }
}
