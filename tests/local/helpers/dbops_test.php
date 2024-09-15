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
use ReflectionClass;

/**
 * Tests for Vault - Site migration
 *
 * @covers     \tool_vault\local\helpers\dbops
 * @package    tool_vault
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dbops_test extends \advanced_testcase {

    public function test_get_max_allowed_packet(): void {
        global $DB;

        $packet = $this->call_static_method('get_max_allowed_packet', []);

        if ($DB->get_dbfamily() === 'mysql') {
            $this->assertTrue($packet > 0);
        } else {
            $this->assertSame(null, $packet);
        }
    }

    /**
     * Sample data that could be a contents of a file in the dbdump.zip
     *
     * @return array
     */
    protected function get_sample_data(): array {
        $json = <<<EOF
[
    ["id","name","value"],
    [1, "test", "value"],
    [2, "another", "キャンパス Αλφαβητικός Κατάλογος Лорем ипсум долор сит амет"],
    [3, "withquotes", "\"I'm hungry\""]
]
EOF;
        $data = json_decode($json, true);
        $fields = array_shift($data);
        return ['config', $fields, $data];
    }

    /**
     * Call protected static method of class dbops
     *
     * @param string $method
     * @param array $args
     * @return mixed
     */
    protected function call_static_method(string $method, array $args = []) {
        $class = new ReflectionClass(dbops::class);
        $method = $class->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs(null, $args);
    }

    /**
     * Test for method prepare_insert_sql
     * @uses dbops::prepare_insert_sql()
     */
    public function test_prepare_insert_sql(): void {
        global $DB;

        [$tablename, $fields, $data] = $this->get_sample_data();

        $sql = $this->call_static_method('prepare_insert_sql', [$tablename, $fields, 2]);
        $this->assertEquals('INSERT INTO {config} (id,name,value) VALUES (?,?,?),(?,?,?)', $sql);
    }

    /**
     * Test for method calculate_row_packet_sizes
     * @uses dbops::calculate_row_packet_sizes()
     */
    public function test_calculate_row_packet_sizes(): void {
        global $DB;
        [$tablename, $fields, $data] = $this->get_sample_data();
        $res = $this->call_static_method('calculate_row_packet_sizes', [count($fields), &$data]);
        if ($DB->get_dbfamily() === 'mysql') {
            $this->assertEquals([20, 124, 36], $res);
        } else {
            $this->assertEquals([0, 0, 0], $res);
        }
    }

    /**
     * Overrides protected static property dmops::$maxallowedpacket
     *
     * @param int|false|null $newvalue
     */
    protected function mock_maxallowedpacket($newvalue): void {
        $class = new ReflectionClass(dbops::class);
        $class->setStaticPropertyValue('maxallowedpacket', $newvalue);
    }

    /**
     * Test for method prepare_next_chunk
     * @uses dbops::prepare_next_chunk()
     */
    public function test_prepare_next_chunk(): void {
        global $CFG;
        $tablename = 'mytable';
        $fields = ['id', 'name', 'value'];
        $packetsizes = [15, 20, 16, 19, 30, 30];

        // Very large packet size.
        $this->mock_maxallowedpacket(100000);
        $endrow = $this->call_static_method('prepare_next_chunk', [$tablename, $fields, &$packetsizes, 0]);
        $this->assertEquals(6, $endrow);

        // Very small packet size.
        $this->mock_maxallowedpacket(5);
        $endrow = $this->call_static_method('prepare_next_chunk', [$tablename, $fields, &$packetsizes, 0]);
        $this->assertEquals(1, $endrow);
        $endrow = $this->call_static_method('prepare_next_chunk', [$tablename, $fields, &$packetsizes, 2]);
        $this->assertEquals(3, $endrow);

        // Packet size just enough to fit two rows but not three.
        $this->mock_maxallowedpacket(79 + strlen($CFG->phpunit_prefix));
        $endrow = $this->call_static_method('prepare_next_chunk', [$tablename, $fields, &$packetsizes, 0]);
        $this->assertEquals(2, $endrow);
        $endrow = $this->call_static_method('prepare_next_chunk', [$tablename, $fields, &$packetsizes, 2]);
        $this->assertEquals(4, $endrow);

        $this->mock_maxallowedpacket(false);
    }
}
