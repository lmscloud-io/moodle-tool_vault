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

namespace tool_vault\local\checks;

use tool_vault\constants;
use tool_vault\local\xmldb\dbstructure;
use tool_vault\site_backup;
use tool_vault\site_restore;

/**
 * Check disk space on restore
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class diskspace_restore extends check_base {

    /**
     * Evaluate check and store results in model details
     */
    public function perform(): void {
        $freespace = disk_free_space(make_request_directory());
        $enoughspace = true; // TODO.
        $this->model->set_details([
            'freespace' => $freespace,
            'enoughspace' => $enoughspace,
        ])->save();
    }

    /**
     * Can backup be performed
     *
     * @return bool
     */
    public function success(): bool {
        return $this->model->status === constants::STATUS_FINISHED
            && $this->model->get_details()['enoughspace'];
    }

    /**
     * Status message
     *
     * @return string
     */
    public function get_status_message(): string {
        return $this->success() ?
            'There is enough disk space to perform site restore' :
            'There is not enough disk space to perform site restore';
    }

    /**
     * Get summary of the past check
     *
     * @return string
     */
    public function summary(): string {
        if ($this->model->status !== constants::STATUS_FINISHED) {
            return '';
        }
        $details = $this->model->get_details();
        return
            $this->display_status_message($this->get_status_message()).
            '<ul>'.
            '<li>Free space in temp dir: '.display_size($details['freespace']).'</li>'.
            '</ul>';
    }

    /**
     * Does this past check have details (to display a link "Show details")
     *
     * @return bool
     */
    public function has_details(): bool {
        return false;
    }

    /**
     * Get detailed report of the past check
     *
     * @return string
     */
    public function detailed_report(): string {
        return '';
    }

    /**
     * Display name of this check
     *
     * @return string
     */
    public static function get_display_name(): string {
        return "Disk space";
    }
}
