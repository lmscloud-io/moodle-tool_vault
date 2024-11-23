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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_vault\local\checks;
use tool_vault\api;
use tool_vault\constants;
use tool_vault\local\helpers\ui;

/**
 * Class environ
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class environ extends check_base {

    /**
     * Display name of this check
     *
     * @return string
     */
    public static function get_display_name() {
        return get_string('environbackup', 'tool_vault');
    }

    /**
     * Evaluate check and store results in model details
     */
    public function perform() {
        $this->model->set_details([
            'max_execution_time' => ini_get("max_execution_time"),
        ])->save();
    }

    /**
     * Pre-check is successful (backup/restore can be performed)
     *
     * @return bool
     */
    public function success() {
        $maxexectime = $this->model->get_details()['max_execution_time'];
        return $this->model->status === constants::STATUS_FINISHED
            && (!$maxexectime || $maxexectime >= constants::MIN_REQUIRED_EXEC_TIME);
    }

    /**
     * Is there a warning
     *
     * @return bool
     */
    public function warning() {
        return $this->success() && !empty($this->model->get_details()['max_execution_time']);
    }

    /**
     * String explaining the status
     *
     * @return string
     */
    public function get_status_message() {
        if ($this->warning()) {
            return get_string('environ_success_warning', 'tool_vault',
                    ['value' => $this->formatted_max_execution_time(), 'url' => api::get_frontend_url().'/faq']);
        } else if ($this->success()) {
            return get_string('success', 'moodle');
        } else {
            return get_string('environ_fail', 'tool_vault', api::get_frontend_url().'/faq');
        }
    }

    /**
     * Returns human-readable max_execution_time value
     *
     * @return string
     */
    protected function formatted_max_execution_time() {
        $maxexectime = $this->model->get_details()['max_execution_time'];
        if (!$maxexectime) {
            return get_string('unlimited', 'moodle');
        } else {
            return ui::format_duration($maxexectime);
        }
    }

    /**
     * Get summary of the past check
     *
     * @return string
     */
    public function summary() {
        if ($this->model->status !== constants::STATUS_FINISHED) {
            return '';
        }
        $highlightedstatus = '';
        if (!$this->success() || $this->warning()) {
            $highlightedstatus = $this->display_status_message($this->get_status_message(), $this->warning());
        }
        return
            $highlightedstatus.
            '<ul>'.
            '<li>' . get_string('environbackup_maxexecutiontime', 'tool_vault') . ': ' .
                $this->formatted_max_execution_time().'</li>'.
            '</ul>';
    }

    /**
     * Get detailed report of the past check
     *
     * @return string
     */
    public function detailed_report() {
        return '';
    }

    /**
     * Does this past check have details (to display a link "Show details")
     *
     * @return bool
     */
    public function has_details() {
        return false;
    }
}
