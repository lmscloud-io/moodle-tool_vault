<?php
// This file is part of Moodle - http://moodle.org/
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
use tool_vault\local\models\restore_base_model;
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
    /** @var string  */
    protected $dir = '';
    /** @var \tool_vault\local\xmldb\dbstructure|null */
    protected $dbstructure = null;

    /**
     * Constructor
     *
     * @param operation_base $siterestore
     * @param string $filetype
     */
    public function __construct(operation_base $siterestore, string $filetype) {
        if ($filetype === constants::FILENAME_DBDUMP) {
            if ($siterestore instanceof site_restore) {
                $this->dbstructure = $siterestore->get_db_structure();
            } else {
                throw new \coding_exception('Files restore helper for dbdump can only be used from site_restore');
            }
        }
        $this->siterestore = $siterestore;
        $this->filetype = $filetype;
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
        $this->open_next_archive();
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
                'filesize' => $file['size'],
                'etag' => $file['etag'],
                'operationid' => $operationid,
                'status' => constants::STATUS_SCHEDULED,
            ];
            if (!$backupfile = backup_file::create($file['name'], $extra)) {
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
     * Filedir backup
     *
     * @return bool
     */
    protected function is_filedir_backup(): bool {
        return $this->filetype === constants::FILENAME_FILEDIR;
    }

    /**
     * Returns all files extracted from archive (only for dbstructure)
     *
     * @return array
     */
    public function get_all_files(): array {
        if ($this->filetype !== constants::FILENAME_DBSTRUCTURE) {
            throw new \coding_exception('Can only be called for the dbstructure file type');
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
     * For dbdump groups by table, for datadir only returns top-level files/folders
     *
     * @param bool $recursive
     * @param string $subpath
     * @return void
     */
    protected function populate_files_list(bool $recursive, string $subpath = '') {
        $path = $this->dir . DIRECTORY_SEPARATOR . $subpath;
        $handle = opendir($path);
        $files = [];
        while (($file = readdir($handle)) !== false) {
            if ($file !== '.' && $file !== '..') {
                $files[] = $file;
            }
        }
        closedir($handle);
        usort($files, [$this, 'filesorter']);
        foreach ($files as $file) {
            if ($recursive && is_dir($path . $file)) {
                $this->populate_files_list($recursive, $subpath . $file . DIRECTORY_SEPARATOR);
            } else {
                $this->add_to_current_files($subpath . $file);
            }
        }
    }

    /**
     * Called from populate_files_list
     *
     * @param string $localpath
     * @return void
     */
    protected function add_to_current_files(string $localpath) {
        if ($this->is_dbdump_backup()) {
            if (array_key_exists($this->tablename($localpath), $this->curenttables)) {
                $this->curenttables[$this->tablename($localpath)][] = $localpath;
            }
        } else {
            $this->curentfileslist[] = $localpath;
        }
    }

    /**
     * Used to sort files names
     *
     * @param string $file1
     * @param string $file2
     * @return int
     */
    protected function filesorter($file1, $file2): int {
        if ($this->is_dbdump_backup()) {
            $c1 = strcmp($this->tablename($file1), $this->tablename($file2));
            if ($c1 != 0) {
                return $c1;
            }
            $ext1 = (int)pathinfo(pathinfo($file1, PATHINFO_FILENAME), PATHINFO_EXTENSION);
            $ext2 = (int)pathinfo(pathinfo($file2, PATHINFO_FILENAME), PATHINFO_EXTENSION);
            return $ext1 - $ext2;
        } else {
            return strcmp($file1, $file2);
        }
    }

    /**
     * Download archive from API
     *
     * @return string
     * @throws \moodle_exception
     */
    protected function download_backup_file(): string {
        $zipdir = make_request_directory();
        $zippath = $zipdir.DIRECTORY_SEPARATOR.$this->backupfiles[$this->currentseq]->get_file_name();
        api::download_backup_file($this->siterestore->get_model()->backupkey, $zippath, $this->siterestore);
        return $zippath;
    }

    /**
     * Open next archive
     *
     * @return bool
     * @throws \moodle_exception
     */
    protected function open_next_archive(): bool {
        $this->currentseq = $this->find_next_seq();
        if ($this->currentseq === null) {
            return false;
        }
        $this->dir = make_request_directory();
        $zippath = $this->download_backup_file();
        $zippacker = new \zip_packer();
        $zippacker->extract_to_pathname($zippath, $this->dir);
        $this->curentfileslist = [];
        if ($this->is_dbdump_backup()) {
            $this->curenttables = array_fill_keys(array_keys($this->dbstructure->get_backup_tables()), []);
            $this->populate_files_list(false);
            $this->curenttables = array_filter($this->curenttables);
            $this->curentfileslist = array_keys($this->curenttables);
        } else {
            $this->populate_files_list($this->is_filedir_backup());
        }
        $this->nextfileidx = 0;
        @unlink($zippath); // TODO where did it go?
        return true;
    }

    /**
     * Close current archive
     *
     * @return void
     */
    protected function close_current_archive(): void {
        if (!array_key_exists($this->currentseq, $this->backupfiles)) {
            return;
        }
        $this->backupfiles[$this->currentseq]->set_status(constants::STATUS_FINISHED)->save();
        if ($this->dir && file_exists($this->dir) && is_dir($this->dir)) {
            site_restore::remove_recursively($this->dir);
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
