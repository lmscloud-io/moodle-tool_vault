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
 * The cleanup_existing_files_test test class.
 *
 * @package     tool_vault
 * @category    test
 * @copyright   2023 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_vault\local\restoreactions;

use tool_vault\constants;
use tool_vault\local\helpers\tempfiles;
use tool_vault\local\models\restore_model;
use tool_vault\site_restore;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/'.$CFG->admin.'/tool/vault/tests/fixtures/site_backup_mock.php');

/**
 * The cleanup_existing_files test class.
 *
 * @covers      \tool_vault\local\restoreactions\cleanup_existing_files
 * @package     tool_vault
 * @category    test
 * @copyright   2023 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cleanup_existing_files_test extends \advanced_testcase {

    /**
     * Cleanup all temp files
     *
     * @return void
     */
    public function tearDown() {
        tempfiles::cleanup();
        parent::tearDown();
    }

    public function test_execute() {
        global $CFG, $DB;
        $this->resetAfterTest();

        // Create a file in filedir.
        $file = $this->create_file();
        $chash = $file->get_contenthash();
        $filepathondisk = $CFG->dataroot.'/filedir/'.substr($chash, 0, 2).'/'.substr($chash, 2, 2).'/'.$chash;
        $this->assertTrue(file_exists($filepathondisk));

        $siterestore = $this->create_site_restore();
        // Execute the pre-restore action.
        (new cleanup_existing_files())->execute($siterestore, restore_action::STAGE_BEFORE);
        $this->assertNotEmpty($DB->get_records('tool_vault_table_files_data', ['restoreid' => $siterestore->get_model()->id]));
        // Emulate file being deleted from files table during restore.
        $DB->execute("DELETE FROM {files} WHERE contenthash=?", [$chash]);
        // Execute the post-restore action.
        (new cleanup_existing_files())->execute($siterestore, restore_action::STAGE_AFTER_ALL);
        // Make sure file no longer exists on disk.
        $this->assertFalse(file_exists($filepathondisk));
        $this->assertEmpty($DB->get_records('tool_vault_table_files_data', ['restoreid' => $siterestore->get_model()->id]));
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
}
