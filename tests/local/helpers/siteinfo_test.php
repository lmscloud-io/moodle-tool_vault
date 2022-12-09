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

namespace tool_vault\local\helpers;

use tool_vault\constants;
use tool_vault\fixtures\site_backup_mock;
use tool_vault\local\models\backup_model;
use tool_vault\local\models\restore_model;
use tool_vault\site_restore;

/**
 * The siteinfo_test test class.
 *
 * @covers      \tool_vault\local\helpers\siteinfo
 * @package     tool_vault
 * @category    test
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class siteinfo_test extends \advanced_testcase {
    public function test_get_plugins_list_full() {
        $list = siteinfo::get_plugins_list_full(true);
        $this->assertNotEmpty($list);
    }
}
