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
    public function test_restore_db() {
        global $DB;
        $this->resetAfterTest();
        // Create a course and an instance of book module.
        $course = $this->getDataGenerator()->create_course();
        $book1 = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);

        // Add and delete a record in table 'book' so that sequence is not the same as max(id).
        $booktemp = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        course_delete_module($booktemp->cmid);
        $this->assertCount(1, $DB->get_records('book'));

        // Perform backup.
        $sitebackup = new site_backup("");
        $filepath = $sitebackup->export_db('dbdump.zip');

        // Add a second book instance.
        $book2 = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $this->assertCount(2, $DB->get_records('book'));

        // Prepare restore, only 'book' table.
        $siterestore = new site_restore();
        $structure = $siterestore->prepare_restore_db($filepath);
        $tables = array_intersect_key($structure->get_tables_definitions(), ['book' => 1]);
        $structure->set_tables_definitions($tables);

        // Run restore, the content of table 'book' should revert to the state when the backup was made.
        $siterestore->restore_db($structure, $filepath);
        $this->assertCount(1, $DB->get_records('book'));

        // Assert sequences were restored.
        $book3 = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $this->assertEquals($book2->id, $book3->id);
    }
}
