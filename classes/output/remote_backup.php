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
use stdClass;
use tool_vault\api;

/**
 * Remote backup
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remote_backup implements \templatable {

    /** @var \tool_vault\local\models\remote_backup */
    protected $backup;
    /** @var bool */
    protected $extradetails;

    /**
     * Constructor
     *
     * @param \tool_vault\local\models\remote_backup $backup
     * @param bool $extradetails
     */
    public function __construct(\tool_vault\local\models\remote_backup $backup, bool $extradetails = false) {
        $this->backup = $backup;
        $this->extradetails = $extradetails;
    }

    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template. This means:
     * 1. No complex types - only stdClass, array, int, string, float, bool
     * 2. Any additional info that is required for the template is pre-calculated (e.g. capability checks).
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return stdClass|array
     */
    public function export_for_template(renderer_base $output) {
        global $PAGE;
        $started = userdate($this->backup->timecreated, get_string('strftimedatetimeshort', 'langconfig'));
        $viewurl = new \moodle_url($PAGE->url,
            ['section' => 'restore', 'action' => 'remotedetails', 'backupkey' => $this->backup->backupkey]);
        $restoreurl = new \moodle_url($PAGE->url,
            ['section' => 'restore', 'action' => 'restore',
                'backupkey' => $this->backup->backupkey, 'sesskey' => sesskey()]);
        return [
            'sectionurl' => $PAGE->url->out(false),
            'showdetails' => $this->extradetails,
            'status' => $this->backup->status,
            'backupkey' => $this->backup->backupkey,
            'started' => $started,
            'info' => $this->backup->info,
            'isfinished' => ($this->backup->status === \tool_vault\constants::STATUS_FINISHED),
            'viewurl' => $viewurl->out(false),
            'restoreurl' => $restoreurl->out(false),
            // @codingStandardsIgnoreLine
            'details' => $this->extradetails ? print_r($this->backup->to_object(), true) : '',
            'restoreallowed' => api::are_restores_allowed(),
        ];
    }
}
