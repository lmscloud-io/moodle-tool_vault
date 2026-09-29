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

#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vault\local\helpers\tempfiles::class)]
/**
 * Tests for Vault - Site backup and migration
 *
 * @covers     \tool_vault\local\helpers\tempfiles
 * @package    tool_vault
 * @category   test
 * @copyright  2024 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tempfiles_test extends \advanced_testcase {
    public function test_make_temp_directory(): void {
        $dir = tempfiles::make_temp_dir('test-');
        $this->assertTrue(file_exists($dir) && is_dir($dir));
        $this->assertTrue(tempfiles::dir_is_empty($dir));
        file_put_contents($dir . '/f1.txt', "hi");
        $this->assertFalse(tempfiles::dir_is_empty($dir));
        tempfiles::remove_temp_dir($dir);
        $this->assertFalse(file_exists($dir));
    }

    public function test_remove_temp_dir_with_symlinks(): void {
        if (!function_exists('symlink') || DIRECTORY_SEPARATOR !== '/') {
            $this->markTestSkipped('Symbolic links are not supported');
        }

        // Files and directories outside of the directory that is being removed.
        $targetdir = tempfiles::make_temp_dir('test-target-');
        file_put_contents($targetdir . '/target.txt', 'target');
        mkdir($targetdir . '/subdir');
        file_put_contents($targetdir . '/subdir/file.txt', 'target');

        // Directory with regular files and with symlinks to the files and directories above.
        $dir = tempfiles::make_temp_dir('test-');
        mkdir($dir . '/sub');
        file_put_contents($dir . '/sub/f1.txt', 'hi');
        symlink($targetdir . '/target.txt', $dir . '/sub/linktofile.txt');
        symlink($targetdir . '/subdir', $dir . '/sub/linktodir');
        symlink($targetdir . '/nonexisting', $dir . '/brokenlink');

        $this->assertEquals(4, tempfiles::remove_temp_dir($dir));
        $this->assertFalse(file_exists($dir) || is_link($dir));

        // A symlink to a directory is removed without touching the target.
        $link = make_request_directory() . '/link';
        symlink($targetdir, $link);
        $this->assertEquals(1, tempfiles::remove_temp_dir($link));
        $this->assertFalse(file_exists($link) || is_link($link));

        // All targets are still there.
        $this->assertEquals('target', file_get_contents($targetdir . '/target.txt'));
        $this->assertEquals('target', file_get_contents($targetdir . '/subdir/file.txt'));

        tempfiles::remove_temp_dir($targetdir);
        $this->assertFalse(file_exists($targetdir));
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
