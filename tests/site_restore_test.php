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

namespace tool_vault;

use tool_vault\local\models\backup_model;
use tool_vault\local\models\restore_model;

/**
 * The site_restore_test test class.
 *
 * @covers      \tool_vault\site_restore
 * @package     tool_vault
 * @category    test
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class site_restore_test extends \advanced_testcase {

    /**
     * Create mock site backup instance
     *
     * @return site_backup
     */
    protected function create_site_backup() {
        $backup = new backup_model((object)['status' => constants::STATUS_INPROGRESS]);
        $backup->save();
        return new site_backup($backup);
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

    public function test_restore_db() {
        global $DB;
        if (!PHPUNIT_LONGTEST) {
            $this->markTestSkipped('PHPUNIT_LONGTEST is not defined');
        }
        $this->resetAfterTest();
        $this->setAdminUser();
        // Create a course and an instance of book module.
        $course = $this->getDataGenerator()->create_course();
        $book1 = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);

        // Add and delete a record in table 'book' so that sequence is not the same as max(id).
        $booktemp = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        course_delete_module($booktemp->cmid);
        $this->assertCount(1, $DB->get_records('book'));

        // Perform backup.
        $sitebackup = $this->create_site_backup();
        [$filepathstructure, $filepath] = $sitebackup->export_db();

        // Add a second book instance.
        $book2 = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $this->assertCount(2, $DB->get_records('book'));

        // Prepare restore, only 'book' table.
        $siterestore = $this->create_site_restore();
        $structure = $siterestore->prepare_restore_db($filepathstructure);
        $tables = array_intersect_key($structure->get_tables_definitions(), ['book' => 1]);
        $structure->set_tables_definitions($tables);

        // Run restore, the content of table 'book' should revert to the state when the backup was made.
        $siterestore->restore_db($structure, $filepath);
        $this->assertCount(1, $DB->get_records('book'));

        // Assert sequences were restored.
        $book3 = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $this->assertEquals($book2->id, $book3->id);
    }

    public function test_restore_dataroot() {
        $this->resetAfterTest();

        // Make a directory under dataroot and store a file there.
        $hellodir = make_upload_directory('helloworld');
        $hellofilepath = $hellodir.DIRECTORY_SEPARATOR.'hello.txt';
        file_put_contents($hellofilepath, 'Hello world!');
        $this->assertTrue(file_exists($hellofilepath));

        // Call export_dataroot() from site_backup.
        $sitebackup = $this->create_site_backup();
        $filepath = $sitebackup->export_dataroot();

        // Remove file.
        unlink($hellofilepath);
        $this->assertFalse(file_exists($hellofilepath));

        // Restore.
        $siterestore = $this->create_site_restore();
        $files = $siterestore->prepare_restore_dataroot($filepath);
        $siterestore->restore_dataroot($files);

        // File is now present.
        $this->assertTrue(file_exists($hellofilepath));
        $this->assertEquals('Hello world!', file_get_contents($hellofilepath));
    }

    public function test_site_restore_filedir() {
        global $CFG;
        $this->resetAfterTest();

        // Create a file in filedir.
        $file = $this->create_file();
        $chash = $file->get_contenthash();
        $filepathondisk = $CFG->dataroot.'/filedir/'.substr($chash, 0, 2).'/'.substr($chash, 2, 2).'/'.$chash;
        $this->assertTrue(file_exists($filepathondisk));

        // Perform backup.
        $sitebackup = $this->create_site_backup();
        $filepaths = $sitebackup->export_filedir();

        // Remove the file.
        $this->delete_file();
        $this->assertFalse(file_exists($filepathondisk));

        // Run restore, file is now back.
        $siterestore = $this->create_site_restore();
        $restoreddir = $siterestore->prepare_restore_filedir($filepaths[0]);
        $siterestore->restore_filedir($restoreddir);
        $this->assertTrue(file_exists($filepathondisk));
        $this->assertEquals('helloworld', file_get_contents($filepathondisk));
    }

    /**
     * Create a file in filedir
     *
     * @return \stored_file
     * @throws \file_exception
     * @throws \moodle_exception
     */
    protected function create_file() {
        global $CFG;
        $CFG->numsections = 1;
        course_create_sections_if_missing(SITEID, 1);
        $sectionid = get_fast_modinfo(SITEID)->get_section_info_all()[1]->id;

        return get_file_storage()->create_file_from_string([
            'contextid' => \context_course::instance(SITEID)->id,
            'component' => 'course',
            'filearea' => 'section',
            'itemid' => $sectionid,
            'filepath' => '/',
            'filename' => 'hello.txt',
        ], 'helloworld');
    }

    /**
     * Delete testing stored file from db and filedir
     *
     * @return void
     */
    protected function delete_file() {
        $sectionid = get_fast_modinfo(SITEID)->get_section_info_all()[1]->id;
        get_file_storage()->delete_area_files(\context_course::instance(SITEID)->id,
            'course', 'section', $sectionid);
    }
}
