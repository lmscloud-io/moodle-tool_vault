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

/**
 * Behat step definitions for tool_vault
 *
 * @package   tool_vault
 * @copyright 2022 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

/**
 * Behat step definitions for scheduled vault administration.
 *
 * @package   tool_vault
 * @copyright 2022 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_tool_vault extends behat_base {

    /**
     * Skip tests if the test API url is not set
     *
     * @Given test API key for :type account is specified for tool_vault
     */
    public function test_api_key_is_specified_for_tool_vault($type) {
        $key = '';
        if (($type === 'pro' || $type === 'any')
                && (defined('TOOL_VAULT_TEST_API_KEY') && !empty(TOOL_VAULT_TEST_API_KEY))) {
            $key = TOOL_VAULT_TEST_API_KEY;
        } else if (($type === 'light' || $type === 'any')
                && (defined('TOOL_VAULT_TEST_API_KEY_LIGHT') && !empty(TOOL_VAULT_TEST_API_KEY_LIGHT))) {
            $key = TOOL_VAULT_TEST_API_KEY_LIGHT;
        }
        if ($key) {
            \tool_vault\api::set_api_key($key);
        } else {
            throw new \Moodle\BehatExtension\Exception\SkippedException();
        }
    }

    /**
     * Skip tests if the storage is not in whitelisted
     *
     * @Given storage :storage should be tested in tool_vault
     */
    public function storage_should_be_tested_in_tool_vault($storage) {
        $definedstorage = defined('TOOL_VAULT_TEST_STORAGE') && !empty(TOOL_VAULT_TEST_STORAGE) ?
            TOOL_VAULT_TEST_STORAGE : '';
        if ($storage === $definedstorage || $definedstorage === '*') {
            return;
        }
        if ($definedstorage === '' && preg_match('/^eu/i', $storage)) {
            return;
        }
        throw new \Moodle\BehatExtension\Exception\SkippedException();
    }

    /**
     * Generate a random backup name and set it in the form
     *
     * @Given /^I set vault backup description field$/
     */
    public function set_vault_backup_description_field() {
        global $CFG;
        $backupname = random_string(15) . ' (' . $CFG->branch . ')';
        set_config('behat_backup_name', $backupname, 'tool_vault');
        $this->execute('behat_forms::i_set_the_field_in_container_to', [
            get_string('backupdescription', 'tool_vault'),
            get_string('startbackup', 'tool_vault'),
            "dialogue",
            $backupname,
        ]);
    }

    /**
     * Generate a random backup name and set it in the form
     *
     * @Given I set vault backup storage field to :storage
     */
    public function set_vault_backup_storage_field($storage) {
        global $CFG;
        if (!strlen((string)$storage)) {
            // Do not set anything, leave default.
            // This will also pass if the storage selector is not displayed at all.
            return;
        }
        $this->execute('behat_forms::i_set_the_field_in_container_to', [
            get_string('startbackup_bucket', 'tool_vault'),
            get_string('startbackup', 'tool_vault'),
            "dialogue",
            $storage,
        ]);
    }

    /**
     * Locate a table row for the last backup we made
     *
     * @When /^I click on "(?P<element_string>(?:[^"]|\\")*)" "(?P<selector_string>[^"]*)" in the row of my vault backup$/
     * @param string $element Element we look for
     * @param string $selectortype The type of what we look for
     */
    public function i_click_on_in_the_row_of_my_vault_backup($element, $selectortype) {
        $backupname = get_config('tool_vault', 'behat_backup_name');
        $this->execute('behat_general::i_click_on_in_the', [
            $element,
            $selectortype,
            $backupname,
            "table_row",
        ]);
    }
}
