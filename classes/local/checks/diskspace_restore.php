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

namespace tool_vault\local\checks;

use tool_vault\constants;
use tool_vault\local\models\backup_file;
use tool_vault\local\models\dryrun_model;
use tool_vault\local\xmldb\dbstructure;
use tool_vault\site_backup;
use tool_vault\site_restore;

/**
 * Check disk space on restore
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class diskspace_restore extends check_base {

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
            $largestarchive = max($largestarchive, $bfile->filesize + $bfile->origsize);
            $origsizes[$bfile->filetype] += $bfile->origsize;
            $sizes[$bfile->filetype] += $bfile->filesize;
        }

        $mintmpspace = $largestarchive + $sizes[constants::FILENAME_DBSTRUCTURE] + $origsizes[constants::FILENAME_DBSTRUCTURE];

        $freespace = disk_free_space(make_request_directory());

        $enoughspace = $freespace > $mintmpspace;

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
            (($details['datarootsize'] ?? 0) + ($details['filedirsize'] ?? 0) +
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
                'There is enough disk space in the temporary directory however there may not be '.
                'enough space for all files and dataroot if they are in the same local disk partition' :
                'There is enough disk space in the temporary directory to perform site restore') :
            'There is not enough disk space in the temporary directory to perform site restore';
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
            '<li>Free space in temp dir: '.display_size($details['freespace']).'</li>'.
            '<li>Minimum space required in temp dir: '.display_size($details['mintmpspace'] ?? 0).'</li>'.
            '<li>Required space for dataroot (excluding filedir): '.display_size($details['datarootsize'] ?? 0).'</li>'.
            '<li>Required space for files: '.display_size($details['filedirsize'] ?? 0).'</li>'.
            '<li>Required space for database (*): '.display_size($details['dbtotalsize'] ?? 0).'</li>'.
            '</ul>'.
            '<p>(*) Note, tool Vault is <b>not able to check</b> if there is enough space in the database to perform restore.</p>';
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
        return "Disk space";
    }
}
