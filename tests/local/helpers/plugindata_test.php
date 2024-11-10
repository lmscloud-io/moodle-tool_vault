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
use tool_vault\local\xmldb\dbstructure;

/**
 * The plugindata_test test class.
 *
 * @covers      \tool_vault\local\helpers\plugindata
 * @package     tool_vault
 * @category    test
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plugindata_test extends \advanced_testcase {

    /**
     * Cleanup all temp files
     *
     * @return void
     */
    public function tearDown() {
        tempfiles::cleanup();
        parent::tearDown();
    }

    /**
     * Test with no exclusions, one and two exclusions
     *
     * @return array
     */
    protected function pluginset_to_test() {
        return [
            'none' => [],
            'p1' => ['tool_vault'],
            'p2' => ['local_codechecker'],
            'pboth' => ['tool_vault', 'local_codechecker'],
        ];
    }

    /**
     * Compare arrays without comparing order
     *
     * @param array $expected
     * @param array $actual
     * @param mixed $message
     * @return void
     */
    protected function assert_equals_canonicalizing(array $expected, array $actual, $message = '') {
        sort($expected);
        sort($actual);
        $this->assertEquals($expected, $actual, $message);
    }

    public function test_get_sql_for_plugins_data_in_table() {
        global $DB;
        $tablestotest = array_merge(plugindata::get_tables_with_possible_plugin_data(), ['course']);
        foreach ($tablestotest as $table) {
            $resall = $resyes = $resno = [];
            foreach ($this->pluginset_to_test() as $setname => $pluginset) {
                $resall[$setname] = $DB->get_records_select($table, '');
                $r = plugindata::get_sql_for_plugins_data_in_table($table, $pluginset);
                $resyes[$setname] = $DB->get_records_select($table, $r[0], $r[1]);
                $r = plugindata::get_sql_for_plugins_data_in_table($table, $pluginset, true);
                $resno[$setname] = $DB->get_records_select($table, $r[0], $r[1]);
                $this->assert_equals_canonicalizing($resall[$setname], $resyes[$setname] + $resno[$setname],
                    'Results do not add up for table '.$table.' for set '.$setname);
                $this->assertEquals(count($resall[$setname]), count($resyes[$setname]) + count($resno[$setname]),
                    'Results count do not add up for table '.$table.' for set '.$setname);
            }
            $this->assert_equals_canonicalizing($resyes['pboth'], $resyes['p1'] + $resyes['p2'],
                'Results do not add up for different sets for table '.$table);
            $this->assertTrue(count($resyes['pboth']) == count($resyes['p1']) + count($resyes['p2']),
                'Results counts do not add up for different sets for table '.$table);
        }
    }

    public function test_get_sql_for_plugins_data_in_table_to_preserve() {
        global $DB;
        foreach (plugindata::get_tables_with_possible_plugin_data_to_preserve() as $table) {
            foreach ($this->pluginset_to_test() as $setname => $pluginset) {
                // This does not assert anything, only checks for exceptions/debugging messages.
                $r = plugindata::get_sql_for_plugins_data_in_table_to_preserve($table, $pluginset, 13);
                // Mdlcode-disable-next-line cannot-parse-db-tablename.
                $res = $DB->get_records_select($table, $r[0], $r[1], 'id', $r[2]);
            }
        }
    }
}
