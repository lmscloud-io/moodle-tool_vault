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
 * The site_backup_test test class.
 *
 * @package     tool_vault
 * @category    test
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_vault;

use tool_vault\fixtures\site_backup_mock;
use tool_vault\local\helpers\tempfiles;
use tool_vault\local\models\backup_model;
use tool_vault\local\xmldb\dbtable;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/vault/tests/fixtures/site_backup_mock.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vault\site_backup::class)]
/**
 * The site_backup_test test class.
 *
 * @covers      \tool_vault\site_backup
 * @package     tool_vault
 * @category    test
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class site_backup_test extends \advanced_testcase {
    /**
     * Cleanup all temp files
     *
     * @return void
     */
    public function tearDown(): void {
        tempfiles::cleanup();
        parent::tearDown();
    }

    /**
     * Create mock site backup instance
     *
     * @return site_backup_mock
     */
    protected function create_site_backup() {
        $backup = new backup_model((object)['status' => constants::STATUS_INPROGRESS]);
        $backup->save();
        return new site_backup_mock($backup);
    }

    /**
     * Testing get_all_tables()
     */
    public function test_get_all_tables(): void {
        $this->resetAfterTest();
        $tables = $this->create_site_backup()->get_db_structure()->get_tables_actual();
        $this->assertTrue(array_key_exists('tool_vault_config', $tables));
        $this->assertTrue(array_key_exists('config', $tables));
    }

    public function test_export_table(): void {
        $this->resetAfterTest();
        $sitebackup = $this->create_site_backup();
        $tableobj = dbtable::create_from_actual_db('tool_vault_config', $sitebackup->get_db_structure());

        // We will use table tool_vault_config as a temp table.
        api::store_config('n0', 'value');
        api::store_config('n1', null);
        api::store_config('n2', '');
        api::store_config('n3', 'null');
        api::store_config('n4', 'NULL');
        api::store_config('n5', 'value "with" quotes');
        api::store_config('n6', "value\nwith\nnewlines");

        $dir = tempfiles::make_temp_dir('test-dbstruct-');
        $sitebackup->export_table_data($tableobj, $dir);

        $data = json_decode(file_get_contents($dir . DIRECTORY_SEPARATOR . 'tool_vault_config.0.json'), true);
        $this->assertEquals(['name', 'n0', 'n1', 'n2', 'n3', 'n4', 'n5', 'n6'], array_column($data, 1));
        $this->assertEquals(
            ['value', 'value', null, '', 'null', 'NULL', 'value "with" quotes', "value\nwith\nnewlines"],
            array_column($data, 2)
        );

        // Close archive, remove temp folder and also clear the curl mock stack.
        $sitebackup->get_files_backup(constants::FILENAME_DBDUMP)->finish();
        tempfiles::remove_temp_dir($dir);
        $curl = new \curl();
        $curl->get('');
        $curl->get('');
        $curl->get('');
    }

    public function test_export_table_with_reserved_words(): void {
        global $DB;
        $this->resetAfterTest();

        // Create a temp table with a column that is a reserved word.
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('test_table');
        $table->add_field('id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('desc', XMLDB_TYPE_CHAR, 255);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $dbman->create_temp_table($table);
        // Insert some data.
        $id = $DB->execute("INSERT INTO {test_table} (" .
            $dbman->generator->getEncQuoted('desc') . ") VALUES (?)", ['value']);

        // Export this table. Make sure data is exported and there are no quotes in the field names in the json.
        $sitebackup = $this->create_site_backup();
        $tableobj = dbtable::create_from_actual_db('test_table', $sitebackup->get_db_structure());
        $dir = tempfiles::make_temp_dir('test-dbstruct-');
        $sitebackup->export_table_data($tableobj, $dir);
        $jsoncontens = json_decode(file_get_contents($dir . DIRECTORY_SEPARATOR . 'test_table.0.json'), true);

        $this->assertEquals([['id', 'desc'], [(string)$id, 'value']], $jsoncontens);

        // Close archive, remove temp folder and also clear the curl mock stack. Drop temp table.
        $sitebackup->get_files_backup(constants::FILENAME_DBDUMP)->finish();
        tempfiles::remove_temp_dir($dir);
        $curl = new \curl();
        $curl->get('');
        $curl->get('');
        $curl->get('');
        $dbman->drop_table($table);
    }

    public function test_export_table_in_chunks(): void {
        global $DB;
        $this->resetAfterTest();
        $dbman = $DB->get_manager();

        // Table with an 'id' column.
        $table1 = new \xmldb_table('test_table_withid');
        $table1->add_field('id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table1->add_field('name', XMLDB_TYPE_CHAR, 255);
        $table1->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $dbman->create_temp_table($table1);

        // Table without an 'id' column, the first column has duplicates and nulls.
        $table2 = new \xmldb_table('test_table_noid');
        $table2->add_field('name', XMLDB_TYPE_CHAR, 255);
        $table2->add_field('info', XMLDB_TYPE_CHAR, 255);
        $dbman->create_temp_table($table2);

        $names = ['a', 'a', 'a', 'b', null];
        foreach ($names as $i => $name) {
            $DB->execute("INSERT INTO {test_table_withid} (name) VALUES (?)", [$name]);
            $DB->execute("INSERT INTO {test_table_noid} (name, info) VALUES (?, ?)", [$name, "r$i"]);
        }

        $sitebackup = $this->create_site_backup();
        $sitebackup->chunksize = 2;
        $dir = tempfiles::make_temp_dir('test-dbstruct-');
        try {
            foreach (['test_table_withid', 'test_table_noid'] as $tablename) {
                $tableobj = dbtable::create_from_actual_db($tablename, $sitebackup->get_db_structure());
                $sitebackup->export_table_data($tableobj, $dir);
            }

            // Table with 'id' is exported in chunks of two rows.
            $ids = [];
            for ($i = 0; $i < 3; $i++) {
                $data = json_decode(file_get_contents($dir . "/test_table_withid.$i.json"), true);
                $this->assertEquals(['id', 'name'], array_shift($data));
                $this->assertCount($i < 2 ? 2 : 1, $data);
                $ids = array_merge($ids, array_column($data, 0));
            }
            $this->assertFalse(file_exists($dir . '/test_table_withid.3.json'));
            $this->assertEqualsCanonicalizing($DB->get_fieldset_sql('SELECT id FROM {test_table_withid}'), $ids);

            // Table without 'id' is exported in one file with all rows.
            $data = json_decode(file_get_contents($dir . '/test_table_noid.0.json'), true);
            $this->assertEquals(['name', 'info'], array_shift($data));
            $this->assertEqualsCanonicalizing(['r0', 'r1', 'r2', 'r3', 'r4'], array_column($data, 1));
            $this->assertFalse(file_exists($dir . '/test_table_noid.1.json'));
        } finally {
            // Close archive, remove temp folder and also clear the curl mock stack. Drop temp tables.
            $sitebackup->get_files_backup(constants::FILENAME_DBDUMP)->finish();
            tempfiles::remove_temp_dir($dir);
            $curl = new \curl();
            $curl->get('');
            $curl->get('');
            $curl->get('');
            $dbman->drop_table($table1);
            $dbman->drop_table($table2);
        }
    }

    public function test_export_db(): void {
        if (!PHPUNIT_LONGTEST) {
            $this->markTestSkipped('PHPUNIT_LONGTEST is not defined');
        }

        $this->resetAfterTest();
        $this->setAdminUser();
        $sitebackup = $this->create_site_backup();
        $sitebackup->prepare();
        $sitebackup->export_db();
        [$filepathstructure] = $sitebackup->get_files_backup(constants::FILENAME_DBSTRUCTURE)->uploadedfiles;
        [$filepath] = $sitebackup->get_files_backup(constants::FILENAME_DBDUMP)->uploadedfiles;
        $this->assertGreaterThanOrEqual(100000, filesize($filepath));

        // Unpack and check contents.
        $x = new \zip_packer();
        $dirstruct = tempfiles::make_temp_dir('test-dbstruct-');
        $dir = tempfiles::make_temp_dir('test-dbdump-');
        try {
            $x->extract_to_pathname($filepathstructure, $dirstruct);
            $x->extract_to_pathname($filepath, $dir);
            $handle = opendir($dir);
            $files = [];
            while (($file = readdir($handle)) !== false) {
                if (!preg_match('/^[\\._]/', $file) && ($p = pathinfo($file)) && ($p['extension'] === 'json')) {
                    $files[] = $p['filename'];
                }
            }
            closedir($handle);

            $this->assertTrue(in_array('config.0', $files));
            $this->assertTrue(in_array('user.0', $files));
            $this->assertTrue(file_exists($dirstruct . DIRECTORY_SEPARATOR . constants::FILE_STRUCTURE));
            $this->assertTrue(file_exists($dirstruct . DIRECTORY_SEPARATOR . constants::FILE_SEQUENCE));
            $this->assertFalse(in_array('tool_vault_config.0', $files));
            $this->assertFalse(in_array('tool_vault_operation.0', $files));
            $this->assertFalse(in_array('tool_vault_log.0', $files));

            // Large tables are exported in several chunks (the number depends on the table size on disk),
            // read the rows from all files for the table.
            $readtable = function (string $tablename) use ($dir, $files): array {
                $rows = [];
                foreach ($files as $file) {
                    if (preg_match('/^' . preg_quote($tablename, '/') . '\\.\\d+$/', $file)) {
                        $data = json_decode(file_get_contents($dir . '/' . $file . '.json'), true);
                        $columns = array_shift($data); // Fist row are column names.
                        $rows = array_merge($rows, array_map(fn($row) => array_combine($columns, $row), $data));
                    }
                }
                return $rows;
            };

            // Retrieve users, just for checks.
            $this->assertContains('admin', array_column($readtable('user'), 'username'));

            // Retrieve config_plugins, make sure the version number for tool_vault is not included there.
            $config = json_decode(file_get_contents($dir . '/' . 'config_plugins.0.json'), true);
            $this->assertEquals(
                ['id', 'plugin', 'name', 'value'],
                array_shift($config)
            ); // Fist row are column names.
            $config = array_map('array_values', $readtable('config_plugins'));
            $f1 = array_filter($config, function ($entry) {
                return $entry[1] === 'tool_vault';
            });
            $f2 = array_filter($config, function ($entry) {
                return $entry[1] === 'tool_monitor';
            });
            $this->assertEmpty($f1);
            $this->assertNotEmpty($f2);
        } finally {
            tempfiles::remove_temp_dir($dirstruct);
            tempfiles::remove_temp_dir($dir);
        }
    }

    public function test_export_dataroot(): void {
        $this->resetAfterTest();

        // Make a directory under dataroot and store a file there.
        $hellodir = make_upload_directory('helloworld');
        file_put_contents($hellodir . DIRECTORY_SEPARATOR . 'hello.txt', 'Hello world!');

        // Call export_dataroot() from site_backup.
        $sitebackup = $this->create_site_backup();
        $sitebackup->export_dataroot();
        [$filepath] = $sitebackup->get_files_backup(constants::FILENAME_DATAROOT)->uploadedfiles;
        $this->assertTrue(file_exists($filepath));

        // Unpack and check contents.
        $x = new \zip_packer();
        $dir = tempfiles::make_temp_dir('test-dataroot-');
        $x->extract_to_pathname($filepath, $dir);

        // Make sure a helloworld file was present in the archive.
        $this->assertTrue(file_exists($dir . '/helloworld/hello.txt'));
        $this->assertEquals('Hello world!', file_get_contents($dir . '/helloworld/hello.txt'));

        tempfiles::remove_temp_dir($dir);
    }

    public function test_export_filedir(): void {
        $this->resetAfterTest();

        $sitebackup = $this->create_site_backup();
        $sitebackup->export_filedir();
        $filepaths = $sitebackup->get_files_backup(constants::FILENAME_FILEDIR)->uploadedfiles;
        $this->assertEquals(1, count($filepaths));
        $filepath = reset($filepaths);
        $this->assertGreaterThanOrEqual(1000, filesize($filepath));

        // Unpack and check contents.
        $x = new \zip_packer();
        $dir = tempfiles::make_temp_dir('test-filedir-');
        $x->extract_to_pathname($filepath, $dir);

        // Make sure a file for empty file was present in the archive.
        $emptyfile = sha1('');
        $this->assertTrue(file_exists($dir . '/' . substr($emptyfile, 0, 2) . '/' .
            substr($emptyfile, 2, 2) . '/' . $emptyfile));

        tempfiles::remove_temp_dir($dir);
    }
}
