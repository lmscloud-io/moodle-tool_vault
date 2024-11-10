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

namespace tool_vault\local\checks;

/**
 * Tests for Vault - Site migration
 *
 * @covers     \tool_vault\local\checks\plugins_restore
 * @package    tool_vault
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plugins_restore_test extends \advanced_testcase {
    public function test_major_version_from_branch() {
        $this->assertEquals('3.9', plugins_restore::major_version_from_branch(39));
        $this->assertEquals('3.9', plugins_restore::major_version_from_branch('39'));
        $this->assertEquals('3.11', plugins_restore::major_version_from_branch(311));
        $this->assertEquals('4.0', plugins_restore::major_version_from_branch(400));
        $this->assertEquals('4.5', plugins_restore::major_version_from_branch(405));
        $this->assertEquals('4.5', plugins_restore::major_version_from_branch('405'));
        $this->assertEquals('5.0', plugins_restore::major_version_from_branch(500));
    }
}
