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
require_once($CFG->dirroot.'/'.$CFG->admin.'/tool/vault/tests/fixtures/site_backup_mock.php');

/**
 * The site_backup_test test class.
 *
 * @covers      \tool_vault\site_backup
 * @package     tool_vault
 * @category    test
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class site_backup_test extends \advanced_testcase {

    /**
     * Cleanup all temp files
     *
     * @return void
     */
    public function tearDown(): void {
        tempfiles::cleanup();
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
    public function test_get_all_tables() {
        $this->resetAfterTest();
        $tables = $this->create_site_backup()->get_db_structure()->get_tables_actual();
        $this->assertTrue(array_key_exists('tool_vault_config', $tables));
        $this->assertTrue(array_key_exists('config', $tables));
    }

    public function test_export_db() {
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
        $this->assertTrue(file_exists($dirstruct.DIRECTORY_SEPARATOR.constants::FILE_STRUCTURE));
        $this->assertTrue(file_exists($dirstruct.DIRECTORY_SEPARATOR.constants::FILE_SEQUENCE));
        $this->assertFalse(in_array('tool_vault_config.0', $files));
        $this->assertFalse(in_array('tool_vault_operation.0', $files));
        $this->assertFalse(in_array('tool_vault_log.0', $files));

        // Retrieve user file, just for checks.
        $userlist = json_decode(file_get_contents($dir.'/'.'user.0.json'), true);
        $this->assertEquals('admin', $userlist[2][7]);

        // Retrieve config_plugins, make sure the version number for tool_vault is not included there.
        $config = json_decode(file_get_contents($dir.'/'.'config_plugins.0.json'), true);
        $this->assertEquals(['id', 'plugin', 'name', 'value'],
            array_shift($config)); // Fist row are column names.
        $f1 = array_filter($config, function($entry) {
            return $entry[1] === 'tool_vault';
        });
        $f2 = array_filter($config, function($entry) {
            return $entry[1] === 'tool_monitor';
        });
        $this->assertEmpty($f1);
        $this->assertNotEmpty($f2);

        tempfiles::remove_temp_dir($dirstruct);
        tempfiles::remove_temp_dir($dir);
    }

    public function test_export_dataroot() {
        $this->resetAfterTest();

        // Make a directory under dataroot and store a file there.
        $hellodir = make_upload_directory('helloworld');
        file_put_contents($hellodir.DIRECTORY_SEPARATOR.'hello.txt', 'Hello world!');

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

    public function test_export_filedir() {
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
