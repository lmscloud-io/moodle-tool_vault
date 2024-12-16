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

namespace tool_vault\local\restoreactions\upgrade_22;

use tool_vault\api;
use tool_vault\constants;
use tool_vault\site_restore;

/**
 * Upgrade core and standard plugins to 2.2
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upgrade_22 {
    /**
     * Upgrade the restored site to 2.2
     *
     * @param site_restore $logger
     * @return void
     */
    public static function upgrade(site_restore $logger) {
        self::upgrade_core($logger);
        \tool_vault\local\restoreactions\upgrade::upgrade_plugins_to_intermediate_release(
            $logger, __DIR__, self::plugin_versions(), '22');
        set_config('upgraderunning', 0);
    }

    /**
     * Upgrade core to 2.2
     *
     * @param site_restore $logger
     * @return void
     */
    protected static function upgrade_core(site_restore $logger) {
        global $CFG;
        require_once(__DIR__ ."/core.php");

        try {
            tool_vault_22_core_upgrade($CFG->version);
        } catch (\Throwable $t) {
            $logger->add_to_log("Exception executing core upgrade script: ".
               $t->getMessage(), constants::LOGLEVEL_WARNING);
            api::report_error($t);
        }

        set_config('version', 2011120511.00);
        set_config('release', '2.2.11');
        set_config('branch', '22');
    }

    /**
     * List of standard plugins in 2.2 and their exact versions
     *
     * @return array
     */
    protected static function plugin_versions() {
        return [

        ];
    }
}