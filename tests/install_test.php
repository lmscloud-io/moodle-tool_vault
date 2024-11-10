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

namespace tool_vault;

/**
 * Tests for \tool_vault\db\install.php
 *
 * @package     tool_vault
 * @category    test
 * @copyright   2024 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class install_test extends \advanced_testcase {
    /**
     * Test pre-checks that are normally scheduled via db/install.php
     *
     * @covers ::xmldb_tool_vault_install
     */
    public function test_xmldb_tool_vault_install() {
        $this->resetAfterTest();

        \tool_vault\local\checks\check_base::get_all_checks();

        $task = new \tool_vault\task\cron_task();
        $task->execute();
    }
}
