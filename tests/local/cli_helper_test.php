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

namespace tool_vault\local;

use tool_vault\constants;

/**
 * Tests for \tool_vault\local\cli_helper
 *
 * @covers      \tool_vault\local\cli_helper
 * @package     tool_vault
 * @category    test
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cli_helper_test extends \advanced_testcase {

    /**
     * Generate cli_helper and mock $_SERVER['argv']
     *
     * @param string $script
     * @param array $mockargv
     * @return cli_helper
     */
    protected function construct_helper($script, array $mockargv = []) {
        if (array_key_exists('argv', $_SERVER)) {
            $oldservervars = $_SERVER['argv'];
        }
        $_SERVER['argv'] = array_merge([''], $mockargv);
        $clihelper = new cli_helper($script, basename(__FILE__));
        if (isset($oldservervars)) {
            $_SERVER['argv'] = $oldservervars;
        } else {
            unset($_SERVER['argv']);
        }
        return $clihelper;
    }

    /**
     * Test cli_helper for backup
     */
    public function test_cli_helper_for_backup() {
        $this->resetAfterTest();
        $clihelper = $this->construct_helper(cli_helper::SCRIPT_BACKUP, ['--apikey=phpunit']);
        ob_start();
        $clihelper->print_help();
        $contents = ob_get_contents();
        $this->assertNotEmpty($contents);
        ob_end_clean();

        $clihelper->validate_cli_options();
    }
}
