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

/**
 * Tests for Vault - Site migration
 *
 * @covers     \tool_vault\local\helpers\tempfiles
 * @package    tool_vault
 * @category   test
 * @copyright  2024 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tempfiles_test extends \advanced_testcase {
    public function test_make_temp_directory() {
        $dir = tempfiles::make_temp_dir('test-');
        $this->assertTrue(file_exists($dir) && is_dir($dir));
        $this->assertTrue(tempfiles::dir_is_empty($dir));
        file_put_contents($dir.'/f1.txt', "hi");
        $this->assertFalse(tempfiles::dir_is_empty($dir));
        tempfiles::remove_temp_dir($dir);
        $this->assertFalse(file_exists($dir));
    }

    public function test_get_free_space_fallback(): void {
        $dir = make_backup_temp_directory('mytest');
        $space = disk_free_space($dir);
        $mb = 1024 * 1024;
        if ($space < $mb) {
            $this->markTestSkipped('There is less than 1Mb of free disk space, skipping the test');
        }
        $this->assertTrue(tempfiles::get_free_space_fallback($dir, 10));
        $this->assertTrue(tempfiles::get_free_space_fallback($dir, $mb));
    }
}
