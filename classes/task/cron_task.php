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

use tool_vault\constants;
use tool_vault\local\checks\check_base;
use tool_vault\local\models\backup_model;
use tool_vault\local\models\check_model;
use tool_vault\local\models\dryrun_model;
use tool_vault\local\models\operation_model;
use tool_vault\local\models\restore_model;
use tool_vault\local\operations\operation_base;
use tool_vault\site_backup;
use tool_vault\site_restore;
use tool_vault\site_restore_dryrun;

/**
 * Cron for tool_vault
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cron_task extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'tool_vault');
    }

    /** @var string */
    const Q_INPROGRESS = 'Q_INPROGRESS';
    /** @var string */
    const Q_INPROGRESS_STUCK = 'Q_INPROGRESS_STUCK';
    /** @var string */
    const Q_SCHEDULED_BACKUPS = 'Q_SCHEDULED_BACKUPS';
    /** @var string */
    const Q_SCHEDULED_RESTORES = 'Q_SCHEDULED_RESTORES';
    /** @var string */
    const Q_SCHEDULED_OTHER = 'Q_SCHEDULED_OTHER';

    /**
     * Analyse all operations that are scheduled or in progress and split them into groups
     *
     * @return array|array[]
     */
    protected function get_queue() {
        /** @var operation_model[] $records */
        $records = operation_model::get_records([constants::STATUS_SCHEDULED, constants::STATUS_INPROGRESS], 'id');
        $res = [
            self::Q_INPROGRESS => [],
            self::Q_INPROGRESS_STUCK => [],
            self::Q_SCHEDULED_BACKUPS => [],
            self::Q_SCHEDULED_RESTORES => [],
            self::Q_SCHEDULED_OTHER => [],
        ];
        $now = time();
        foreach ($records as $record) {
            if ($record->status === constants::STATUS_INPROGRESS) {
                if ($record instanceof backup_model || $record instanceof restore_model) {
                    $res[self::Q_INPROGRESS][] = $record;
                }
                if ($record->is_stuck()) {
                    $res[self::Q_INPROGRESS_STUCK][] = $record;
                }
            } else {
                if ($record instanceof backup_model) {
                    $res[self::Q_SCHEDULED_BACKUPS][] = $record;
                } else if ($record instanceof restore_model) {
                    $res[self::Q_SCHEDULED_RESTORES][] = $record;
                } else {
                    $res[self::Q_SCHEDULED_OTHER][] = $record;
                }
            }
        }
        return $res;
    }

    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {

        $queue = $this->get_queue();

        // Check if any operation is stuck - there was no activity for the last constants::LOCK_TIMEOUT seconds.
        foreach ($queue[self::Q_INPROGRESS_STUCK] as $record) {
            if ($record instanceof backup_model || $record instanceof restore_model) {
                // We found a backup or restore that is stuck. Attempt to resume it.
                $this->resume_operation($record);
                // Nothing else will run in this cron job.
                return;
            }

            if ($record instanceof check_model && $record->parentid && ($parent = operation_model::get_by_id($record->parentid))) {
                if ($parent->status !== constants::STATUS_INPROGRESS) {
                    // Something strange, the parent operation finished but this check is stuck in progress,
                    // mark it with the same status as the parent. This should not really happen.
                    mtrace("Operation with type {$record->type} and id {$record->id} has status {$record->status}. ".
                        "Changing status to the status of the parent operation - {$parent->status}");
                    $record->set_status($parent->status)->save();
                    continue;
                } else {
                    // The check has a parent in progress - ignore it, we will process the parent when they get stuck.
                    continue;
                }
            }

            // Either check or dry-run has stuck, they can not be resumed. Fail it.
            mtrace("Operation with type {$record->type} and id {$record->id} has timed out, failing it");
            $record->add_log('Operation timed out');
            $record->set_status(constants::STATUS_FAILED)->save();
        }

        // If there are any scheduled pre-checks or dry-runs, execute them. Fetch queue after each time because
        // other cron jobs might have picked something.
        while ($queue[self::Q_SCHEDULED_OTHER]) {
            $this->start_operation(reset($queue[self::Q_SCHEDULED_OTHER]));
            $queue = $this->get_queue();
        }

        // If there are no backups or restores in progress - start first scheduled backup (there should not be more than one).
        if ($queue[self::Q_SCHEDULED_BACKUPS] && empty($queue[self::Q_INPROGRESS])) {
            $this->start_operation(reset($queue[self::Q_SCHEDULED_BACKUPS]));
            $queue = $this->get_queue();
        }

        // If there are no backups or restores in progress - start first scheduled restore (there should not be more than one).
        if ($queue[self::Q_SCHEDULED_RESTORES] && empty($queue[self::Q_INPROGRESS])) {
            $this->start_operation(reset($queue[self::Q_SCHEDULED_RESTORES]));
        }
    }

    /**
     * Resume stuck backup or restore
     *
     * @param operation_model $model
     * @return void
     */
    protected function resume_operation(operation_model $model) {
        // TODO implement resume.
        $model->add_log('Operation timed out');
        $model->set_status(constants::STATUS_FAILED)->save();
    }

    /**
     * Execute a check, backup or restore
     *
     * @param operation_model $model
     * @return void
     */
    protected function start_operation(operation_model $model) {
        if ($model instanceof backup_model) {
            $operation = new site_backup($model);
        } else if ($model instanceof restore_model) {
            $operation = new site_restore($model);
        } else if ($model instanceof dryrun_model) {
            $operation = new site_restore_dryrun($model);
        } else if ($model instanceof check_model) {
            try {
                $operation = check_base::load($model->id); // TODO expose instance.
            } catch (\Throwable $t) {
                $model->set_status(constants::STATUS_FAILEDTOSTART)->set_error($t)->save();
                return;
            }
        } else {
            $model->set_status(constants::STATUS_FAILEDTOSTART)->save();
            return;
        }

        try {
            if (method_exists($this, 'get_pid')) {
                $pid = (int)$this->get_pid();
            } else {
                $pid = (int)getmypid();
            }
            $operation->start($pid);
            $operation->execute();
        } catch (\Throwable $t) {
            $operation->mark_as_failed($t);
        }
    }
}
