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

namespace tool_vault\local\checks;

use tool_vault\constants;
use tool_vault\local\helpers\tempfiles;
use tool_vault\local\models\backup_file;
use tool_vault\local\models\dryrun_model;

/**
 * Check disk space on restore
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class diskspace_restore extends check_base_restore {

    /**
     * Evaluate check and store results in model details
     */
    public function perform(): void {
        /** @var dryrun_model $parent */
        $parent = $this->get_parent();
        $largestarchive = 0;
        $filetypes = [constants::FILENAME_DBSTRUCTURE, constants::FILENAME_DATAROOT, constants::FILENAME_FILEDIR,
            constants::FILENAME_DBDUMP, ];
        $origsizes = array_fill_keys($filetypes, 0);
        $sizes = array_fill_keys($filetypes, 0);
        foreach ($parent->get_files() as $file) {
            $bfile = backup_file::create($file);
            if (in_array($bfile->filetype, $filetypes)) {
                $largestarchive = max($largestarchive, $bfile->filesize + $bfile->origsize);
                $origsizes[$bfile->filetype] += $bfile->origsize;
                $sizes[$bfile->filetype] += $bfile->filesize;
            }
        }

        $mintmpspace = $largestarchive + $sizes[constants::FILENAME_DBSTRUCTURE] + $origsizes[constants::FILENAME_DBSTRUCTURE];

        $freespace = tempfiles::get_free_space($mintmpspace);

        $enoughspace = $freespace === true || $freespace > $mintmpspace;

        $this->model->set_details([
            'freespace' => $freespace,
            'enoughspace' => $enoughspace,
            'datarootsize' => $origsizes[constants::FILENAME_DATAROOT],
            'filedirsize' => $origsizes[constants::FILENAME_FILEDIR],
            'mintmpspace' => $mintmpspace,
            'dbtotalsize' => $parent->get_metadata()['dbtotalsize'] ?? 0,
        ])->save();
    }

    /**
     * Can backup be performed
     *
     * @return bool
     */
    public function success(): bool {
        return $this->model->status === constants::STATUS_FINISHED
            && $this->model->get_details()['enoughspace'];
    }

    /**
     * Should we display a warning
     *
     * @return bool
     */
    protected function is_warning(): bool {
        $details = $this->model->get_details();
        return $this->success() &&
            ($details['freespace'] !== true &&
                ($details['datarootsize'] ?? 0) + ($details['filedirsize'] ?? 0) +
                ($details['mintmpspace'] ?? 0) > $details['freespace']);
    }

    /**
     * Status message
     *
     * @return string
     */
    public function get_status_message(): string {
        return $this->success() ?
            ($this->is_warning() ?
                get_string('diskspacerestore_success_warning', 'tool_vault') :
                get_string('diskspacerestore_success', 'tool_vault')) :
            get_string('diskspacerestore_fail', 'tool_vault');
    }

    /**
     * Get summary of the past check
     *
     * @return string
     */
    public function summary(): string {
        if ($this->model->status !== constants::STATUS_FINISHED) {
            return '';
        }
        $details = $this->model->get_details();
        return
            $this->display_status_message($this->get_status_message(), $this->is_warning()).
            '<ul>'.
            ($details['freespace'] !== true ?
            ('<li>'.get_string('diskspacebackup_freespace', 'tool_vault') . ': '.
                display_size($details['freespace']).'</li>') : '').
            '<li>'.get_string('diskspacerestore_mintmpspace', 'tool_vault') . ': '.
                display_size($details['mintmpspace'] ?? 0).'</li>'.
            '<li>'.get_string('diskspacebackup_datarootsize', 'tool_vault') . ': '.
                display_size($details['datarootsize'] ?? 0).'</li>'.
            '<li>'.get_string('diskspacerestore_filedirsize', 'tool_vault') . ': '.
                display_size($details['filedirsize'] ?? 0).'</li>'.
            '<li>'.get_string('diskspacebackup_dbtotalsize', 'tool_vault') . ' (*): '.
                display_size($details['dbtotalsize'] ?? 0).'</li>'.
            '</ul>'.
            '<p>(*) ' . get_string('diskspacerestore_dbtotalsizefootnote', 'tool_vault') . '</p>';
    }

    /**
     * Does this past check have details (to display a link "Show details")
     *
     * @return bool
     */
    public function has_details(): bool {
        return false;
    }

    /**
     * Get detailed report of the past check
     *
     * @return string
     */
    public function detailed_report(): string {
        return '';
    }

    /**
     * Display name of this check
     *
     * @return string
     */
    public static function get_display_name(): string {
        return get_string('diskspacerestore', 'tool_vault');
    }
}
