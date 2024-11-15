<?php
// This file is part of Moodle - http://moodle.org/
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

// phpcs:ignoreFile
// Mdlcode-disable incorrect-package-name.

/**
 * Upgrade script for tool_moodlenet.
 *
 * @package    tool_moodlenet
 * @copyright  2020 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_vault\local\restoreactions\upgrade_401\helpers\general_helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion
 * @return bool always true
 */
function tool_vault_401_xmldb_tool_moodlenet_upgrade(int $oldversion) {
    global $CFG, $DB;

    // Automatically generated Moodle v3.9.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2022021600) {
        // This is a special case for if MoodleNet integration has never been enabled,
        // or if defaultmoodlenet is not set for whatever reason.
        if (!get_config('tool_moodlenet', 'defaultmoodlenet')) {
            set_config('defaultmoodlenet', 'https://moodle.net', 'tool_moodlenet');
            set_config('defaultmoodlenetname', get_string('defaultmoodlenetnamevalue', 'tool_moodlenet'), 'tool_moodlenet');
        }

        // Enable MoodleNet and set it to display on activity chooser footer.
        // But only do this if we know for sure that the default MoodleNet is a working one.
        if (get_config('tool_moodlenet', 'defaultmoodlenet') == 'https://moodle.net') {
            set_config('enablemoodlenet', '1', 'tool_moodlenet');
            set_config('activitychooseractivefooter', 'tool_moodlenet');

            // Use an adhoc task to send a notification to admin stating MoodleNet is automatically enabled after upgrade.
            general_helper::queue_adhoc_task(tool_moodlenet\task\send_enable_notification::class);
        }

        upgrade_plugin_savepoint(true, 2022021600, 'tool', 'moodlenet');
    }

    // Automatically generated Moodle v4.0.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v4.1.0 release upgrade line.
    // Put any upgrade step following this.

    return true;
}
