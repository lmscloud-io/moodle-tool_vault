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

/**
 * The site_backup_test class.
 *
 * @package     tool_vault
 * @category    test
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_vault\fixtures;

use tool_vault\local\helpers\tempfiles;

/**
 * Mock class for files_backup helper
 */
class files_backup_mock extends \tool_vault\local\helpers\files_backup {
    /** @var array */
    public $uploadedfiles = [];

    /**
     * Finish
     *
     * @param bool $startnew
     * @return void
     */
    public function finish(bool $startnew = false) {
        $zipfilepath = $this->get_archive_file_path();
        $this->ziparchive->close();
        $newfilepath = make_request_directory().DIRECTORY_SEPARATOR.basename($zipfilepath);
        copy($zipfilepath, $newfilepath);
        $this->uploadedfiles[] = $newfilepath;
        $this->ziparchive = null;

        \curl::mock_response('123');
        \curl::mock_response('123');
        \curl::mock_response(json_encode(['uploadurl' => 'https://test.s3.amazonaws.com/']));
        parent::finish($startnew);
    }
}
