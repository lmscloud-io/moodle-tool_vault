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

namespace tool_vault\local\tools;

use tool_vault\constants;
use tool_vault\local\models\tool_model;

/**
 * Class uninstall_missing_plugins
 *
 * @package    tool_vault
 * @copyright  2024 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class uninstall_missing_plugins extends tool_base {

    /**
     * Execute operation
     * @return void
     */
    public function perform() {
        \tool_vault\local\restoreactions\uninstall_missing_plugins::remove_missing_plugins($this);
    }

    /**
     * Display name of this check
     *
     * @return string
     */
    public static function get_display_name(): string {
        return get_string('tools_uninstallmissingplugins', 'tool_vault');
    }

    /**
     * Tool description (for the listing page)
     *
     * @return string
     */
    public static function get_description(): string {
        return get_string('tools_uninstallmissingplugins_desc', 'tool_vault');
    }
}
