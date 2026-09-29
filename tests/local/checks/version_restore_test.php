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

namespace tool_vault\local\checks;

use tool_vault\constants;
use tool_vault\local\models\dryrun_model;

#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vault\local\checks\version_restore::class)]
/**
 * Tests for the restore pre-check that compares Moodle versions.
 *
 * @covers      \tool_vault\local\checks\version_restore
 * @package     tool_vault
 * @category    test
 * @copyright   2026 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class version_restore_test extends \advanced_testcase {
    /**
     * Version and branch from the backup metadata are escaped in the summary.
     */
    public function test_summary_escapes_backup_metadata(): void {
        $this->resetAfterTest();

        $dryrun = new dryrun_model((object)[
            'status' => constants::STATUS_INPROGRESS,
            'backupkey' => 'testbackupkey',
        ]);
        $dryrun->save();
        $dryrun->set_remote_details([
            'metadata' => [
                'version' => '1<b>version</b>',
                'branch' => '<img src=x onerror=alert(1)>',
            ],
        ])->save();

        $check = version_restore::create_and_run($dryrun);
        $this->assertEquals(constants::STATUS_FINISHED, $check->get_model()->status);

        $summary = $check->summary();
        $this->assertStringContainsString('1&lt;b&gt;version&lt;/b&gt;', $summary);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $summary);
        $this->assertStringNotContainsString('<b>version', $summary);
        $this->assertStringNotContainsString('<img src=x', $summary);
    }

    /**
     * Version from the backup metadata is escaped in the failure message (backup is newer than the site).
     */
    public function test_failure_message_escapes_backup_version(): void {
        $this->resetAfterTest();

        $dryrun = new dryrun_model((object)[
            'status' => constants::STATUS_INPROGRESS,
            'backupkey' => 'testbackupkey',
        ]);
        $dryrun->save();
        $dryrun->set_remote_details([
            'metadata' => [
                'version' => '9999999999<b>version</b>',
                'branch' => '999',
            ],
        ])->save();

        $check = version_restore::create_and_run($dryrun);
        $this->assertEquals(constants::STATUS_FINISHED, $check->get_model()->status);
        $this->assertFalse($check->success());

        $message = $check->get_status_message();
        $this->assertStringContainsString('9999999999&lt;b&gt;version&lt;/b&gt;', $message);
        $this->assertStringNotContainsString('<b>version', $message);
        $this->assertStringNotContainsString('<b>version', $check->summary());
    }
}
