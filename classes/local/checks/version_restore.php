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

namespace tool_vault\local\checks;

use tool_vault\constants;
use tool_vault\local\models\dryrun_model;

/**
 * Check version on restore
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class version_restore extends check_base {

    /**
     * Evaluate check and store results in model details
     */
    public function perform(): void {
        /** @var dryrun_model $parent */
        $parent = $this->get_parent();
        $this->model->set_details([
            'backupversion' => $parent->get_metadata()['version'],
            'backupbranch' => $parent->get_metadata()['branch'],
            'success' => true,
        ])->save();
    }

    /**
     * Can backup be performed
     *
     * @return bool
     */
    public function success(): bool {
        global $CFG;
        if ($this->model->status !== constants::STATUS_FINISHED) {
            return false;
        }
        $details = $this->model->get_details();
        $version = (float)($details['backupversion']);
        $branch = $details['backupbranch'];
        return "{$branch}" === "{$CFG->branch}" && (float)($CFG->version) >= $version;
    }

    /**
     * Status message text
     *
     * @return string
     */
    public function get_status_message(): string {
        global $CFG;
        $details = $this->model->get_details();
        $branch = $details['backupbranch'] ?? null;
        $version = $details['backupversion'];
        if ($this->success()) {
            return 'Moodle version matches';
        } else if ("{$branch}" !== "{$CFG->branch}") {
            return "Can not restore backup made on a different branch (major version) of Moodle. ".
                "This backup branch is '{$branch}' and this site branch is '{$CFG->branch}'";
        } else {
            return "Site version number has to be not lower than the version in the backup. ".
                "This backup is {$version} and this site is {$CFG->version}";
        }
    }

    /**
     * Get summary of the past check
     *
     * @return string
     */
    public function summary(): string {
        global $CFG;
        if ($this->model->status !== constants::STATUS_FINISHED) {
            return '';
        }
        $details = $this->model->get_details();
        return
            $this->display_status_message($this->get_status_message()).
            '<ul>'.
            '<li>Backup made in version '.$details['backupversion'].' (branch '.$details['backupbranch'].')</li>'.
            '<li>This website has version '.$CFG->version.' (branch '.$CFG->branch.')</li>'.
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
        return get_string('moodleversion', 'moodle');
    }
}
