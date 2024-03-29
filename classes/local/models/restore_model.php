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

namespace tool_vault\local\models;

use tool_vault\constants;
use tool_vault\local\helpers\ui;

/**
 * Model for local restore
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_model extends restore_base_model {
    /** @var string */
    protected static $defaulttype = 'restore';

    /**
     * Get display title
     *
     * @return string
     */
    public function get_title() {
        $title = get_string('restorefrombackup', 'tool_vault', s($this->backupkey));
        if ($this->status === constants::STATUS_SCHEDULED) {
            $title .= ' (' . ui::format_status($this->status) . ')';
        }
        return $title;
    }

    /**
     * Get status and time modified
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_subtitle() {
        return get_string('status', 'moodle') . ' ' . ui::format_status($this->status) .
            ' : ' . userdate($this->timemodified, get_string('strftimedatetimeshort', 'langconfig'));
    }

    /**
     * Save record
     *
     * @return operation_model
     */
    public function save(): operation_model {
        if (!$this->accesskey) {
            $this->generate_access_key();
        }
        return parent::save();
    }
}
