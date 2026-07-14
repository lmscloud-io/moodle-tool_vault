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

namespace tool_vault\local\xmldb;

/**
 * The database_column_info_test test class.
 *
 * @covers      \tool_vault\local\xmldb\database_column_info
 * @package     tool_vault
 * @category    test
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class database_column_info_test extends \advanced_testcase {
    /**
     * Invoke the protected fix_field_properties() method on a numeric field with the given default.
     *
     * @param int $type one of XMLDB_TYPE_INTEGER, XMLDB_TYPE_NUMBER, XMLDB_TYPE_FLOAT
     * @param mixed $default default value to set on the field before fixing
     * @return mixed the default value after fixing
     */
    protected function fix_numeric_default(int $type, $default) {
        $field = new \xmldb_field('somefield');
        $field->setType($type);
        $field->setLength(10);
        $field->setNotNull(true);
        $field->setDefault($default);

        $dci = database_column_info::clone_from(new \database_column_info((object)['meta_type' => 'I']));
        $rm = new \ReflectionMethod(database_column_info::class, 'fix_field_properties');
        $rm->setAccessible(true);
        $rm->invoke($dci, $field, null);

        return $field->getDefault();
    }

    /**
     * An empty string default on a numeric column is invalid and must be turned into "no default" (null),
     * otherwise the DB generator produces invalid "... NOT NULL DEFAULT ," SQL.
     */
    public function test_fix_field_properties_strips_empty_numeric_default(): void {
        $this->assertNull($this->fix_numeric_default(XMLDB_TYPE_INTEGER, ''));
        $this->assertNull($this->fix_numeric_default(XMLDB_TYPE_NUMBER, ''));
        $this->assertNull($this->fix_numeric_default(XMLDB_TYPE_FLOAT, ''));
    }

    /**
     * A genuine numeric default (including zero) must be preserved.
     */
    public function test_fix_field_properties_keeps_real_numeric_default(): void {
        $this->assertSame('0', $this->fix_numeric_default(XMLDB_TYPE_INTEGER, '0'));
        $this->assertSame('5', $this->fix_numeric_default(XMLDB_TYPE_INTEGER, '5'));
        $this->assertNull($this->fix_numeric_default(XMLDB_TYPE_INTEGER, null));
    }
}
