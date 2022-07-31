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
use tool_vault\site_restore_dryrun;

/**
 * Ad-hoc task for site restore dry-run
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dryrun_task extends adhoc_task {

    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {
        try {
            $dryrun = site_restore_dryrun::start_dryrun($this->get_pid());
        } catch (\Throwable $t) {
            mtrace("Failed to start: ".$t->getMessage());
            return;
        }

        try {
            $dryrun->execute();
        } catch (\Throwable $t) {
            $dryrun->mark_as_failed($t);
            mtrace("Failed to execute: ".$t->getMessage()."\n".$t->getTraceAsString());
        }
    }

    /**
     * Schedule this task
     *
     * @return void
     */
    public static function schedule() {
        global $USER;

        $task = new static();
        $task->set_userid($USER->id);

        \core\task\manager::queue_adhoc_task($task, true);
    }
}
