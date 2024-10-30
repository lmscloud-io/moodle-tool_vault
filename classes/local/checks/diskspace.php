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

use tool_vault\api;
use tool_vault\constants;
use tool_vault\local\helpers\plugincode;
use tool_vault\local\helpers\siteinfo;
use tool_vault\local\helpers\tempfiles;
use tool_vault\local\xmldb\dbstructure;

// phpcs:disable Generic.CodeAnalysis.EmptyStatement.DetectedCatch

/**
 * Check disk space
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class diskspace extends check_base {
    /** @var array  */
    protected $tablesizes = [];
    /** @var array  */
    protected $tablerowscnt = [];

    /**
     * Evaluate check and store results in model details
     */
    public function perform(): void {
        global $DB;
        $record = $DB->get_record_sql('SELECT sum(filesize) AS sumfilesize, max(filesize) AS maxfilesize, count(1) AS countfiles
            FROM (SELECT distinct contenthash, filesize
                from {files}
                WHERE not (component=? AND filearea = ?)) a',
            ['user', 'draft']);
        $totalsize = $record->sumfilesize;
        $maxfilesize = $record->maxfilesize;
        $countfiles = $record->countfiles;
        $structure = dbstructure::load();
        foreach ($structure->get_tables_actual() as $tablename => $table) {
            $deftable = $structure->find_table_definition($tablename);
            if (!siteinfo::is_table_excluded_from_backup($tablename, $deftable)) {
                try {
                    // Mdlcode-disable-next-line cannot-parse-db-tablename.
                    $this->tablerowscnt[$tablename] = $DB->count_records_select($tablename, '1=1');
                } catch (\Throwable $e) {
                    // An exception can occur if $tablename contains invalid characters. It will
                    // be reported in the dbstatus check, so just ignore it here.
                }
            }
        }
        $dbrecords = array_sum($this->tablerowscnt);
        $this->tablesizes = array_intersect_key($structure->get_actual_tables_sizes(), $this->tablerowscnt);
        $dbtotalsize = array_sum($this->tablesizes);
        $dbmaxsize = max($this->tablesizes);
        [$datarootsize, $maxdatarootfilesize, $datarootunreadable] = $this->get_dataroot_size();
        $includecodesize = (!$this->parent && api::allow_backup_plugincode() >= 0 ) ||
            ($this->parent && !empty($this->parent->get_details()['backupplugincode']));
        $codesize = $includecodesize ? plugincode::get_total_addon_size() : null;

        // This is a rough estimate!
        // There should be enough space to archive the largest file. In the worst case we already have almost
        // constants::UPLOAD_SIZE of files prepared and then add the largest file/table. After that we archive
        // them and in the worst case the archive is the same size as the original.
        $requiredspacefiles = min($totalsize, constants::UPLOAD_SIZE + $maxfilesize) * 2;
        $requiredspacedb = min($dbtotalsize, constants::UPLOAD_SIZE + $dbmaxsize) * 2;
        $requiredspacedataroot = min($datarootsize, constants::UPLOAD_SIZE + $maxdatarootfilesize) * 2;
        $requiredspace = max($requiredspacefiles, $requiredspacedb, $requiredspacedataroot, $codesize * 2);
        $freespace = tempfiles::get_free_space($requiredspace);
        $enoughspace = $requiredspace < $freespace;

        // Save results.
        $this->model->set_details([
            'totalfilesize' => $totalsize,
            'maxfilesize' => $maxfilesize,
            'countfiles' => $countfiles,
            'freespace' => $freespace,
            'dbrecords' => $dbrecords,
            'dbtotalsize' => $dbtotalsize,
            'dbmaxsize' => $dbmaxsize,
            'datarootsize' => $datarootsize,
            'pluginscount' => plugincode::get_addon_plugins_count(),
            'codesize' => $codesize,
            'maxdatarootfilesize' => $maxdatarootfilesize,
            'datarootunreadable' => $datarootunreadable,
            'enoughspace' => $enoughspace,
        ])->save();
    }

    /**
     * Calculate total size of files in dataroot
     *
     * @return array [$totalsize, $maxfilesize]
     */
    protected function get_dataroot_size() {
        global $CFG;
        $handle = opendir($CFG->dataroot);
        $size = 0;
        $maxfile = 0;
        $unreadable = [];
        while (($file = readdir($handle)) !== false) {
            if (!siteinfo::is_dataroot_path_skipped_backup($file) && $file !== '.' && $file !== '..') {
                $filepath = $CFG->dataroot . DIRECTORY_SEPARATOR . $file;
                if (!is_readable($filepath)) {
                    $unreadable[] = $file;
                } else if (is_dir($filepath)) {
                    try {
                        $it = new \RecursiveDirectoryIterator($filepath, \RecursiveDirectoryIterator::SKIP_DOTS);
                        $allfiles = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::LEAVES_ONLY);
                        foreach ($allfiles as $f) {
                            if (is_readable($f->getRealpath())) {
                                $thissize = $f->getSize();
                                $size += $thissize;
                                $maxfile = max($maxfile, $thissize);
                            } else {
                                $unreadable[] = $file;
                                break;
                            }
                        }
                    } catch (\Exception $e) {
                        $unreadable[] = $file;
                    }
                } else if (is_file($filepath)) {
                    $thissize = filesize($filepath);
                    $size += $thissize;
                    $maxfile = max($maxfile, $thissize);
                }
            }
        }
        closedir($handle);
        return [$size, $maxfile, $unreadable];
    }

    /**
     * Can backup be performed
     *
     * @return bool
     */
    public function success(): bool {
        return $this->model->status === constants::STATUS_FINISHED
            && empty($this->model->get_details()['datarootunreadable'])
            && $this->model->get_details()['enoughspace'];
    }

    /**
     * Status message
     *
     * @return string
     */
    public function get_status_message(): string {
        if ($this->success()) {
            return get_string('diskspacebackup_success', 'tool_vault');
        } else if (!empty($this->model->get_details()['datarootunreadable'])) {
            $a = (object)[
                'paths' => join(', ', $this->model->get_details()['datarootunreadable']),
                'settingsurl' => (new \moodle_url('/admin/settings.php', ['section' => 'tool_vault']))->out(),
            ];
            return get_string('diskspacebackup_fail_datarootunreadable', 'tool_vault', $a);
        } else {
            return get_string('diskspacebackup_fail', 'tool_vault');
        }
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
            $this->display_status_message($this->get_status_message()).
            '<ul>'.
            '<li>' . get_string('diskspacebackup_totalfilesize', 'tool_vault') . ': ' .
                display_size($details['totalfilesize']).'</li>'.
            '<li>' . get_string('diskspacebackup_maxfilesize', 'tool_vault') . ': ' .
                display_size($details['maxfilesize']).'</li>'.
            '<li>' . get_string('diskspacebackup_countfiles', 'tool_vault') . ': ' .
                number_format($details['countfiles'], 0).'</li>'.
            '<li>' . get_string('diskspacebackup_dbrecords', 'tool_vault') . ': ' .
                number_format($details['dbrecords'], 0).'</li>'.
            '<li>' . get_string('diskspacebackup_dbtotalsize', 'tool_vault') . ': ' .
                display_size($details['dbtotalsize']).'</li>'.
            '<li>' . get_string('diskspacebackup_dbmaxsize', 'tool_vault') . ': ' .
                display_size($details['dbmaxsize']).'</li>'.
            '<li>' . get_string('diskspacebackup_datarootsize', 'tool_vault') . ': ' .
                display_size($details['datarootsize']).'</li>'.
            (isset($details['codesize']) ?
                '<li>' . get_string('diskspacebackup_codesize', 'tool_vault', $details['pluginscount']) . ': '.
                    display_size($details['codesize']) . '</li>' :
                '').
            '<li>' . get_string('diskspacebackup_maxdatarootfilesize', 'tool_vault') . ': ' .
                display_size($details['maxdatarootfilesize'] ?? 0).'</li>'.
            ($details['freespace'] !== true ?
                ('<li>' . get_string('diskspacebackup_freespace', 'tool_vault') . ': ' .
                display_size($details['freespace']).'</li>') : '').
            '</ul>';
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
        return get_string('diskspacebackup', 'tool_vault');
    }

    /**
     * Get number of rows in the table and the table size
     *
     * @param string $tablename
     * @return array
     */
    public function get_table_size(string $tablename) {
        return [
            $this->tablerowscnt[$tablename] ?? 0,
            $this->tablesizes[$tablename] ?? 0,
        ];
    }
}
