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

// phpcs:ignoreFile
// Mdlcode-disable incorrect-package-name.

/**
 * Upgrade code for install
 *
 * @package   tool_usertours
 * @copyright 2016 Ryan Wyllie <ryan@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_usertours\manager;

/**
 * Upgrade the user tours plugin.
 *
 * @param int $oldversion The old version of the user tours plugin
 * @return bool
 */
function tool_vault_404_xmldb_tool_usertours_upgrade($oldversion) {

    if ($oldversion < 2023053000) {
        // Update shipped tours.
        // Normally, we just bump the version numbers because we need to call update_shipped_tours only once.
        manager::update_shipped_tours();

        upgrade_plugin_savepoint(true, 2023053000, 'tool', 'usertours');
    }

    return true;
}
