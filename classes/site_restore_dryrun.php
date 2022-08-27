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

namespace tool_vault;

use tool_vault\local\checks\check_base;
use tool_vault\local\checks\diskspace_restore;
use tool_vault\local\checks\version_restore;
use tool_vault\local\helpers\files_restore;
use tool_vault\local\logger;
use tool_vault\local\models\dryrun_model;
use tool_vault\local\models\operation_model;
use tool_vault\local\models\restore_base_model;
use tool_vault\local\operations\operation_base;

/**
 * Site restore pre-checks only
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class site_restore_dryrun extends operation_base {

    /** @var dryrun_model */
    protected $model;
    /** @var check_base[] */
    protected $prechecks = null;

    /**
     * Constructor
     *
     * @param dryrun_model $model
     */
    public function __construct(dryrun_model $model) {
        $this->model = $model;
    }

    /**
     * Last dryrun
     *
     * @param string $backupkey
     * @return static|null
     */
    public static function get_last_dryrun(string $backupkey): ?self {
        if ($model = dryrun_model::get_last_dry_run($backupkey)) {
            return new static($model);
        }
        return null;
    }

    /**
     * Schedule dry-run
     *
     * @param array $params ['backupkey' => ?, 'passphrase' => ?]
     * @return static
     */
    public static function schedule(array $params = []): operation_base {
        if (empty($params['backupkey'])) {
            throw new \coding_exception('Parameter backupkey is required for site_restore_dryrun::schedule()');
        }
        $backupkey = $params['backupkey'];
        $encryptionkey = api::prepare_encryption_key($params['passphrase'] ?? '');
        $dryrun = new dryrun_model();
        $dryrun
            ->set_status( constants::STATUS_SCHEDULED)
            ->set_details(['encryptionkey' => $encryptionkey])
            ->set_backupkey($backupkey)
            ->save();
        $dryrun->add_log("Restore pre-check scheduled");
        return new static($dryrun);
    }

    /**
     * Start scheduled dry-run
     *
     * @param int $pid
     */
    public function start(int $pid) {
        if (!api::is_registered()) {
            throw new \moodle_exception('errorapikeynotvalid', 'tool_vault');
        }
        parent::start($pid);
    }

    /**
     * Runs all pre-checks (executed from both "dryrun" and the actual restore)
     *
     * @param files_restore $restorehelper
     * @param restore_base_model $model
     * @param logger $logger
     * @param bool $exceptiononfailure throw exception if one of the prechecks fails
     * @return check_base[] array of executed prechecks
     */
    public static function execute_prechecks(files_restore $restorehelper, restore_base_model $model,
                                             logger $logger, bool $exceptiononfailure = false): array {
        $backupmetadata = api::get_remote_backup($model->backupkey, constants::STATUS_FINISHED);

        files_restore::populate_backup_files($model->id, $backupmetadata->files ?? []);
        if (!$restorehelper->has_known_archives()) {
            $restorehelper->rescan_files_from_db();
        }

        $files = $restorehelper->get_all_files();

        if (!array_key_exists(constants::FILE_STRUCTURE, $files)) {
            throw new \moodle_exception('Archive '.constants::FILENAME_DBSTRUCTURE.'.zip does not contain database structure');
        }
        if (!array_key_exists(constants::FILE_METADATA, $files)) {
            throw new \moodle_exception('Archive '.constants::FILENAME_DBSTRUCTURE.'.zip does not contain backup metadata');
        }

        $remotedetails = (array)$backupmetadata->to_object();
        $remotedetails['dbstructure'] = file_get_contents($files[constants::FILE_STRUCTURE]);
        $remotedetails['metadata'] = json_decode(file_get_contents($files[constants::FILE_METADATA]), true);
        $model
            ->set_remote_details($remotedetails)
            ->save();

        /** @var check_base[] $precheckclasses */
        $precheckclasses = [
            version_restore::class,
            diskspace_restore::class,
        ];
        $prechecks = [];
        foreach ($precheckclasses as $classname) {
            $logger->add_to_log('Restore pre-check: '.$classname::get_display_name().'...');
            $chk = $classname::create_and_run($model);
            $prechecks[$chk->get_name()] = $chk;
            if ($chk->success()) {
                $logger->add_to_log('...OK');
            } else if ($exceptiononfailure) {
                $restorehelper->finish();
                throw new \moodle_exception('... pre-check '.$classname::get_display_name().' failed');
            } else {
                $logger->add_to_log('... pre-check '.$classname::get_display_name().' failed');
            }
        }
        return $prechecks;
    }

    /**
     * Perform dry-run
     *
     * @return void
     * @throws \moodle_exception
     */
    public function execute() {
        $this->model
            ->set_status(constants::STATUS_INPROGRESS)
            ->save();
        $this->add_to_log('Restore pre-check started');

        $helper = new files_restore($this, constants::FILENAME_DBSTRUCTURE);
        $this->prechecks = self::execute_prechecks($helper, $this->model, $this);
        $helper->finish();

        $this->model
            ->set_status(constants::STATUS_FINISHED)
            ->set_details(['encryptionkey' => ''])
            ->save();
        $this->add_to_log('Restore pre-check finished');
    }

    /**
     * Model
     *
     * @return dryrun_model
     */
    public function get_model(): operation_model {
        return $this->model;
    }

    /**
     * Get all prechecks
     *
     * @return check_base[]
     */
    public function get_prechecks(): array {
        if ($this->prechecks === null) {
            $this->prechecks = check_base::get_all_checks_for_operation($this->model->id);
        }
        return $this->prechecks;
    }

    /**
     * All precheckes have passed
     *
     * @return bool
     */
    public function prechecks_succeeded(): bool {
        foreach ($this->get_prechecks() as $precheck) {
            if (!$precheck->success()) {
                return false;
            }
        }
        return true;
    }
}
