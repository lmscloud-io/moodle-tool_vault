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

namespace tool_vault\local\restoreactions;

use tool_vault\api;
use tool_vault\constants;
use tool_vault\local\restoreactions\upgrade_311\upgrade_311;
use tool_vault\local\restoreactions\upgrade_401\upgrade_401;
use tool_vault\local\restoreactions\upgrade_402\upgrade_402;
use tool_vault\local\restoreactions\upgrade_404\upgrade_404;
use tool_vault\site_restore;

/**
 * Base class for intermediate upgrade scripts
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class upgrade_base {
    /**
     * Release version string (e.g. '3.11.8', '4.1.2', '4.2.3')
     *
     * @return string
     */
    abstract public static function get_release(): string;

    /**
     * Core version number for this release
     *
     * @return float
     */
    abstract public static function get_version(): float;

    /**
     * Branch identifier (e.g. '311', '401', '402')
     *
     * @return string
     */
    abstract public static function get_branch(): string;

    /**
     * List of standard plugins and their exact versions for this release
     *
     * @return array
     */
    abstract protected static function plugin_versions(): array;

    /**
     * Returns the directory where the calling subclass file resides
     *
     * @return string
     */
    protected static function get_dir(): string {
        $rc = new \ReflectionClass(static::class);
        return dirname($rc->getFileName());
    }

    /**
     * Returns the ordered list of available upgrade classes
     *
     * @return string[]
     */
    public static function get_upgrade_classes(): array {
        return [
            upgrade_311::class,
            upgrade_401::class,
            upgrade_402::class,
            upgrade_404::class,
        ];
    }

    /**
     * Upgrade the restored site to this intermediate release
     *
     * @param site_restore $logger
     * @return void
     */
    public static function upgrade(site_restore $logger) {
        static::upgrade_core($logger);
        static::upgrade_plugins($logger);
        set_config('upgraderunning', 0);
    }

    /**
     * Upgrade core to this intermediate release
     *
     * @param site_restore $logger
     * @return void
     */
    protected static function upgrade_core(site_restore $logger) {
        global $CFG;
        $dir = static::get_dir();
        $branch = static::get_branch();
        require_once($dir . "/core.php");

        $funcname = "tool_vault_{$branch}_core_upgrade";
        try {
            $funcname($CFG->version);
        } catch (\Throwable $t) {
            $logger->add_to_log("Exception executing core upgrade script: " .
               $t->getMessage(), constants::LOGLEVEL_WARNING);
            api::report_error($t);
        }

        set_config('version', static::get_version());
        set_config('release', static::get_release());
        set_config('branch', $branch);
    }

    /**
     * Upgrade all standard plugins to this intermediate release
     *
     * @param site_restore $logger
     * @return void
     */
    protected static function upgrade_plugins(site_restore $logger) {
        global $DB;
        $dir = static::get_dir();
        $branch = static::get_branch();
        $allcurversions = $DB->get_records_menu('config_plugins', ['name' => 'version'], '', 'plugin, value');
        foreach (static::plugin_versions() as $plugin => $version) {
            if (empty($allcurversions[$plugin])) {
                // Standard plugin {$plugin} not found. It will be installed during the full upgrade.
                continue;
            }
            if (file_exists($dir . "/" . $plugin . ".php") && \core_component::get_component_directory($plugin)) {
                require_once($dir . "/" . $plugin . ".php");
                $pluginshort = preg_replace("/^mod_/", "", $plugin);
                $funcname = "tool_vault_{$branch}_xmldb_{$pluginshort}_upgrade";
                try {
                    $funcname($allcurversions[$plugin]);
                } catch (\Throwable $t) {
                    $logger->add_to_log("Exception executing upgrade script for plugin {$plugin}: " .
                        $t->getMessage(), constants::LOGLEVEL_WARNING);
                    api::report_error($t);
                }
            }
            set_config('version', $version, $plugin);
        }
    }
}
