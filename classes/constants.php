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

namespace tool_vault;

/**
 * Constants
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class constants {
    /** @var string */
    const DIFF_EXTRATABLES = 'extratables';
    /** @var string */
    const DIFF_MISSINGTABLES = 'missingtables';
    /** @var string */
    const DIFF_CHANGEDTABLES = 'changedtables';
    /** @var string */
    const DIFF_EXTRACOLUMNS = 'extracolumns';
    /** @var string */
    const DIFF_MISSINGCOLUMNS = 'missingcolumns';
    /** @var string */
    const DIFF_CHANGEDCOLUMNS = 'changedcolumns';
    /** @var string */
    const DIFF_MISSINGINDEXES = 'missingindexes';
    /** @var string */
    const DIFF_EXTRAINDEXES = 'extraindexes';
    /** @var string */
    const DIFF_INVALIDTABLES = 'invalidtables';

    /** @var string */
    const FILE_STRUCTURE = '__structure__.xml';
    /** @var string */
    const FILE_METADATA = '__metadata__.json';
    /** @var string */
    const FILE_SEQUENCE = '__sequences__.json';
    /** @var string */
    const FILE_CONFIGOVERRIDE = '__configoverride__.json';
    /** @var string */
    const FILENAME_DBSTRUCTURE = 'dbstructure';
    /** @var string */
    const FILENAME_DBDUMP = 'dbdump';
    /** @var string */
    const FILENAME_DATAROOT = 'dataroot';
    /** @var string */
    const FILENAME_FILEDIR = 'filedir';

    /** @var string */
    const STATUS_INPROGRESS = 'inprogress';
    /** @var string */
    const STATUS_SCHEDULED = 'scheduled';
    /** @var string */
    const STATUS_FINISHED = 'finished';
    /** @var string */
    const STATUS_FAILED = 'failed';
    /** @var string */
    const STATUS_FAILEDTOSTART = 'failedtostart';

    /** @var string */
    const LOGLEVEL_INFO = 'info';
    /** @var string */
    const LOGLEVEL_ERROR = 'error';
    /** @var string */
    const LOGLEVEL_WARNING = 'warning';
    /** @var string */
    const LOGLEVEL_PROGRESS = 'progress';
    /** @var string */
    const LOGLEVEL_VERBOSE = 'verbose';

    /** @var int */
    const REQUEST_API_TIMEOUT = 20; // 20 seconds.
    /** @var int */
    const REQUEST_S3_TIMEOUT = 60 * 60; // 60 minutes.
    /** @var int */
    const REQUEST_API_RETRIES = 4;
    /** @var int */
    const REQUEST_S3_RETRIES = 4;

    /** @var int */
    const LOCK_TIMEOUT = 60 * 60; // 1 hour.

    /** @var int */
    const DBFILE_SIZE = 1024 * 1024 * 2; // 2 Mb.
    /** @var int */
    const UPLOAD_SIZE = 1024 * 1024 * 1024; // 1 Gb.
    /** @var int */
    const FILES_BATCH = 5000;

    /** @var int */
    const DESCRIPTION_MAX_LENGTH = 255;

    /** @var int */
    const LOG_FREQUENCY = 15; // 15s.
}
