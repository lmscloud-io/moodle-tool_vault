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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_vault\privacy;

use core_privacy\local\metadata\types\external_location;
use core_privacy\manager;
use core_privacy\local\metadata\collection;

/**
 * Tests for Vault - Site migration
 *
 * @covers     \tool_vault\privacy\provider
 * @package    tool_vault
 * @category   test
 * @copyright  2024 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider_test extends \advanced_testcase {

    /** @var string */
    const COMPONENT = 'tool_vault';
    /** @var string */
    const CLASSNAME = provider::class;

    /**
     * Test that the specified null_provider works as expected.
     */
    public function test_null_provider() {
        $classname = self::CLASSNAME;
        $reason = $classname::get_reason();
        $this->assertTrue((string)$reason === $reason);

        // Mdlcode-disable-next-line cannot-parse-string.
        $this->assertNotEmpty(get_string($reason, self::COMPONENT));
        $this->assertDebuggingNotCalled();
    }

    /**
     * Test that the plugin is compliant.
     */
    public function test_all_providers_compliant() {
        $manager = new manager();
        $this->assertTrue($manager->component_is_compliant(self::COMPONENT));
    }

    /**
     * Test that get_metadata() returns valid string identifiers.
     */
    public function test_link_external_location() {
        $collection = new collection(self::COMPONENT);
        $collection = provider::get_metadata($collection);
        $this->assertNotEmpty($collection);
        $items = $collection->get_collection();
        $this->assertEquals(1, count($items));
        $item = reset($items);
        $this->assertInstanceOf(external_location::class, $item);

        // Mdlcode-disable-next-line cannot-parse-string.
        $this->assertNotEmpty(get_string($item->get_summary(), self::COMPONENT));
        $privacyfields = $item->get_privacy_fields();
        $this->assertNotEmpty($privacyfields);
        if (!empty($privacyfields)) {
            foreach ($privacyfields as $key => $field) {
                // Mdlcode-disable-next-line cannot-parse-string.
                $this->assertNotEmpty(get_string($field, self::COMPONENT));
            }
        }
    }
}
