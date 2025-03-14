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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_vault\output;

use renderer_base;
use stdClass;
use tool_vault\constants;
use tool_vault\local\helpers\ui;
use tool_vault\local\models\backup_model;
use tool_vault\local\models\dryrun_model;
use tool_vault\local\models\operation_model;
use tool_vault\local\models\restore_model;
use tool_vault\site_restore_dryrun;

/**
 * Last operation
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class last_operation implements \templatable {
    /** @var operation_model */
    protected $operation;
    /** @var string */
    protected $text;
    /** @var string */
    protected $title;
    /** @var \moodle_url */
    protected $detailsurl;
    /** @var bool */
    protected $isfailed = false;

    /**
     * Constructor
     *
     * @param operation_model $operation
     */
    public function __construct(operation_model $operation) {
        $this->operation = $operation;
        if ($operation instanceof backup_model) {
            if ($operation->status === constants::STATUS_SCHEDULED) {
                $this->title = get_string('lastop_backupscheduled_header', 'tool_vault');
                $this->text = get_string('lastop_backupscheduled_text', 'tool_vault');
            } else if ($operation->status === constants::STATUS_INPROGRESS) {
                $this->title = get_string('lastop_backupinprogress_header', 'tool_vault');
                $this->text = get_string('lastop_backupinprogress_text', 'tool_vault');
            } else if ($operation->status === constants::STATUS_FINISHED) {
                $this->title = get_string('lastop_backupfinished_header', 'tool_vault');
                $this->text = get_string('lastop_backupfinished_text', 'tool_vault',
                (object)['backupkey' => $this->operation->backupkey,
                    'started' => ui::format_time($this->operation->timecreated),
                    'finished' => ui::format_time($this->operation->get_finished_time())]);
            } else {
                $this->title = get_string('lastop_backupfailed_header', 'tool_vault');
                $this->text = get_string('lastop_backupfailed_text', 'tool_vault', ui::format_time($this->operation->timecreated));
            }
            if ($operation->status === constants::STATUS_INPROGRESS || $operation->status === constants::STATUS_SCHEDULED) {
                $this->detailsurl = new \moodle_url('/admin/tool/vault/progress.php', ['accesskey' => $operation->accesskey]);
            } else {
                $this->detailsurl = \tool_vault\local\uiactions\backup_details::url(['id' => $operation->id]);
            }

        } else if ($operation instanceof restore_model) {
            $this->title = $operation->get_title();
            $this->text = $operation->get_subtitle();
            if ($operation->status === constants::STATUS_SCHEDULED) {
                $this->title = get_string('lastop_restorescheduled_header', 'tool_vault');
                $this->text = get_string('lastop_restorescheduled_text', 'tool_vault');
            } else if ($operation->status === constants::STATUS_INPROGRESS) {
                $this->title = get_string('lastop_restoreinprogress_header', 'tool_vault');
                $this->text = get_string('lastop_restoreinprogress_text', 'tool_vault');
            } else if ($operation->status === constants::STATUS_FINISHED) {
                $this->title = get_string('lastop_restorefinished_header', 'tool_vault');
                $this->text = get_string('lastop_restorefinished_text', 'tool_vault',
                    (object)['backupkey' => $this->operation->backupkey,
                    'started' => ui::format_time($this->operation->timecreated),
                    'finished' => ui::format_time($this->operation->get_finished_time())]);
            } else {
                $this->title = get_string('lastop_restorefailed_header', 'tool_vault');
                $this->text = get_string('lastop_restorefailed_text', 'tool_vault', ui::format_time($this->operation->timecreated));
                if ($operation->can_resume()) {
                    $this->text .= '<br><b>' . get_string('lastop_restorefailed_canberesumed', 'tool_vault').'</b>';
                }
            }
            if ($operation->status === constants::STATUS_INPROGRESS || $operation->status === constants::STATUS_SCHEDULED) {
                $this->detailsurl = new \moodle_url('/admin/tool/vault/progress.php', ['accesskey' => $operation->accesskey]);
            } else {
                $this->detailsurl = \tool_vault\local\uiactions\restore_details::url(['id' => $operation->id]);
            }

        } else if ($operation instanceof dryrun_model) {
            if ($operation->status === constants::STATUS_SCHEDULED) {
                $this->title = get_string('lastop_restoreprecheckscheduled_header', 'tool_vault');
                $this->text = get_string('lastop_restoreprecheckscheduled_text', 'tool_vault');
            } else if ($operation->status === constants::STATUS_INPROGRESS) {
                $this->title = get_string('lastop_restoreprecheckinprogress_header', 'tool_vault');
                $this->text = get_string('lastop_restoreprecheckinprogress_text', 'tool_vault');
            } else if ($operation->status === constants::STATUS_FINISHED) {
                $this->title = get_string('lastop_restoreprecheckfinished_header', 'tool_vault');
                $this->text = get_string('lastop_restoreprecheckfinished_text', 'tool_vault',
                    (object)['finished' => ui::format_time($operation->get_finished_time()), 'backupkey' => $operation->backupkey]);
            } else {
                $this->title = get_string('lastop_restoreprecheckfailed_header', 'tool_vault');
                $this->text = get_string('lastop_restoreprecheckfailed_text', 'tool_vault',
                    ui::format_time($operation->get_finished_time()));
            }
            $this->detailsurl = \tool_vault\local\uiactions\restore_details::url(['id' => $operation->id]);
        }
    }

    /**
     * Show with success CSS class
     *
     * @return bool
     */
    protected function is_success(): bool {
        return !$this->isfailed && $this->operation->status === constants::STATUS_FINISHED;
    }

    /**
     * Show with the error CSS class
     *
     * @return bool
     */
    protected function is_error(): bool {
        return $this->isfailed ||
            $this->operation->status === constants::STATUS_FAILED ||
            $this->operation->status === constants::STATUS_FAILEDTOSTART;
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
        $rv = [
            'class' => $this->is_success() ? 'success' : ($this->is_error() ? 'danger' : 'info'),
            'title' => $this->title ?? get_class($this->operation),
            'text' => $this->text,
            'detailsurl' => $this->detailsurl ? ($this->detailsurl->out(false)) : '',
        ];
        return $rv;
    }
}
