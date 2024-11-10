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

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/filestorage/zip_archive.php");

/**
 * Wrapper for the core \zip_archive class with access to some protected properties
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class zip_archive extends \zip_archive {

    /**
     * Allows to specify the compression level of the file
     *
     * Improves performance for files that are already archives or have their own compression.
     *
     * @param string $localname name of the file in the archive
     * @param int $level level of compression: 0 - not compressed, 9 - maximum compression
     * @return void
     */
    public function set_file_compression_level(string $localname, int $level) {
        if ($level >= 9) {
            // Compression level 9 is default, we do not need to do anything.
            return;
        }
        if (!$this->za) {
            return;
        }
        if ($level <= 0) {
            $this->za->setCompressionName($localname, \ZipArchive::CM_STORE);
        } else {
            $this->za->setCompressionName($localname, \ZipArchive::CM_DEFLATE, $level);
        }
    }
}
