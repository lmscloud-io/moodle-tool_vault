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

use tool_vault\local\checks\base;
use tool_vault\local\checks\diskspace_restore;
use tool_vault\local\checks\version_restore;
use tool_vault\local\models\dryrun;
use tool_vault\local\models\remote_backup;
use tool_vault\local\models\restore;
use tool_vault\task\dryrun_task;

/**
 * Site restore pre-checks only
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class site_restore_dryrun implements local\logger {

    /** @var dryrun */
    protected $dryrun;
    /** @var remote_backup */
    protected $remotebackup;
    /** @var base[] */
    protected $prechecks = null;

    /**
     * Constructor
     *
     * @param dryrun $dryrun
     */
    public function __construct(dryrun $dryrun) {
        $this->dryrun = $dryrun;
    }

    /**
     * Last dryrun
     *
     * @param string $backupkey
     * @return static|null
     */
    public static function get_last_dryrun(string $backupkey): ?self {
        if ($model = dryrun::get_last_dry_run($backupkey)) {
            return new static($model);
        }
        return null;
    }

    /**
     * Schedule dry-run
     *
     * @param string $backupkey
     * @return void
     */
    public static function schedule_dryrun(string $backupkey) {
        $backupmetadata = api::get_remote_backup($backupkey, constants::STATUS_FINISHED);
        $dryrun = new dryrun();
        $dryrun
            ->set_status( constants::STATUS_SCHEDULED)
            ->set_backupkey($backupkey)
            ->set_remote_details((array)$backupmetadata->to_object())
            ->save();
        $dryrun->add_log("Pre-check scheduled");
        dryrun_task::schedule();
    }

    /**
     * Start scheduled dry-run
     *
     * @param int $pid
     * @return static
     */
    public static function start_dryrun(int $pid): self {
        if (!api::is_registered()) {
            throw new \moodle_exception('API key not found');
        }
        $records = dryrun::get_records([constants::STATUS_SCHEDULED]);
        if (!$records) {
            throw new \moodle_exception('No pre-checks scheduled');
        }
        $dryrun = reset($records);
        $dryrun->set_pid_for_logging($pid);
        return new static($dryrun);
    }

    /**
     * Perform dry-run
     *
     * @return void
     * @throws \moodle_exception
     */
    public function execute() {
        $this->dryrun
            ->set_status(constants::STATUS_INPROGRESS)
            ->save();
        $this->add_to_log('Started');

        $backupmetadata = api::get_remote_backup($this->dryrun->backupkey, constants::STATUS_FINISHED);

        $dir = make_request_directory();
        $zippath = $dir.DIRECTORY_SEPARATOR.constants::FILENAME_DBSTRUCTURE.'.zip';
        api::download_backup_file($this->dryrun->backupkey, $zippath, $this);
        $zippacker = new \zip_packer();
        $zippacker->extract_to_pathname($zippath, $dir);

        $remotedetails = (array)$backupmetadata->to_object();
        $remotedetails['dbstructure'] = file_get_contents($dir.DIRECTORY_SEPARATOR.constants::FILE_STRUCTURE);
        $remotedetails['metadata'] = json_decode(file_get_contents($dir.DIRECTORY_SEPARATOR.constants::FILE_METADATA), true);
        $this->dryrun
            ->set_remote_details($remotedetails)
            ->save();

        // TODO...

        /** @var base[] $precheckclasses */
        $precheckclasses = [
            version_restore::class,
            diskspace_restore::class,
        ];
        $this->prechecks = [];
        foreach ($precheckclasses as $classname) {
            $this->add_to_log('Running pre-check: '.$classname::get_display_name().'...');
            if (($chk = $classname::create_and_run($this->dryrun)) && $chk->success()) {
                $this->prechecks[$chk->get_name()] = $chk;
                $this->add_to_log('...OK');
            } else {
                throw new \moodle_exception('...'.$classname::get_display_name().' failed');
            }
        }

        $this->dryrun->set_status(constants::STATUS_FINISHED)->save();
        $this->add_to_log('Finished');
    }

    /**
     * Mark as failed
     *
     * @param \Throwable $t
     * @return void
     */
    public function mark_as_failed(\Throwable $t) {
        $this->dryrun->set_status(constants::STATUS_FAILED)->save();
        $this->add_to_log('Pre-check failed: '.$t->getMessage(), constants::LOGLEVEL_ERROR);
    }

    /**
     * Log action
     *
     * @package tool_vault
     * @param string $message
     * @param string $loglevel
     * @return void
     */
    public function add_to_log(string $message, string $loglevel = constants::LOGLEVEL_INFO) {
        if ($this->dryrun && $this->dryrun->id) {
            $logrecord = $this->dryrun->add_log($message, $loglevel);
            if (!(defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
                mtrace($this->dryrun->format_log_line($logrecord, false));
            }
        }
    }

    /**
     * Model
     *
     * @return dryrun
     */
    public function get_model(): dryrun {
        return $this->dryrun;
    }

    /**
     * Get all prechecks
     *
     * @return base[]
     */
    public function get_prechecks(): array {
        if ($this->prechecks === null) {
            $this->prechecks = base::get_all_checks_for_operation($this->get_model()->id);
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
