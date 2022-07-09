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
 * The dbtable_test test class.
 *
 * @covers      \tool_vault\local\xmldb\dbtable
 * @package     tool_vault
 * @category    test
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dbtable_test extends \advanced_testcase {

    /**
     * Test for get_alter_sql
     *
     * @return void
     */
    public function test_get_alter_sql() {
        $this->resetAfterTest();

        $structure = dbstructure::load();
        $table = $structure->get_tables_definitions()['config'];

        $this->assertNotEmpty($table->get_alter_sql(null));
    }

    /**
     * Test for get_alter_sql
     *
     * @return void
     */
    public function test_1() {
        $s = <<<EOF
    <TABLE NAME="config">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="name" TYPE="char" LENGTH="255" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="value" TYPE="text" NOTNULL="true" SEQUENCE="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
        <KEY NAME="name" TYPE="unique" FIELDS="name"/>
      </KEYS>
    </TABLE>
EOF;
        $xmltable = $this->table_from_xml($s);
        $s = new dbstructure();
        $table = new dbtable($xmltable, $s);
        $this->assertNotEmpty($table->get_alter_sql(null));
        $this->assertEmpty($table->get_alter_sql($table));

        $s2 = <<<EOF
    <TABLE NAME="config_log">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="plugin" TYPE="char" LENGTH="100" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="name" TYPE="char" LENGTH="100" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="value" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="oldvalue" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
        <KEY NAME="userid" TYPE="foreign" FIELDS="userid" REFTABLE="user" REFFIELDS="id"/>
      </KEYS>
      <INDEXES>
        <INDEX NAME="timemodified" UNIQUE="false" FIELDS="timemodified"/>
      </INDEXES>
    </TABLE>

EOF;
        $xmltable = $this->table_from_xml($s2);
        $s = new dbstructure();
        $table2 = new dbtable($xmltable, $s);
        $this->assertNotEmpty($table2->get_alter_sql($table));
    }

    /**
     * Get table from xml
     *
     * @param string $xml
     * @return \xmldb_table
     */
    protected function table_from_xml(string $xml): \xmldb_table {
        global $CFG;

        $xml = "<XMLDB>$xml</XMLDB>";

        $oldxmldb = $CFG->xmldbdisablecommentchecking ?? null;
        $CFG->xmldbdisablecommentchecking = 1;
        $xmlarr = xmlize($xml);
        foreach ($xmlarr['XMLDB']['#']['TABLE'] as $xmltable) {
            $name = strtolower(trim($xmltable['@']['NAME']));
            $table = new \xmldb_table($name);
            $table->arr2xmldb_table($xmltable);
        }
        set_config('xmldbdisablecommentchecking', $oldxmldb);
        return $table;
    }
}
