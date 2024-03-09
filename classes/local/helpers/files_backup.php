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
use tool_vault\site_backup;

/**
 * Helper class for files backup
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class files_backup {
    /** @var site_backup */
    protected $sitebackup;
    /** @var string */
    protected $filetype;
    /** @var backup_file[] */
    protected $backupfiles = [];
    /** @var \zip_archive */
    protected $ziparchive;
    /** @var string */
    protected $zipdir;
    /** @var array */
    protected $filestoremove = [];
    /** @var backup_file */
    protected $currentbackupfile = null;

    /**
     * Constructor
     *
     * @param site_backup $sitebackup
     * @param string $filetype
     */
    public function __construct(site_backup $sitebackup, string $filetype) {
        global $DB;
        $this->sitebackup = $sitebackup;
        $this->filetype = $filetype;
        $records = $DB->get_records_select(backup_file::TABLE, "operationid = ? AND filetype = ?",
            [$sitebackup->get_model()->id, $this->filetype], 'seq, id');
        foreach ($records as $record) {
            $backupfile = new backup_file((array)$record);
            $this->backupfiles[] = $backupfile;
        }
        $this->zipdir = make_request_directory();
        $this->start();
    }

    /**
     * DB backups also need to store list of tables
     *
     * @return bool
     */
    protected function is_db_backup(): bool {
        return $this->filetype === constants::FILENAME_DBDUMP;
    }

    /**
     * Start writing to archive
     *
     * @return void
     * @throws \moodle_exception
     */
    public function start() {
        // Prepare model but do not save, In case of backup we don't save unfinished files.
        $seq = $this->backupfiles ? ($this->backupfiles[count($this->backupfiles) - 1]->seq + 1) : 0;
        $this->currentbackupfile = new backup_file([
            'filetype' => $this->filetype,
            'seq' => $seq,
            'operationid' => $this->sitebackup->get_model()->id,
            'status' => constants::STATUS_INPROGRESS,
            'filesize' => 0,
            'origsize' => 0,
            'details' => json_encode([]),
        ]);

        $this->ziparchive = new \zip_archive();
        if (!$this->ziparchive->open($this->get_archive_file_path(), \file_archive::CREATE)) {
            // TODO?
            throw new \moodle_exception('error_cannotcreatezip', 'tool_vault');
        }
    }

    /**
     * Path to current archive
     *
     * @return string
     */
    public function get_archive_file_path(): string {
        return $this->zipdir . DIRECTORY_SEPARATOR . $this->currentbackupfile->get_file_name();
    }

    /**
     * Finish writing to archive, upload it
     *
     * @param bool $startnew start new archive
     */
    public function finish(bool $startnew = false) {
        $this->ziparchive->close();
        $this->ziparchive = null;

        if (!empty($this->currentbackupfile->get_detail('lastfile'))) {
            $zipfilepath = $this->get_archive_file_path();
            $this->currentbackupfile->set_filesize(filesize($zipfilepath));
            api::upload_backup_file($this->sitebackup, $zipfilepath, $this->currentbackupfile);
            $this->currentbackupfile->set_status(constants::STATUS_FINISHED)->save();
            $this->backupfiles[] = $this->currentbackupfile;
            unlink($zipfilepath);
            $this->currentbackupfile = null;
        }

        // Cleanup and reset.
        foreach ($this->filestoremove as $file) {
            unlink($file);
        }
        $this->filestoremove = [];

        if ($startnew) {
            $this->start();
        }
    }

    /**
     * Total size of uploaded files
     *
     * @return int
     */
    public function get_uploaded_size(): int {
        $totalsize = 0;
        foreach ($this->backupfiles as $backupfile) {
            $totalsize += $backupfile->filesize;
        }
        return $totalsize;
    }

    /**
     * Add file to the current archive (if it's a folder it will add all files in the folder)
     *
     * @param string $filepath
     * @param string|null $localname
     * @param bool $allownewzip allow to break after this file and start a new archive
     * @param bool $removesource remove $filepath on completion
     * @return self
     */
    public function add_file(string $filepath, ?string $localname = null, bool $allownewzip = true,
                             bool $removesource = true): self {
        $localname = $localname ?? basename($filepath);
        if (is_dir($filepath)) {
            $this->add_folder($filepath, $localname, $removesource);
            return $this;
        }
        $this->ziparchive->add_file_from_pathname($localname, $filepath);
        if ($removesource) {
            $this->filestoremove[] = $filepath;
        }
        $this->currentbackupfile->set_origsize($this->currentbackupfile->origsize + filesize($filepath));
        $this->currentbackupfile->update_detail('lastfile', $localname);
        if ($allownewzip) {
            $this->check_if_new_zip_needed();
        }
        return $this;
    }

    /**
     * Add all files in a folder (recursively)
     *
     * @param string $filepath
     * @param string $localname
     * @param bool $removesource
     * @return void
     */
    protected function add_folder(string $filepath, string $localname, bool $removesource = true) {
        $handle = opendir($filepath);
        while (($file = readdir($handle)) !== false) {
            if ($file !== '.' && $file !== '..') {
                $this->add_file($filepath . DIRECTORY_SEPARATOR . $file,
                    $localname . DIRECTORY_SEPARATOR . $file, false, $removesource);
            }
        }
        closedir($handle);
    }

    /**
     * Add a file to the current archive from string content
     *
     * @param string $localname
     * @param string $content
     * @return self
     */
    public function add_file_from_string(string $localname, string $content): self {
        $this->ziparchive->add_file_from_string($localname, $content);
        $this->currentbackupfile->set_origsize($this->currentbackupfile->origsize + strlen($content));
        $this->currentbackupfile->update_detail('lastfile', $localname);
        return $this;
    }

    /**
     * Add a table export (full or partial) to this archive
     *
     * @param string $tablename
     * @param string $filepath
     * @return self
     */
    public function add_table_file(string $tablename, string $filepath): self {
        if (!$this->is_db_backup()) {
            throw new \coding_exception('This function can only be used for the DB backup');
        }
        $this->add_file($filepath, null, false);
        $uploadedtables = $this->currentbackupfile->get_detail('tables') ?? [];
        if (!in_array($tablename, $uploadedtables)) {
            $this->currentbackupfile->update_detail('tables', array_merge($uploadedtables, [$tablename]));
        }
        return $this;
    }

    /**
     * Called when we are done adding files for one table
     *
     * @return self
     */
    public function finish_table(): self {
        if (!$this->is_db_backup()) {
            throw new \coding_exception('This function can only be used for the DB backup');
        }
        $this->check_if_new_zip_needed();
        return $this;
    }

    /**
     * Is it time to finish one archive and start a new one
     *
     * @return void
     */
    protected function check_if_new_zip_needed() {
        if ($this->currentbackupfile->origsize > constants::UPLOAD_SIZE) {
            $this->finish(true);
        }
    }

    /**
     * Get the relative path to the last file added to the backup (excluding current backup)
     *
     * @return string|null
     */
    public function get_last_backedup_file(): ?string {
        if (!$this->backupfiles) {
            return null;
        }
        return $this->backupfiles[count($this->backupfiles) - 1]->get_detail('lastfile');
    }
}
