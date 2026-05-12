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
 * The plugins_restore_test test class.
 *
 * @package     tool_vault
 * @category    test
 * @copyright   2026 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_vault\local\checks;

use tool_vault\constants;
use tool_vault\local\helpers\siteinfo;
use tool_vault\local\models\dryrun_model;

/**
 * Tests for the restore pre-check that compares plugin versions.
 *
 * @covers      \tool_vault\local\checks\plugins_restore
 * @package     tool_vault
 * @category    test
 * @copyright   2026 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plugins_restore_test extends \advanced_testcase {
    /**
     * Build a dryrun_model carrying the given plugin list as backup metadata.
     *
     * @param array $backupplugins pluginname => ['version' => int, ...]
     * @return dryrun_model
     */
    protected function create_dryrun(array $backupplugins): dryrun_model {
        global $CFG;
        $dryrun = new dryrun_model((object)[
            'status' => constants::STATUS_INPROGRESS,
            'backupkey' => 'testbackupkey',
        ]);
        $dryrun->save();
        $dryrun->set_remote_details([
            'metadata' => [
                'branch' => $CFG->branch,
                'plugins' => $backupplugins,
            ],
        ])->save();
        return $dryrun;
    }

    /**
     * Call a protected method on a plugins_restore instance.
     *
     * @param plugins_restore $check
     * @param string $method
     * @return mixed
     */
    protected function call_protected(plugins_restore $check, string $method) {
        $r = new \ReflectionMethod($check, $method);
        $r->setAccessible(true);
        return $r->invoke($check);
    }

    /**
     * When the backup plugin list mirrors the site, the pre-check passes.
     */
    public function test_success_when_versions_match(): void {
        $this->resetAfterTest();

        // Use the actual installed plugin list as the "backup" content.
        // tool_vault is excluded by perform() from the local list, so it must
        // also be absent from the backup list to avoid a missing-plugin failure.
        $backupplugins = siteinfo::get_plugins_list_full(true);
        unset($backupplugins['tool_vault']);

        $dryrun = $this->create_dryrun($backupplugins);
        $check = plugins_restore::create_and_run($dryrun);

        $this->assertEquals(constants::STATUS_FINISHED, $check->get_model()->status);
        $this->assertTrue($check->success());
        $this->assertSame([], $this->call_protected($check, 'problem_plugins'));
        $this->assertSame(
            get_string('addonplugins_success', 'tool_vault'),
            $check->get_status_message()
        );
    }

    /**
     * When the backup has a plugin at a higher version than installed locally,
     * the pre-check fails with the "Add-on plugins" / lower-local-version error.
     */
    public function test_fails_when_backup_has_higher_plugin_version(): void {
        $this->resetAfterTest();

        // Start from the actual installed plugin list and pick the first plugin
        // that has a known version on this site (on-disk-only plugins lack one).
        $backupplugins = siteinfo::get_plugins_list_full(true);
        unset($backupplugins['tool_vault']);
        $targetplugin = null;
        foreach ($backupplugins as $name => $info) {
            if (!empty($info['version'])) {
                $targetplugin = $name;
                break;
            }
        }
        $this->assertNotNull($targetplugin, 'No installed plugin with a version available');
        $localversion = (int)$backupplugins[$targetplugin]['version'];
        $backupplugins[$targetplugin]['version'] = $localversion + 1;

        $dryrun = $this->create_dryrun($backupplugins);
        $check = plugins_restore::create_and_run($dryrun);

        $this->assertEquals(constants::STATUS_FINISHED, $check->get_model()->status);
        $this->assertFalse($check->success());

        $problem = $this->call_protected($check, 'problem_plugins');
        $this->assertArrayHasKey($targetplugin, $problem);
        $this->assertSame($localversion + 1, (int)$problem[$targetplugin][0]['version']);
        $this->assertSame($localversion, (int)$problem[$targetplugin][1]['version']);

        // This is the user-visible message wrapped by restore_precheck_failed.
        $this->assertSame(
            get_string('addonplugins_fail', 'tool_vault'),
            $check->get_status_message()
        );
    }

    /**
     * When a plugin is listed in the restorepreserveplugins setting, the restore
     * will not touch it, so a version mismatch on that plugin must not fail the
     * pre-check.
     */
    public function test_preserved_plugin_version_mismatch_is_ignored(): void {
        $this->resetAfterTest();

        $backupplugins = siteinfo::get_plugins_list_full(true);
        unset($backupplugins['tool_vault']);
        $targetplugin = null;
        foreach ($backupplugins as $name => $info) {
            if (!empty($info['version'])) {
                $targetplugin = $name;
                break;
            }
        }
        $this->assertNotNull($targetplugin, 'No installed plugin with a version available');

        // Bump the backup version - without preservation this would trigger
        // problem_plugins() and the pre-check would fail.
        $localversion = (int)$backupplugins[$targetplugin]['version'];
        $backupplugins[$targetplugin]['version'] = $localversion + 1;

        // Mark the plugin as preserved during restore.
        set_config('restorepreserveplugins', $targetplugin, 'tool_vault');

        $dryrun = $this->create_dryrun($backupplugins);
        $check = plugins_restore::create_and_run($dryrun);

        $this->assertTrue(
            $check->success(),
            'Version mismatch on a preserved plugin must not fail the pre-check'
        );
        $this->assertArrayNotHasKey(
            $targetplugin,
            $this->call_protected($check, 'problem_plugins')
        );
    }
}
