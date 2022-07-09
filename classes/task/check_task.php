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
use tool_vault\local\checks\base;

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
        $customdata = (array)$this->get_custom_data();
        $id = $customdata['id'] ?? null;
        if (!$id) {
            mtrace('Parameter id is not found');
            return;
        }

        if (!$check = base::load($id)) {
            mtrace("Failed to load check with id $id");
            return;
        }

        $check->mark_as_inprogress();
        try {
            $check->perform();
        } catch (\Throwable $t) {
            // TODO analyse error, reschedule.
            mtrace("Failed to execute check: ".$t->getMessage()."\n".$t->getTraceAsString());
            $check->mark_as_failed($t);
            return;
        }
        $check->mark_as_finished();
    }

    /**
     * Schedule this task
     *
     * @param int $id
     * @return void
     */
    public static function schedule(int $id) {
        $task = new static();
        $task->set_custom_data(['id' => $id]);
        \core\task\manager::queue_adhoc_task($task, true);
    }
}
