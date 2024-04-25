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
 * The files_restore_test test class.
 *
 * @package     tool_vault
 * @category    test
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_vault\local\helpers;

use tool_vault\constants;
use tool_vault\fixtures\site_backup_mock;
use tool_vault\local\models\backup_model;
use tool_vault\local\models\restore_model;
use tool_vault\site_restore;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/'.$CFG->admin.'/tool/vault/tests/fixtures/site_backup_mock.php');

/**
 * The files_restore_test test class.
 *
 * @covers      \tool_vault\local\helpers\files_restore
 * @package     tool_vault
 * @category    test
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class files_restore_test extends \advanced_testcase {

    /**
     * Cleanup all temp files
     *
     * @return void
     */
    public function tearDown(): void {
        tempfiles::cleanup();
    }

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
        $restore = new restore_model((object)['status' => constants::STATUS_INPROGRESS, 'backupkey' => 'b']);
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
        $path = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
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
    public function test_filedir(): void {
        global $DB;
        $this->resetAfterTest();
        $siterestore = $this->create_site_restore();

        // Mock responses from API.
        $files0 = ['f5/a3/ddddddddddddddddddd', 'ab/cd/bbbbbbbbbbbbbbbb'];
        $files1 = ['d4/a2/cccccccccccccccc', 'ab/df/aaaaaaaaaaaaaaaaaaaa'];
        $this->mock_backup_files($siterestore->get_model()->id, [
            [constants::FILENAME_FILEDIR, null, $files0],
            [constants::FILENAME_FILEDIR.'-1', null, $files1],
        ]);

        $filesrestore = new files_restore($siterestore, constants::FILENAME_FILEDIR);
        $this->assertTrue($filesrestore->is_first_archive());
        $this->assertEquals($files0[1], $filesrestore->get_next_file()[1]);
        $this->assertEquals($files0[0], $filesrestore->get_next_file()[1]);
        $this->assertEquals($files1[1], $filesrestore->get_next_file()[1]);

        $this->assertFalse($filesrestore->is_first_archive());
        $this->assertEquals(constants::STATUS_FINISHED,
            $DB->get_field('tool_vault_backup_file', 'status', ['filetype' => constants::FILENAME_FILEDIR, 'seq' => 0]));
        $this->assertEquals(constants::STATUS_SCHEDULED,
            $DB->get_field('tool_vault_backup_file', 'status', ['filetype' => constants::FILENAME_FILEDIR, 'seq' => 1]));

        $this->assertEquals($files1[0], $filesrestore->get_next_file()[1]);
        $this->assertNull($filesrestore->get_next_file());
        $filesrestore->finish();
    }

    /**
     * Test helper for dataroot
     */
    public function test_dataroot(): void {
        $this->resetAfterTest();
        $siterestore = $this->create_site_restore();

        // Mock responses from API.
        $files0 = ['lang/en/moodle.php', 'lang/de/moodle.php', 'something.json'];
        $files1 = ['d/subdir/file.php'];
        $this->mock_backup_files($siterestore->get_model()->id, [
            [constants::FILENAME_DATAROOT, null, $files0],
            [constants::FILENAME_DATAROOT.'-1', null, $files1],
        ]);

        // Check result of get_next_file().
        $filesrestore = new files_restore($siterestore, constants::FILENAME_DATAROOT);
        $this->assertEquals('lang', $filesrestore->get_next_file()[1]);
        $this->assertEquals('lang/de', $filesrestore->get_next_file()[1]);
        $this->assertEquals('lang/de/moodle.php', $filesrestore->get_next_file()[1]);
        $this->assertEquals('lang/en', $filesrestore->get_next_file()[1]);
        $this->assertEquals('lang/en/moodle.php', $filesrestore->get_next_file()[1]);
        $this->assertEquals('something.json', $filesrestore->get_next_file()[1]);
        $this->assertEquals('d', $filesrestore->get_next_file()[1]);
        $this->assertEquals('d/subdir', $filesrestore->get_next_file()[1]);
        $this->assertEquals('d/subdir/file.php', $filesrestore->get_next_file()[1]);
        $this->assertNull($filesrestore->get_next_file());
        $filesrestore->finish();
    }

    /**
     * Prepare archive with db structure (from fixture)
     */
    protected function prepare_db_structure() {
        global $CFG;
        return $this->create_archive(constants::FILENAME_DBSTRUCTURE.'.zip',
            [constants::FILE_STRUCTURE => file_get_contents(
                $CFG->dirroot.'/'.$CFG->admin.'/tool/vault/tests/fixtures/dbstructure1.xml'), ]);
    }

    /**
     * Set archive to use
     *
     * @param string $filepath
     * @return void
     */
    protected function curl_mock_file_download(string $filepath) {
        \curl::mock_response(file_get_contents($filepath));
        \curl::mock_response(json_encode(['downloadurl' => 'https://test.s3.amazonaws.com/']));
    }

    /**
     * Mock both backup files in DB and API responses
     *
     * @param int $opid
     * @param array $files each row is an array:
     *     [filename without extension, archivefilepath|null, list of files to create archive file]
     * @return void
     */
    protected function mock_backup_files(int $opid, array $files) {
        $apifiles = [];
        foreach ($files as $file) {
            $apifiles[] = ['name' => $file[0].'.zip'];
        }
        files_restore::populate_backup_files($opid, $apifiles);
        foreach (array_reverse($files) as $file) {
            $filepath = $file[1] ?? $this->create_archive($file[0].'.zip', $file[2]);
            $this->curl_mock_file_download($filepath);
        }
    }

    /**
     * Test helper for dbdump
     */
    public function test_dbdump(): void {
        $this->resetAfterTest();

        $filepathstructure = $this->prepare_db_structure();

        $siterestore = $this->create_site_restore();

        // Mock backup files and curl responses.
        $usersfiles = [];
        for ($i = 0; $i < 140; $i++) {
            $usersfiles[] = "user.{$i}.json";
        }
        $files0 = array_merge($usersfiles, ['config.0.json']);
        $files1 = ['forum.0.josn', 'nonexistingtable.0.json', 'course.0.json', 'course.1.json'];
        shuffle($files0); // Test that the order will be correct later.

        $this->mock_backup_files($siterestore->get_model()->id, [
            [constants::FILENAME_DBSTRUCTURE, $filepathstructure],
            [constants::FILENAME_DBDUMP, null, $files0],
            [constants::FILENAME_DBDUMP.'-1', null, $files1],
        ]);

        // Read db structure.
        $siterestore->prepare_restore_db();

        // Start pulling tables one by one and check response.
        $filesrestore = $siterestore->get_files_restore(constants::FILENAME_DBDUMP);
        $nexttable = $filesrestore->get_next_table();
        $nexttable[1] = array_map('basename', $nexttable[1]);
        $this->assertEquals(['config', ['config.0.json']], $nexttable);

        $nexttable = $filesrestore->get_next_table();
        $nexttable[1] = array_map('basename', $nexttable[1]);
        $this->assertEquals(['user', $usersfiles], $nexttable);

        $this->assertEquals('forum', $filesrestore->get_next_table()[0]);
        $this->assertEquals('course', $filesrestore->get_next_table()[0]);
        $this->assertNull($filesrestore->get_next_table());

        // Clean up.
        $siterestore->get_files_restore(constants::FILENAME_DBSTRUCTURE)->finish();
        $filesrestore->finish();
    }
}
