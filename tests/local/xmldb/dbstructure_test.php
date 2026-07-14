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
 * The dbstructure_test test class.
 *
 * @covers      \tool_vault\local\xmldb\dbstructure
 * @package     tool_vault
 * @category    test
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dbstructure_test extends \advanced_testcase {
    /**
     * Test for loading structure
     */
    public function test_dbstructure(): void {
        $definitions = dbstructure::load();
        // Loop through all tables and compare definitions with actual.
        foreach ($definitions->get_tables_actual() as $tablename => $actualtable) {
            $definition = $definitions->find_table_definition($tablename);
            $this->assertEquals(
                $definition->get_xmldb_table()->xmlOutput(),
                $actualtable->get_xmldb_table()->xmlOutput(),
                'Output does not match for the table "' . $tablename . '"'
            );
        }

        // TODO add test when definition is different from actual.
    }

    /**
     * A backup made on an older DB may contain NOT NULL integer fields with an empty-string
     * default (DEFAULT=""). This must not produce malformed "... NOT NULL DEFAULT ," SQL on restore.
     *
     * @covers \tool_vault\local\xmldb\dbstructure::fix_table_xml_from_backup
     */
    public function test_backup_xml_empty_numeric_default(): void {
        global $DB;
        $this->resetAfterTest();

        $xml = <<<EOF
<?xml version="1.0" encoding="UTF-8" ?>
<XMLDB xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="xmldb.xsd">
  <TABLES>
    <TABLE NAME="tool_vault_unittest" COMPONENT="core">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="starttime" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="" SEQUENCE="false"/>
        <FIELD NAME="endtime" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="" SEQUENCE="false"/>
        <FIELD NAME="sampleorigin" TYPE="char" LENGTH="255" NOTNULL="true" DEFAULT="" SEQUENCE="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
      </KEYS>
    </TABLE>
  </TABLES>
</XMLDB>
EOF;
        $tmpfile = make_request_directory() . '/structure.xml';
        file_put_contents($tmpfile, $xml);

        // Call the protected backup-xml loader directly (skips the full DB scan done by load_from_backup()).
        $structure = new dbstructure();
        $rm = new \ReflectionMethod(dbstructure::class, 'load_definitions_from_backup_xml');
        $rm->setAccessible(true);
        $rm->invoke($structure, $tmpfile);

        // Mdlcode-disable-next-line cannot-parse-db-tablename.
        $table = $structure->get_backup_tables()['tool_vault_unittest'];
        $sqls = $table->get_alter_sql(null);

        // The generated CREATE TABLE must not contain an empty DEFAULT clause.
        $this->assertStringNotContainsString('DEFAULT ,', implode("\n", $sqls));

        // And it must actually be executable against the database.
        $DB->change_database_structure($sqls);
        $dbman = $DB->get_manager();
        // Mdlcode-disable-next-line cannot-parse-db-tablename.
        $xmldbtable = new \xmldb_table('tool_vault_unittest');
        $this->assertTrue($dbman->table_exists($xmldbtable));
        $dbman->drop_table($xmldbtable);
    }

    /**
     * Test function retrieve_sequences()
     */
    public function test_sequences(): void {
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
