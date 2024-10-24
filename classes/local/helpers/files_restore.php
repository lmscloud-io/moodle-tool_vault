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

namespace tool_vault\local\helpers;

use tool_vault\api;
use tool_vault\constants;
use tool_vault\local\models\backup_file;
use tool_vault\local\operations\operation_base;
use tool_vault\site_restore;

/**
 * Helper class for files restore
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class files_restore {
    /** @var site_restore */
    protected $siterestore;
    /** @var string */
    protected $filetype;
    /** @var backup_file[] */
    protected $backupfiles = [];
    /** @var int  */
    protected $currentseq = -1;
    /** @var array  */
    protected $curentfileslist = [];
    /** @var array  */
    protected $curenttables = [];
    /** @var int  */
    protected $nextfileidx = 0;
    /** @var ?string  */
    protected $dir = null;
    /** @var \tool_vault\local\xmldb\dbstructure|null */
    protected $dbstructure = null;

    /**
     * Constructor
     *
     * @param operation_base $siterestore
     * @param string $filetype
     */
    public function __construct(operation_base $siterestore, string $filetype) {
        $this->filetype = $filetype;
        $this->siterestore = $siterestore;
        if ($this->is_dbdump_backup()) {
            if ($siterestore instanceof site_restore) {
                $this->dbstructure = $siterestore->get_db_structure();
                if (!$this->dbstructure) {
                    throw new \coding_exception('DB structure is not available');
                }
            } else {
                throw new \coding_exception('Files restore helper for dbdump can only be used from site_restore');
            }
        }
        $this->rescan_files_from_db();
    }

    /**
     * Re-scan the backup files from the DB
     *
     * @return void
     */
    public function rescan_files_from_db() {
        global $DB;
        $this->backupfiles = [];
        $this->currentseq = -1;
        $records = $DB->get_records_select(backup_file::TABLE, "operationid = ? AND filetype = ?",
            [$this->siterestore->get_model()->id, $this->filetype], 'seq, id');
        foreach ($records as $record) {
            $backupfile = new backup_file((array)$record);
            $this->backupfiles[$backupfile->seq] = $backupfile;
        }
        if (!$this->is_pluginscode_backup()) {
            $this->open_next_archive();
        }
    }

    /**
     * Are there any archives of this type in the DB
     *
     * @return bool
     */
    public function has_known_archives(): bool {
        return !empty($this->backupfiles);
    }

    /**
     * Are we on the very first backup archive (which means we are starting restore for the first time and not resuming)
     *
     * @return bool
     */
    public function is_first_archive(): bool {
        return $this->backupfiles && $this->currentseq == key($this->backupfiles);
    }

    /**
     * Helper method that syncs files info received from the API with what we store in the DB
     *
     * @param int $operationid
     * @param array $files
     * @return void
     */
    public static function populate_backup_files(int $operationid, array $files) {
        global $DB;
        $existing = [];
        $records = $DB->get_records_select(backup_file::TABLE, "operationid = ?",
            [$operationid], 'filetype, seq, id');
        foreach ($records as $record) {
            $existing[] = new backup_file((array)$record);
        }

        foreach ($files as $file) {
            $extra = [
                'operationid' => $operationid,
                'status' => constants::STATUS_SCHEDULED,
            ];
            if (!$backupfile = backup_file::create($extra + $file)) {
                continue;
            }
            foreach ($existing as $eb) {
                if ($eb->filetype === $backupfile->filetype && $eb->seq == $backupfile->seq) {
                    continue 2;
                }
            }
            $backupfile->save();
        }
    }

    /**
     * DB backups also need to store list of tables
     *
     * @return bool
     */
    protected function is_dbdump_backup(): bool {
        return $this->filetype === constants::FILENAME_DBDUMP;
    }

    /**
     * DBstructure backup
     *
     * @return bool
     */
    protected function is_dbstructure_backup(): bool {
        return $this->filetype === constants::FILENAME_DBSTRUCTURE;
    }

    /**
     * Dataroot backup
     *
     * @return bool
     */
    protected function is_dataroot_backup(): bool {
        return $this->filetype === constants::FILENAME_DATAROOT;
    }

    /**
     * Plugins code backup
     *
     * @return bool
     */
    protected function is_pluginscode_backup(): bool {
        return $this->filetype === constants::FILENAME_PLUGINSCODE;
    }

    /**
     * Returns all files extracted from archive (only for dbstructure)
     *
     * @return array
     */
    public function get_all_files(): array {
        if (!$this->is_dbstructure_backup()) {
            throw new \coding_exception('Can only be called for the dbstructure file type');
        }
        if (!$this->dir) {
            throw new \coding_exception('There is no open archive');
        }
        $files = [];
        foreach ($this->curentfileslist as $localfilename) {
            $files[$localfilename] = $this->dir.DIRECTORY_SEPARATOR.$localfilename;
        }
        return $files;
    }

    /**
     * Returns the next file in the backup.
     *
     * For dataroot only returns top-level files and directories, for filedir returns all files with paths
     *
     * @return array|null [full path on disk, local path in archive]
     */
    public function get_next_file(): ?array {
        if ($this->is_dbdump_backup()) {
            throw new \coding_exception('Can not be called for the dbdump file type');
        }
        if (($localpath = $this->get_next()) === null) {
            return null;
        }
        if (!$this->dir) {
            throw new \coding_exception('There is no open archive');
        }
        return [$this->dir.DIRECTORY_SEPARATOR.$localpath, $localpath];
    }

    /**
     * Returns the next file in the backup
     *
     * @return array|null [full path on disk, local path in archive]
     */
    protected function get_next(): ?string {
        if (!count($this->curentfileslist)) {
            return null;
        }
        if ($this->nextfileidx == count($this->curentfileslist)) {
            $this->close_current_archive();
            if (!$this->open_next_archive()) {
                return null;
            }
        }
        return $this->curentfileslist[$this->nextfileidx++];
    }

    /**
     * Total unpacked size of all archives of this type
     *
     * @return int
     */
    public function get_total_orig_size(): int {
        $size = 0;
        foreach ($this->backupfiles as $file) {
            $size += $file->origsize;
        }
        return $size;
    }

    /**
     * Returns the next table in the backup file and all files
     *
     * @return array|null [table name, [path to file with json dump]]
     */
    public function get_next_table(): ?array {
        if (!$this->is_dbdump_backup()) {
            throw new \coding_exception('Can only be called for the dbdump file type');
        }
        if (($tablename = $this->get_next()) === null) {
            return null;
        }
        if (!$this->dir) {
            throw new \coding_exception('There is no open archive');
        }
        $tablefiles = array_map(function($file) {
            return $this->dir.DIRECTORY_SEPARATOR.$file;
        }, $this->curenttables[$tablename] ?? []);
        return [$tablename, $tablefiles];
    }

    /**
     * Table name from the filename in the dbdump backup
     *
     * @param string $path
     * @return string
     */
    protected function tablename(string $path): string {
        return preg_replace('/\\.[\d]+$/', '', pathinfo($path, PATHINFO_FILENAME));
    }

    /**
     * Next archive file seq
     *
     * @return int|null
     */
    protected function find_next_seq(): ?int {
        foreach ($this->backupfiles as $i => $backupfile) {
            if ($i > $this->currentseq && $backupfile->status !== constants::STATUS_FINISHED) {
                return $i;
            }
        }
        return null;
    }

    /**
     * Creates an array of files from the backup archive
     *
     * Special magic for dbdump files where it groups by tables
     *
     * @param string $subpath
     * @return void
     */
    protected function retrieve_files_list(string $subpath = '') {
        if (!$this->dir) {
            throw new \coding_exception('There is no open archive');
        }
        $path = $this->dir . DIRECTORY_SEPARATOR . $subpath;

        // Get list of files recursively.
        $it = new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS);
        $mode = $this->is_dataroot_backup() ? \RecursiveIteratorIterator::SELF_FIRST : \RecursiveIteratorIterator::LEAVES_ONLY;
        /** @var \RecursiveDirectoryIterator $itfiles */
        $itfiles = new \RecursiveIteratorIterator($it, $mode);
        $this->curentfileslist = [];
        foreach ($itfiles as $file) {
            $this->curentfileslist[] = $itfiles->getSubPathName();
        }

        // Sorting and grouping magic.
        if ($this->is_dbdump_backup()) {
            $this->curenttables = array_fill_keys(array_keys($this->dbstructure->get_backup_tables()), []);
            foreach ($this->curentfileslist as $localpath) {
                if (array_key_exists($this->tablename($localpath), $this->curenttables)) {
                    $this->curenttables[$this->tablename($localpath)][] = $localpath;
                }
            }
            foreach ($this->curenttables as $tablename => &$fileslist) {
                usort($fileslist, function ($file1, $file2) {
                    $ext1 = (int)pathinfo(pathinfo($file1, PATHINFO_FILENAME), PATHINFO_EXTENSION);
                    $ext2 = (int)pathinfo(pathinfo($file2, PATHINFO_FILENAME), PATHINFO_EXTENSION);
                    return $ext1 - $ext2;
                });
            }
            $this->curentfileslist = array_keys(array_filter($this->curenttables));
        } else {
            sort($this->curentfileslist, SORT_STRING);
        }
    }

    /**
     * Download archive from API
     *
     * @param string $tempdir
     * @return string
     * @throws \moodle_exception
     */
    protected function download_backup_file(string $tempdir): string {
        $zippath = $tempdir . DIRECTORY_SEPARATOR . $this->backupfiles[$this->currentseq]->get_file_name();
        api::download_backup_file($this->siterestore->get_model(), $zippath, $this->siterestore);
        return $zippath;
    }

    /**
     * Open next archive
     *
     * @return bool
     * @throws \moodle_exception
     */
    protected function open_next_archive(): bool {
        global $CFG;
        $this->currentseq = $this->find_next_seq();
        if ($this->currentseq === null) {
            return false;
        }
        if ($this->dir) {
            debugging('Archive opened without closing the previous one', DEBUG_DEVELOPER);
        }
        $this->dir = null;
        if ($this->is_dataroot_backup()) {
            // It is better to unzip the dataroot backup straight into dataroot directory so we can then
            // move them during restore faster (tmp path can be in a different filesystem/partition).
            try {
                $basedir = $CFG->dataroot.DIRECTORY_SEPARATOR.'__vault_restore__';
                if (!file_exists($basedir)) {
                    make_writable_directory($basedir);
                }
                $this->dir = make_unique_writable_directory($basedir);
            } catch (\Throwable $t) {
                $this->siterestore->add_to_log($t->getMessage(), constants::LOGLEVEL_WARNING);
            }
        }
        $this->dir = $this->dir ?? tempfiles::make_temp_dir("filesrestore-{$this->filetype}-{$this->currentseq}-");
        $tempdir = tempfiles::make_temp_dir('backupzip-');
        $zippath = $this->download_backup_file($tempdir);
        $zippacker = new \zip_packer();
        $zippacker->extract_to_pathname($zippath, $this->dir);
        $this->retrieve_files_list();
        $this->nextfileidx = 0;
        tempfiles::remove_temp_dir($tempdir);
        return true;
    }

    /**
     * Download all zip files and save them to file storage
     *
     * @return int number of files saved
     */
    public function save_to_fs(): int {
        if (!$this->is_pluginscode_backup()) {
            throw new \coding_exception('Can only be called for the pluginscode file type');
        }
        if ($this->dir || $this->currentseq !== -1) {
            throw new \coding_exception('Can not save into FS after the files were extracted');
        }
        $filesno = 0;
        while (($this->currentseq = $this->find_next_seq()) !== null) {
            $tempdir = tempfiles::make_temp_dir('backupzip-');
            $zippath = $this->download_backup_file($tempdir);
            $fs = get_file_storage();
            $fs->create_file_from_pathname([
                'contextid' => \context_system::instance()->id,
                'component' => 'tool_vault',
                'filearea' => constants::FILENAME_PLUGINSCODE,
                'itemid' => $this->siterestore->get_model()->id,
                'filepath' => '/',
                'filename' => $this->currentseq . '.zip',
                'sortorder' => $this->currentseq,
            ], $zippath);
            tempfiles::remove_temp_dir($tempdir);
            $filesno++;
        }
        return $filesno;
    }

    /**
     * Close current archive
     *
     * @return void
     */
    protected function close_current_archive(): void {
        global $CFG;
        if ($this->dir && file_exists($this->dir) && is_dir($this->dir)) {
            tempfiles::remove_temp_dir($this->dir);
        }
        $this->dir = null;
        if (!array_key_exists($this->currentseq, $this->backupfiles)) {
            return;
        }
        $this->backupfiles[$this->currentseq]->set_status(constants::STATUS_FINISHED)->save();
        $keys = array_keys($this->backupfiles);
        $lastkey = array_pop($keys);
        if ($this->is_dataroot_backup() && $this->currentseq == $lastkey) {
            // If it's the last file, remove the whole __vault_restore__ folder.
            tempfiles::remove_temp_dir($CFG->dataroot.DIRECTORY_SEPARATOR.'__vault_restore__');
        }
    }

    /**
     * Finish and remove all temp files
     *
     * not necessary to use, calling get_next_file/get_next_table last time will automatically finalise
     * only may be needed for dbstructure that we may read several times
     *
     * @return void
     */
    public function finish(): void {
        $this->close_current_archive();
    }
}
