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

namespace tool_vault\local\models;

use tool_vault\constants;

/**
 * The files_restore_test test class.
 *
 * @covers      \tool_vault\local\models\operation_model
 * @package     tool_vault
 * @category    test
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class operation_model_test extends \advanced_testcase {

    public function test_get_active_processes() {
        $this->resetAfterTest();
        $backup1 = operation_model::instance((object)['type' => 'backup', 'status' => constants::STATUS_SCHEDULED])->save();
        $all = operation_model::get_active_processes();
        $backups = backup_model::get_active_processes();
        $restores = restore_model::get_active_processes();
        $this->assertEquals(1, count($all));
        $this->assertEquals(1, count($backups));
        $this->assertEquals(0, count($restores));
    }
}
