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

namespace tool_vault\output;

use renderer_base;
use tool_vault\local\checks\check_base;

/**
 * Display check results
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_display implements \templatable {

    /** @var check_base */
    protected $check;
    /** @var bool */
    protected $detailed;

    /**
     * Constructor
     *
     * @param check_base $check
     * @param bool $detailed
     */
    public function __construct(check_base $check, bool $detailed = false) {
        $this->check = $check;
        $this->detailed = $detailed;
    }

    /**
     * Export for template
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $rescheduleurl = $this->check->get_reschedule_url();
        $fullreporturl = $this->check->get_fullreport_url();
        $rv = [
            'title' => $this->check->get_display_name(),
            'subtitle' => 'Status: '.$this->check->get_model()->status.', '.
                userdate($this->check->get_model()->timemodified, get_string('strftimedatetimeshort', 'langconfig')),
            'inprogress' => $this->check->is_in_progress(),
            'reschedulelink' => $rescheduleurl ? $rescheduleurl->out(false) : '',
            'summary' => $this->check->summary(),
            'showdetailslink' => $this->check->has_details(),
            'fullreporturl' => $this->check->has_details() ? $fullreporturl->out(false) : null,
            'errormessage' => error_with_backtrace::create_from_model($this->check->get_model())->export_for_template($output),
        ];
        if ($this->detailed) {
            $rv['details'] = $this->check->detailed_report();
        }
        return $rv;
    }
}
