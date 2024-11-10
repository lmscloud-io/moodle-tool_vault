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

namespace tool_vault;

use tool_vault\local\helpers\tempfiles;
use tool_vault\local\models\backup_dryrun_model;
use tool_vault\local\models\backup_model;
use tool_vault\local\operations\operation_base;

/**
 * Perform site backup pre-check (only used in CLI)
 *
 * @package    tool_vault
 * @copyright  2024 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class site_backup_dryrun extends site_backup {
    /** @var backup_model */
    protected $model;

    /**
     * Constructor
     *
     * @param backup_dryrun_model $model
     */
    public function __construct(backup_dryrun_model $model) {
        $this->model = $model;
    }

    /**
     * Schedules new backup
     *
     * @param array $params
     * @return operation_base
     */
    public static function schedule(array $params = []): operation_base {
        global $USER;

        if (backup_model::get_records([constants::STATUS_INPROGRESS, constants::STATUS_SCHEDULED])) {
            throw new \moodle_exception('error_anotherbackupisinprogress', 'tool_vault');
        }

        $model = new backup_dryrun_model((object)[]);
        $model->set_status(constants::STATUS_SCHEDULED)->set_details([
            'usercreated' => $USER->id,
            'fullname' => $USER ? fullname($USER) : '',
            'email' => $USER->email ?? '',
            'precheckonly' => true,
        ])->save();
        $model->add_log("Backup pre-check scheduled");
        return new static($model);
    }

    /**
     * Start backup
     *
     * @param int $pid
     */
    public function start(int $pid) {
        if (!api::is_registered()) {
            throw new \moodle_exception('error_apikeynotvalid', 'tool_vault');
        }
        $this->model->set_pid_for_logging($pid);
        $x = api::precheck_backup_allowed();
        $this->model
            ->set_status(constants::STATUS_INPROGRESS)
            ->set_details(['precheckresults' => $x])
            ->save();
    }

    /**
     * Execute backup
     *
     * @return void
     */
    public function execute() {
        if (!$this->model || $this->model->status !== constants::STATUS_INPROGRESS) {
            throw new \moodle_exception('error_backupinprogressnotfound', 'tool_vault');
        }

        $this->prepare();
        $this->model
            ->set_status(constants::STATUS_FINISHED)
            ->save();
        tempfiles::cleanup();
    }
}
