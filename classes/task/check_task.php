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

namespace tool_vault\task;

use core\task\adhoc_task;
use tool_vault\constants;
use tool_vault\local\checks\base;
use tool_vault\local\models\check;

/**
 * Ad-hoc task for scheduling checks
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_task extends adhoc_task {

    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {

        $models = check::get_records([constants::STATUS_INPROGRESS]);
        if ($models) {
            // This task should not run if another task is in progress. This can only mean that other task
            // aborted. Mark the stalled check as failed.
            foreach ($models as $model) {
                $model->set_status(constants::STATUS_FAILED)->save();
            }
        }

        $checks = base::get_scheduled();
        foreach ($checks as $check) {
            $check->mark_as_inprogress();
            try {
                $check->perform();
                $check->mark_as_finished();
            } catch (\Throwable $t) {
                // TODO analyse error, reschedule.
                mtrace("Failed to execute check: ".$t->getMessage()."\n".$t->getTraceAsString());
                $check->mark_as_failed($t);
            }
        }
    }

    /**
     * Schedule this task
     *
     * @return void
     */
    public static function schedule() {
        $task = new static();
        \core\task\manager::queue_adhoc_task($task, true);
    }
}
