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

namespace tool_vault\task;

use core\task\adhoc_task;

/**
 * Can be scheduled to be executed after built-in upgrade
 *
 * We will be moving here the code of upgrade ad-hoc tasks that were removed in core and standard plugins
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class after_upgrade_task extends adhoc_task {

    /**
     * Schedule an ad-hoc task to be executed after upgrade.
     *
     * @param string $classname
     * @param bool $checkforexisting
     * @param mixed $customdata
     * @return void
     */
    public static function schedule(string $classname, bool $checkforexisting = false, $customdata = null) {
        global $DB;
        if (class_exists($classname) && is_a($classname, adhoc_task::class, true)) {
            self::queue($classname, $checkforexisting, $customdata);
        } else if (method_exists(self::class, self::get_method_from_classname($classname))) {
            $customdata = ['classname' => $classname, 'other' => $customdata];
            self::queue(self::class, $checkforexisting, $customdata);
        } else {
            debugging('Ad-hoc task does not exist and no substitute is provided: ' . $classname, DEBUG_DEVELOPER);
        }
    }

    /**
     * Queue an ad-hoc task without executing \core\task\manager::queue_adhoc_task() that may not work during upgrade
     *
     * @param string $classname
     * @param bool $checkforexisting
     * @param mixed $customdata
     * @return void
     */
    protected static function queue(string $classname, bool $checkforexisting, $customdata) {
        global $DB;
        $parts = explode('\\', $classname);
        $record = (object)[
            'classname' => $classname,
            'component' => $parts[0],
            'nextruntime' => time() - 1,
            'timecreated' => time(),
            'customdata' => $customdata !== null ? json_encode($customdata) : null,
        ];
        if ($checkforexisting) {
            $existing = $DB->get_record('task_adhoc', ['classname' => $classname, 'component' => $record->component,
                'customdata' => $record->customdata]);
            if ($existing) {
                return;
            }
        }
        $DB->insert_record('task_adhoc', $record);
    }

    /**
     * Replacement method for the removed ad-hoc tasks.
     *
     * @param string $classname
     * @return string
     */
    protected static function get_method_from_classname(string $classname) {
        return str_replace('\\', '_', $classname);
    }

    /**
     * Run the adhoc task and fix the file timestamps.
     */
    public function execute() {
        $customdata = $this->get_custom_data();
        $method = self::get_method_from_classname($customdata->classname);
        if (method_exists($this, $method)) {
            $this->$method($customdata->other);
        } else {
            debugging('Ad-hoc task method does not exist: ' . $method, DEBUG_DEVELOPER);
        }
    }

    /**
     * Replacement for scheduled task \mod_bigbluebuttonbn\task\send_bigbluebutton_module_disabled_notification
     *
     * @return void
     */
    protected function mod_bigbluebuttonbn_task_send_bigbluebutton_module_disabled_notification() {
        // Do nothing. This task is no longer needed, see MDL-79239.
    }

    /**
     * Replacement for scheduled task \mod_forum\task\refresh_forum_post_counts
     *
     * @return void
     */
    protected function mod_forum_task_refresh_forum_post_counts() {
        // Updates null forum post counts according to the post message.
        global $CFG, $DB;

        // Default to chunks of 5000 records per run, unless overridden in config.php.
        $chunksize = $CFG->forumpostcountchunksize ?? 5000;

        // Initialize counter.
        $recordscount = 0;

        $select = 'wordcount IS NULL OR charcount IS NULL';
        $recordset = $DB->get_recordset_select('forum_posts', $select, null, 'discussion', 'id, message', 0, $chunksize);

        if (!$recordset->valid()) {
            $recordset->close();
            return;
        }

        foreach ($recordset as $record) {
            \mod_forum\local\entities\post::add_message_counts($record);
            $DB->update_record('forum_posts', $record);
            $recordscount++;
        }

        $recordset->close();

        if ($recordscount == $chunksize) {
            self::schedule(\mod_forum\task\refresh_forum_post_counts::class);
        }
    }
}
