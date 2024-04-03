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
class version_restore extends check_base_restore {

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
        return (float)($CFG->version) >= $version;
    }

    /**
     * Restore is performed into a higher version of Moodle core than the backup
     *
     * @return bool
     */
    public function core_needs_upgrade(): bool {
        global $CFG;
        $details = $this->model->get_details();
        $version = (float)($details['backupversion']);
        return (float)($CFG->version) > $version;
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
            if ($this->core_needs_upgrade()) {
                return 'Restore can be perrformed but you will need to run upgrade process after it completes.';
            } else {
                return get_string('moodleversion_success', 'tool_vault');
            }
            // phpcs:disable Squiz.PHP.CommentedOutCode.Found
            // Probably not needed. Begin.
            // } else if ("{$branch}" !== "{$CFG->branch}") {
            // return "Can not restore backup made on a different branch (major version) of Moodle. ".
            // "This backup branch is '{$branch}' and this site branch is '{$CFG->branch}'";
            // End.
        } else {
            $a = (object)[
                'version' => $version,
                'siteversion' => $CFG->version,
            ];
            return get_string('moodleversion_fail', 'tool_vault', $a);
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
            $this->display_status_message($this->get_status_message(), $this->core_needs_upgrade()).
            '<ul>'.
            '<li>' . get_string('moodleversion_backupinfo', 'tool_vault', (object)[
                'version' => $details['backupversion'],
                'branch' => $details['backupbranch'],
            ]) . '</li>'.
            '<li>' . get_string('moodleversion_siteinfo', 'tool_vault', (object)[
                'version' => $CFG->version,
                'branch' => $CFG->branch,
            ]) . '</li>'.
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
