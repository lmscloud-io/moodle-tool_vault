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

namespace tool_vault;

use tool_vault\local\xmldb\dbtable;

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
     * Testing get_all_tables()
     */
    public function test_get_all_tables() {
        $tables = (new site_backup(""))->get_db_structure()->get_tables_actual();
        $this->assertFalse(array_key_exists('tool_vault_config', $tables));
        $this->assertTrue(array_key_exists('config', $tables));
    }

    public function test_export_table() {
        $this->resetAfterTest();
        $sitebackup = new site_backup("");
        $tableobj = dbtable::create_from_actual_db('tool_vault_config', $sitebackup->get_db_structure());

        // We will use table tool_vault_config as a temp table.
        api::store_config('n0', 'value');
        api::store_config('n1', null);
        api::store_config('n2', '');
        api::store_config('n3', 'null');
        api::store_config('n4', 'NULL');
        api::store_config('n5', 'value "with" quotes');
        api::store_config('n6', "value\nwith\nnewlines");

        $dir = make_temp_directory(constants::FILENAME_DBDUMP);
        $table = 'tool_vault_config';
        $filepath = $dir.DIRECTORY_SEPARATOR.$table.'.json';
        $sitebackup->export_table_data($tableobj, $filepath);

        $data = json_decode(file_get_contents($dir.DIRECTORY_SEPARATOR.'tool_vault_config.json'), true);
        $this->assertEquals(['name', 'n0', 'n1', 'n2', 'n3', 'n4', 'n5', 'n6'], array_column($data, 1));
        $this->assertEquals(['value', 'value', null, '', 'null', 'NULL', 'value "with" quotes', "value\nwith\nnewlines"],
            array_column($data, 2));
    }

    public function test_export_db() {
        $this->resetAfterTest();
        $sitebackup = new site_backup("");
        $filepath = $sitebackup->export_db(constants::FILENAME_DBDUMP . '.zip');
        $this->assertGreaterThanOrEqual(150000, filesize($filepath));

        // Unpack and check contents.
        $x = new \zip_packer();
        $dir = make_temp_directory('dbextracted');
        $x->extract_to_pathname($filepath, $dir);
        $handle = opendir($dir);
        $files = [];
        while (($file = readdir($handle)) !== false) {
            if (!preg_match('/^[\\._]/', $file) && ($p = pathinfo($file)) && ($p['extension'] === 'json')) {
                $files[] = $p['filename'];
            }
        }
        closedir($handle);

        $this->assertTrue(in_array('config', $files));
        $this->assertTrue(in_array('user', $files));
        $this->assertTrue(file_exists($dir.DIRECTORY_SEPARATOR.constants::FILE_STRUCTURE));
        $this->assertTrue(file_exists($dir.DIRECTORY_SEPARATOR.constants::FILE_SEQUENCE));
        $this->assertFalse(in_array('tool_vault_config', $files));
        $this->assertFalse(in_array('tool_vault_backups', $files));
        $this->assertFalse(in_array('tool_vault_restores', $files));

        // Retrieve user file, just for checks.
        $userlist = json_decode(file_get_contents($dir.'/'.'user.json'), true);
        $this->assertEquals('admin', $userlist[2][7]);
    }

    public function test_export_dataroot() {
        $this->resetAfterTest();

        $sitebackup = new site_backup("");
        $filepath = $sitebackup->export_dataroot(constants::FILENAME_DATAROOT . '.zip');
        $this->assertGreaterThanOrEqual(150000, filesize($filepath));

        // Unpack and check contents.
        $x = new \zip_packer();
        $dir = make_temp_directory('dbextracted');
        $x->extract_to_pathname($filepath, $dir);

        // Make sure a file for empty file was present in the archive.
        $emptyfile = sha1('');
        $this->assertTrue(file_exists($dir . '/filedir/' .  substr($emptyfile, 0, 2) . '/' .
            substr($emptyfile, 2, 2) . '/' . $emptyfile));
    }
}
