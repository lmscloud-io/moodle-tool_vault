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

namespace tool_vault\local\xmldb;

/**
 * The site_backup_test test class.
 *
 * @covers      \tool_vault\local\xmldb\dbstructure
 * @package     tool_vault
 * @category    test
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dbstructure_test extends \advanced_testcase {

    /**
     * Test for loading structure
     */
    public function test_dbstructure() {
        $definitions = dbstructure::load();
        // Loop through all tables and compare definitions with actual.
        foreach ($definitions->get_tables_actual() as $tablename => $actualtable) {
            if ($tablename === 'search_simpledb_index') {
                // TODO.
                // This table has some weird indexes created after installation.
                continue;
            }

            $definition = $definitions->find_table_definition($tablename);
            $this->assertEquals($definition->get_xmldb_table()->xmlOutput(),
                $actualtable->get_xmldb_table()->xmlOutput(),
            'Output does not match for the table "'.$tablename.'"');
        }

        // TODO add test when definition is different from actual.
    }

    /**
     * Test function retrieve_sequences()
     */
    public function test_sequences() {
        $this->resetAfterTest();

        $definitions = dbstructure::load();
        $seqs = $definitions->retrieve_sequences();
        $user1 = $this->getDataGenerator()->create_user();
        $this->assertEquals($user1->id, $seqs['user']);

        $definitions = dbstructure::load();
        $seqs = $definitions->retrieve_sequences();
        $userseq2 = $seqs['user'];
        $user2 = $this->getDataGenerator()->create_user();
        $this->assertEquals($user2->id, $userseq2);
    }
}
