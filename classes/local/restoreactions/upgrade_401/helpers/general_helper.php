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

namespace tool_vault\local\restoreactions\upgrade_401\helpers;

/**
 * Class general_helper
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class general_helper {

    /**
     * Schedule ad-hoc task
     *
     * @param string $classname
     * @param bool $checkforexisting
     * @param \stdClass|null $customdata
     */
    public static function queue_adhoc_task($classname, $checkforexisting = false, $customdata = null) {
        \tool_vault\local\restoreactions\upgrade_311\helpers\general_helper::queue_adhoc_task(
            $classname, $checkforexisting, $customdata);
    }

    /**
     * Deletes file records which have their repository deleted.
     *
     */
    public static function upgrade_delete_orphaned_file_records() {
        \tool_vault\local\restoreactions\upgrade_311\helpers\general_helper::upgrade_delete_orphaned_file_records();
    }

    /**
     * Add a new item at the end of the usermenu.
     *
     * @param string $menuitem
     */
    public static function upgrade_add_item_to_usermenu(string $menuitem): void {
        global $CFG;
        // Get current configuration data.
        $currentcustomusermenuitems = str_replace(["\r\n", "\r"], "\n", $CFG->customusermenuitems);
        $lines = preg_split('/\n/', $currentcustomusermenuitems, -1, PREG_SPLIT_NO_EMPTY);
        $lines = array_map('trim', $lines);
        if (!in_array($menuitem, $lines)) {
            // Add the item to the menu.
            $lines[] = $menuitem;
            set_config('customusermenuitems', implode("\n", $lines));
        }
    }
}
