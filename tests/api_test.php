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

namespace tool_vault;

use tool_vault\local\helpers\tempfiles;

#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vault\api::class)]
/**
 * The api_test test class.
 *
 * @covers      \tool_vault\api
 * @package     tool_vault
 * @category    test
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class api_test extends \advanced_testcase {
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
     * Dummy test.
     *
     * This is to be replaced by some actually usefule test.
     */
    public function test_dummy(): void {
        $this->assertNotEmpty(\core_component::get_component_directory('tool_vault'));
    }

    /**
     * Data provider for test_is_s3_url()
     *
     * @return array
     */
    public static function is_s3_url_provider(): array {
        return [
            ['https://bucket.s3.amazonaws.com/', true],
            ['https://my-bucket.name.s3.amazonaws.com/path/file.zip?X-Amz-Signature=abc', true],
            ['https://test.s3.amazonaws.com/', true],
            ['http://bucket.s3.amazonaws.com/', false],
            ['https://s3.amazonaws.com/', false],
            ['https://evil.example?x.s3.amazonaws.com/', false],
            ['https://evil.example#x.s3.amazonaws.com/', false],
            ['https://evil.example/x.s3.amazonaws.com/', false],
            ['https://user@evil.example:x.s3.amazonaws.com/', false],
            ['https://bucket.s3.amazonaws.com.evil.example/', false],
            ['', false],
            [null, false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('is_s3_url_provider')]
    /**
     * Test for is_s3_url()
     *
     * @dataProvider is_s3_url_provider
     * @param string|null $url
     * @param bool $expected
     */
    public function test_is_s3_url(?string $url, bool $expected): void {
        $this->assertSame($expected, api::is_s3_url($url));
    }
}
