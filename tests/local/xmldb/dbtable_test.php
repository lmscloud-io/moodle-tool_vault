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

use tool_vault\constants;

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
    public function test_get_alter_sql_xml() {
        $table = $this->fixture_config();
        $this->assertNotEmpty($table->get_alter_sql(null));
        $this->assertEmpty($table->get_alter_sql($table));

        $table2 = $this->fixture_config_log();
        $this->assertNotEmpty($table2->get_alter_sql($table));
    }

    /**
     * Get table from xml
     *
     * @param string $xml
     * @return dbtable
     */
    protected function table_from_xml(string $xml): dbtable {
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
        return new dbtable($table);
    }

    /**
     * Fixture
     *
     * @return dbtable
     */
    protected function fixture_config(): dbtable {
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
        return $this->table_from_xml($s);
    }

    /**
     * Fixture
     *
     * @return dbtable
     */
    protected function fixture_config_log(): dbtable {
        $s = <<<EOF
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
        return $this->table_from_xml($s);
    }

    /**
     * Fixture
     *
     * @return dbtable
     */
    protected function fixture_config_log_wrong_order(): dbtable {
        $s = <<<EOF
    <TABLE NAME="config_log">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="value" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="plugin" TYPE="char" LENGTH="100" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="name" TYPE="char" LENGTH="100" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="oldvalue" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
      </KEYS>
      <INDEXES>
        <INDEX NAME="timemodified" UNIQUE="false" FIELDS="timemodified"/>
        <INDEX NAME="something" UNIQUE="false" FIELDS="userid"/>
      </INDEXES>
    </TABLE>
EOF;
        return $this->table_from_xml($s);
    }

    /**
     * Fixture
     *
     * @return dbtable
     */
    protected function fixture_config_log_modified_fields(): dbtable {
        $s = <<<EOF
    <TABLE NAME="config_log">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="18" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="value" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="userid" TYPE="int" LENGTH="18" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="plugin" TYPE="char" LENGTH="150" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="namerenamed" TYPE="char" LENGTH="100" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="oldvalue" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="timemodified" TYPE="int" LENGTH="18" NOTNULL="true" SEQUENCE="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
      </KEYS>
      <INDEXES>
        <INDEX NAME="timemodified" UNIQUE="false" FIELDS="timemodified"/>
        <INDEX NAME="something" UNIQUE="false" FIELDS="userid"/>
      </INDEXES>
    </TABLE>
EOF;
        return $this->table_from_xml($s);
    }

    /**
     * Fixture
     *
     * @return dbtable
     */
    protected function fixture_config_log_modified_indexes(): dbtable {
        $s = <<<EOF
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
        <INDEX NAME="timemodified" UNIQUE="true" FIELDS="timemodified"/>
      </INDEXES>
    </TABLE>
EOF;
        return $this->table_from_xml($s);
    }

    /**
     * Test for compare_with_other_table()
     */
    public function test_compare_with_other_table() {
        $deftable = $this->fixture_config_log();
        $table = $this->fixture_config_log_wrong_order();
        $res = $table->compare_with_other_table($deftable);
        $this->assertEmpty($res);

        $table = $this->fixture_config_log_modified_fields();
        $res = $table->compare_with_other_table($deftable);
        $this->assertEquals([constants::DIFF_EXTRACOLUMNS, constants::DIFF_MISSINGCOLUMNS, constants::DIFF_CHANGEDCOLUMNS],
            array_keys($res));
        $this->assertCount(1, $res[constants::DIFF_EXTRACOLUMNS]);
        $this->assertEquals('namerenamed', $res[constants::DIFF_EXTRACOLUMNS][0]->getName());
        $this->assertCount(1, $res[constants::DIFF_MISSINGCOLUMNS]);
        $this->assertEquals('name', $res[constants::DIFF_MISSINGCOLUMNS][0]->getName());
        $this->assertCount(1, $res[constants::DIFF_CHANGEDCOLUMNS]);
        $this->assertEquals('plugin', $res[constants::DIFF_CHANGEDCOLUMNS][0]->getName());

        $table = $this->fixture_config_log_modified_indexes();
        $res = $table->compare_with_other_table($deftable);
        $this->assertEquals([constants::DIFF_EXTRAINDEXES, constants::DIFF_MISSINGINDEXES], array_keys($res));
        $this->assertCount(1, $res[constants::DIFF_EXTRAINDEXES]);
        $this->assertEquals(['timemodified'], $res[constants::DIFF_EXTRAINDEXES][0]->getFields());
        $this->assertTrue($res['extraindexes'][0]->getUnique());
        $this->assertCount(1, $res[constants::DIFF_MISSINGINDEXES]);
        $this->assertEquals(['timemodified'], $res[constants::DIFF_MISSINGINDEXES][0]->getFields());
        $this->assertFalse($res[constants::DIFF_MISSINGINDEXES][0]->getUnique());

        $res = $deftable->compare_with_other_table(null);
        $this->assertEquals([constants::DIFF_EXTRATABLES], array_keys($res));
        $this->assertCount(1, $res[constants::DIFF_EXTRATABLES]);
        $this->assertEquals('config_log', $res[constants::DIFF_EXTRATABLES][0]->getName());
    }
}
