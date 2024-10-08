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

namespace tool_vault\local\helpers;

use tool_vault\api;

/**
 * The siteinfo_test test class.
 *
 * @covers      \tool_vault\local\helpers\siteinfo
 * @package     tool_vault
 * @category    test
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class siteinfo_test extends \advanced_testcase {

    /**
     * Cleanup all temp files
     *
     * @return void
     */
    public function tearDown(): void {
        tempfiles::cleanup();
        parent::tearDown();
    }

    public function test_get_plugins_list_full(): void {
        $list = siteinfo::get_plugins_list_full(true);
        $this->assertNotEmpty($list);
    }

    public function test_unsupported_plugin_types_to_exclude(): void {
        $this->assertTrue(in_array('mod', siteinfo::unsupported_plugin_types_to_exclude()));
    }

    public function test_plugin_has_xmldb_uninstall_function(): void {
        $this->assertTrue(siteinfo::plugin_has_xmldb_uninstall_function('search_simpledb'));
        $this->assertFalse(siteinfo::plugin_has_xmldb_uninstall_function('media_videojs'));
    }

    public function test_plugin_has_subplugins(): void {
        $this->assertTrue(siteinfo::plugin_has_subplugins('tool_log'));
        $this->assertFalse(siteinfo::plugin_has_subplugins('tool_mobile'));
        $this->assertFalse(siteinfo::plugin_has_subplugins('qformat_xml'));
    }

    public function test_is_dataroot_path_skipped_backup(): void {
        $this->resetAfterTest();
        $this->assertTrue(siteinfo::is_dataroot_path_skipped_backup('sessions'));
        $this->assertFalse(siteinfo::is_dataroot_path_skipped_backup('hellothere'));
        set_config('backupexcludedataroot', 'hellothere', 'tool_vault');
        $this->assertTrue(siteinfo::is_dataroot_path_skipped_backup('hellothere'));
    }

    public function test_is_dataroot_path_skipped_restore(): void {
        $this->resetAfterTest();
        $this->assertTrue(siteinfo::is_dataroot_path_skipped_restore('sessions'));
        $this->assertFalse(siteinfo::is_dataroot_path_skipped_restore('hellothere'));
        set_config('restorepreservedataroot', 'hellothere', 'tool_vault');
        $this->assertTrue(siteinfo::is_dataroot_path_skipped_restore('hellothere'));
    }
}
