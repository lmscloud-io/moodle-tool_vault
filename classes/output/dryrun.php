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
use tool_vault\constants;
use tool_vault\site_restore_dryrun;

/**
 * Output for restore dry-run (checks only)
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dryrun implements \templatable {
    /** @var site_restore_dryrun */
    protected $dryrun;

    /**
     * Constructor
     *
     * @param site_restore_dryrun $dryrun
     */
    public function __construct(site_restore_dryrun $dryrun) {
        $this->dryrun = $dryrun;
    }

    /**
     * Get status and time modified
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_subtitle() {
        $model = $this->dryrun->get_model();
        return 'Status '.$model->status.' : '.userdate($model->timemodified, get_string('strftimedatetimeshort', 'langconfig'));
    }

    /**
     * Function to export the renderer data in a format that is suitable for a mustache template.
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $model = $this->dryrun->get_model();
        $isfinished = $model->status === constants::STATUS_FINISHED;
        $inprogress = $model->status === constants::STATUS_SCHEDULED || $model->status === constants::STATUS_INPROGRESS;
        $prechecks = [];
        foreach ($this->dryrun->get_prechecks() as $check) {
            $prechecks[] = (new check_display($check))->export_for_template($output);
        }
        return [
            'subtitle' => $this->get_subtitle(),
            'logs' => $isfinished ? '' : $model->get_logs(),
            'inprogress' => $inprogress,
            'prechecks' => $prechecks,
        ];
    }
}
