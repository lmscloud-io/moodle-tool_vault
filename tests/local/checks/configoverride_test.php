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

#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vault\local\checks\configoverride::class)]
/**
 * Tests for the backup pre-check that analyses settings overridden in config.php
 *
 * @covers      \tool_vault\local\checks\configoverride
 * @package     tool_vault
 * @category    test
 * @copyright   2026 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class configoverride_test extends \advanced_testcase {
    /**
     * Values of the settings that are not included in the backup are not stored.
     */
    public function test_perform(): void {
        global $CFG, $DB;
        $this->resetAfterTest();

        set_config('toolvaulttestincluded', 'dbvalue');
        set_config('toolvaulttestincludedplugin', 'dbvalue', 'tool_vault');
        $CFG->config_php_settings['toolvaulttestincluded'] = 'includedvalue';
        $CFG->config_php_settings['toolvaulttestsecret'] = 'secretvalue1';
        $CFG->forced_plugin_settings['tool_vault']['toolvaulttestincludedplugin'] = 'includedpluginvalue';
        $CFG->forced_plugin_settings['tool_vault']['toolvaulttestsecretplugin'] = 'secretvalue2';

        /** @var configoverride $check */
        $check = configoverride::create_and_run();
        $this->assertEquals(constants::STATUS_FINISHED, $check->get_model()->status);

        $details = $check->get_model()->get_details();
        $this->assertSame('includedvalue', $details['config_php_settings_included']['toolvaulttestincluded']);
        $this->assertArrayHasKey('toolvaulttestsecret', $details['config_php_settings_notincluded']);
        $this->assertNull($details['config_php_settings_notincluded']['toolvaulttestsecret']);
        $this->assertSame(
            'includedpluginvalue',
            $details['forced_plugin_settings_included']['tool_vault']['toolvaulttestincludedplugin']
        );
        $this->assertArrayHasKey('toolvaulttestsecretplugin', $details['forced_plugin_settings_notincluded']['tool_vault']);
        $this->assertNull($details['forced_plugin_settings_notincluded']['tool_vault']['toolvaulttestsecretplugin']);

        // Secret values are not stored in the database.
        $record = $DB->get_record('tool_vault_operation', ['id' => $check->get_model()->id], '*', MUST_EXIST);
        $this->assertStringNotContainsString('secretvalue', json_encode($record));

        // Only included settings are added to the backup.
        $overrides = $check->get_config_overrides_for_backup();
        $this->assertContains(['name' => 'toolvaulttestincluded', 'value' => 'includedvalue', 'plugin' => null], $overrides);
        $this->assertContains(
            ['name' => 'toolvaulttestincludedplugin', 'value' => 'includedpluginvalue', 'plugin' => 'tool_vault'],
            $overrides
        );
        $this->assertStringNotContainsString('toolvaulttestsecret', json_encode($overrides));

        // Settings that are not included are still listed in the report.
        $this->assertTrue($check->has_details());
        $report = $check->detailed_report();
        $this->assertStringContainsString('toolvaulttestsecret', $report);
        $this->assertStringContainsString('toolvaulttestsecretplugin', $report);
        $this->assertStringNotContainsString('secretvalue', $report);
    }
}
