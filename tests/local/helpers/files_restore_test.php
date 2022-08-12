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

/**
 * The files_restore_test test class.
 *
 * @package     tool_vault
 * @category    test
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_vault\local\helpers;

use tool_vault\constants;
use tool_vault\fixtures\files_restore_mock;
use tool_vault\fixtures\site_backup_mock;
use tool_vault\local\models\backup_model;
use tool_vault\local\models\restore_model;
use tool_vault\site_restore;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/'.$CFG->admin.'/tool/vault/tests/fixtures/site_backup_mock.php');
require_once($CFG->dirroot.'/'.$CFG->admin.'/tool/vault/tests/fixtures/files_restore_mock.php');

/**
 * The files_restore_test test class.
 *
 * @covers      \tool_vault\local\helpers\files_restore
 * @package     tool_vault
 * @category    test
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class files_restore_test extends \advanced_testcase {

    /**
     * Create mock site backup instance
     *
     * @return site_backup_mock
     */
    protected function create_site_backup(): site_backup_mock {
        $backup = new backup_model((object)['status' => constants::STATUS_INPROGRESS]);
        $backup->save();
        return new site_backup_mock($backup);
    }

    /**
     * Create mock site restore instance
     *
     * @return site_restore
     * @throws \coding_exception
     */
    protected function create_site_restore() {
        $restore = new restore_model((object)['status' => constants::STATUS_INPROGRESS]);
        $restore->save();
        return new site_restore($restore);
    }

    /**
     * Create archive file with files
     *
     * @param string $filename name of the archive
     * @param array $files list of files (if non-assoc array, the content will be randomly generated)
     * @return string path to the archive
     */
    protected function create_archive(string $filename, array $files) {
        $ziparchive = new \zip_archive();
        $path = make_request_directory().DIRECTORY_SEPARATOR.$filename;
        $ziparchive->open($path, \file_archive::CREATE);

        if (array_keys($files) === range(0, count($files) - 1)) {
            // Array $files is non-assoc.
            foreach ($files as $file) {
                $ziparchive->add_file_from_string($file, random_string());
            }
        } else {
            foreach ($files as $file => $content) {
                $ziparchive->add_file_from_string($file, $content);
            }
        }

        $ziparchive->close();
        return $path;
    }

    /**
     * Test helper for filedir
     */
    public function test_filedir() {
        global $DB;
        $this->resetAfterTest();
        $siterestore = $this->create_site_restore();
        files_restore::populate_backup_files($siterestore->get_model()->id, [
            ['name' => constants::FILENAME_FILEDIR.'.zip', 'size' => 0, 'etag' => 0],
            ['name' => constants::FILENAME_FILEDIR.'-1.zip', 'size' => 0, 'etag' => 0],
        ]);

        $files0 = ['f5/a3/ddddddddddddddddddd', 'ab/cd/bbbbbbbbbbbbbbbb'];
        files_restore_mock::use_archive($this->create_archive(constants::FILENAME_FILEDIR.'.zip', $files0));
        $files1 = ['d4/a2/cccccccccccccccc', 'ab/df/aaaaaaaaaaaaaaaaaaaa'];
        files_restore_mock::use_archive($this->create_archive(constants::FILENAME_FILEDIR.'-1.zip', $files1));

        $filesrestore = new files_restore_mock($siterestore, constants::FILENAME_FILEDIR);
        $this->assertEquals($files0[1], $filesrestore->get_next_file()[1]);
        $this->assertEquals($files0[0], $filesrestore->get_next_file()[1]);
        $this->assertEquals($files1[1], $filesrestore->get_next_file()[1]);

        $this->assertEquals(constants::STATUS_FINISHED,
            $DB->get_field('tool_vault_backup_file', 'status', ['filetype' => constants::FILENAME_FILEDIR, 'seq' => 0]));
        $this->assertEquals(constants::STATUS_SCHEDULED,
            $DB->get_field('tool_vault_backup_file', 'status', ['filetype' => constants::FILENAME_FILEDIR, 'seq' => 1]));

        $this->assertEquals($files1[0], $filesrestore->get_next_file()[1]);
        $this->assertNull($filesrestore->get_next_file());
    }

    /**
     * Test helper for dataroot
     */
    public function test_dataroot() {
        $this->resetAfterTest();
        $siterestore = $this->create_site_restore();
        files_restore::populate_backup_files($siterestore->get_model()->id, [
            ['name' => constants::FILENAME_DATAROOT.'.zip', 'size' => 0, 'etag' => 0],
            ['name' => constants::FILENAME_DATAROOT.'-1.zip', 'size' => 0, 'etag' => 0],
        ]);

        $files0 = ['lang/en/moodle.php', 'lang/de/moodle.php', 'something.json'];
        files_restore_mock::use_archive($this->create_archive(constants::FILENAME_DATAROOT.'.zip', $files0));
        $files1 = ['d/subdir/file.php'];
        files_restore_mock::use_archive($this->create_archive(constants::FILENAME_DATAROOT.'-1.zip', $files1));

        $filesrestore = new files_restore_mock($siterestore, constants::FILENAME_DATAROOT);
        $this->assertEquals('lang', $filesrestore->get_next_file()[1]);
        $this->assertEquals('something.json', $filesrestore->get_next_file()[1]);
        $this->assertEquals('d', $filesrestore->get_next_file()[1]);
        $this->assertNull($filesrestore->get_next_file());
    }

    /**
     * Prepare archive with db structure (from fixture)
     *
     * @return string
     */
    protected function prepare_db_structure() {
        global $CFG;
        return $this->create_archive(constants::FILENAME_DBSTRUCTURE.'.zip',
            [constants::FILE_STRUCTURE => file_get_contents(
                $CFG->dirroot.'/'.$CFG->admin.'/tool/vault/tests/fixtures/dbstructure1.xml')]);
    }

    /**
     * Test helper for dbdump
     */
    public function test_dbdump() {
        $this->resetAfterTest();

        $filepathstructure = $this->prepare_db_structure();

        $siterestore = $this->create_site_restore();
        $siterestore->prepare_restore_db($filepathstructure);

        files_restore::populate_backup_files($siterestore->get_model()->id, [
            ['name' => constants::FILENAME_DBDUMP.'.zip', 'size' => 0, 'etag' => 0],
            ['name' => constants::FILENAME_DBDUMP.'-1.zip', 'size' => 0, 'etag' => 0],
        ]);

        $usersfiles = [];
        for ($i = 0; $i < 140; $i++) {
            $usersfiles[] = "user.{$i}.json";
        }
        $files0 = array_merge($usersfiles, ['config.0.json']);
        shuffle($files0); // Test that the order will be correct later.
        files_restore_mock::use_archive($this->create_archive(constants::FILENAME_DBDUMP.'.zip', $files0));
        $files1 = ['forum.0.josn', 'nonexistingtable.0.json', 'course.0.json', 'course.1.json'];
        files_restore_mock::use_archive($this->create_archive(constants::FILENAME_DBDUMP.'-1.zip', $files1));

        $filesrestore = new files_restore_mock($siterestore, constants::FILENAME_DBDUMP);
        $this->assertEquals(['config', ['config.0.json']], $filesrestore->get_next_table());
        $this->assertEquals(['user', $usersfiles], $filesrestore->get_next_table());
        $this->assertEquals('forum', $filesrestore->get_next_table()[0]);
        $this->assertEquals('course', $filesrestore->get_next_table()[0]);
        $this->assertNull($filesrestore->get_next_table());
    }
}
