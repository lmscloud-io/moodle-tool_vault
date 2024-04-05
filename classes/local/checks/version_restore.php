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
            'backuprelease' => $parent->get_metadata()['release'] ?? '',
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
        return ((float)($CFG->version) >= $version) && $this->core_upgrade_possible();
    }

    /**
     * Checks if moodle core upgrade is possible
     *
     * For example, in environment.xml we see <MOODLE version="4.2" requires="3.11.8">
     * which means that the upgrade from 3.9 (or any release before 3.11.8) to 4.2 is not possible.
     *
     * @return bool
     */
    public function core_upgrade_possible(): bool {
        global $CFG;
        require_once($CFG->dirroot.'/lib/environmentlib.php');

        $details = $this->model->get_details();
        $release = $details['backuprelease'] ?? '';

        if (empty($release)) {
            // Release was not collected before tool_vault v1.5, skip this check.
            return true;
        }

        $normrelease = normalize_version($release);
        $majorversion = get_latest_version_available(normalize_version($CFG->release), ENV_SELECT_RELEASE);
        $data = get_environment_for_version($majorversion, ENV_SELECT_RELEASE);
        $requires = $data['@']['requires'] ?? '1.0';
        return version_compare($normrelease, $requires, '>=');
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
        require_once($CFG->dirroot.'/lib/environmentlib.php');

        $details = $this->model->get_details();
        $branch = $details['backupbranch'] ?? null;
        $version = $details['backupversion'];
        if ($this->success()) {
            if ($this->core_needs_upgrade()) {
                return get_string('moodleversion_success_withupgrade', 'tool_vault');
            } else {
                return get_string('moodleversion_success', 'tool_vault');
            }
            // phpcs:disable Squiz.PHP.CommentedOutCode.Found
            // Probably not needed. Begin.
            // } else if ("{$branch}" !== "{$CFG->branch}") {
            // return "Can not restore backup made on a different branch (major version) of Moodle. ".
            // "This backup branch is '{$branch}' and this site branch is '{$CFG->branch}'";
            // End.
        } else if ($this->core_needs_upgrade() && !$this->core_upgrade_possible()) {
            $a = (object)[
                'backuprelease' => normalize_version($details['backuprelease'] ?? ''),
                'currentrelease' => get_latest_version_available(normalize_version($CFG->release), ENV_SELECT_RELEASE),
                'url' => (new \moodle_url('/admin/environment.php'))->out(false),
            ];
            return get_string('moodleversion_fail_cannotupgrade', 'tool_vault', $a);
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
        require_once($CFG->dirroot.'/lib/environmentlib.php');

        if ($this->model->status !== constants::STATUS_FINISHED) {
            return '';
        }
        $details = $this->model->get_details();
        return
            $this->display_status_message($this->get_status_message(), $this->core_needs_upgrade()).
            '<ul>'.
            '<li>' . get_string('moodleversion_backupinfo', 'tool_vault', (object)[
                'version' => $details['backupversion'],
                'branch' => !empty($details['backuprelease']) ? normalize_version($details['backuprelease']) :
                    $details['backupbranch'],
            ]) . '</li>'.
            '<li>' . get_string('moodleversion_siteinfo', 'tool_vault', (object)[
                'version' => $CFG->version,
                'branch' => normalize_version($CFG->release),
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
