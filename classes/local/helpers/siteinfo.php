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

namespace tool_vault\local\helpers;

use core_component;
use tool_vault\api;
use tool_vault\local\xmldb\dbtable;

/**
 * Helper class for site information
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class siteinfo {
    /**
     * Get list of installed plugins
     *
     * @param bool $withnames
     * @return array
     */
    public static function get_plugins_list_full(bool $withnames = false): array {
        $list = [];
        foreach (core_component::get_plugin_types() as $type => $unused1) {
            $standard = \core_plugin_manager::standard_plugins_list($type) ?: [];
            foreach (core_component::get_plugin_list($type) as $plug => $dir) {
                $pluginname = $type.'_'.$plug;
                $isaddon = in_array($plug, $standard) ? null : true;
                $list[$pluginname] = array_filter([
                    'version' => get_config($pluginname, 'version'),
                    'isaddon' => $isaddon,
                    'parent' => core_component::get_subtype_parent($type),
                    'name' => ($withnames || $isaddon) ? self::get_plugin_name($pluginname) : null,
                ]);
            }
        }
        return $list;
    }

    /**
     * Get human readable name of a plugin
     *
     * @param string $plugin
     * @return string
     */
    protected static function get_plugin_name(string $plugin) {
        $pluginman = \core_plugin_manager::instance();
        $inf = $pluginman->get_plugin_info($plugin);
        return $inf->displayname;
    }

    /**
     * Plugin types that can not be excluded from backup/restore
     *
     * @return string[]
     */
    public static function unsupported_plugin_types_to_exclude() {
        // Plugins overriding \core\plugininfo\base::override uninstall_cleanup() .
        return [
            // These types only unregister 'enabled' - maybe they can be supported?
            'logstore', 'antivirus',
            // These types are too difficult to support during backup/restore.
            'auth', 'block', 'contenttype', 'customfield', 'enrol',
            'filter', 'format', 'gradingform', 'message', 'mod', 'portfolio',
            'qbehavior', 'qtype', 'repository', 'theme',
        ];
    }

    /**
     * Plugin has xmldb_pluginname_uninstall function
     *
     * @param string $plugin
     * @return bool
     */
    public static function plugin_has_xmldb_uninstall_function(string $plugin): bool {
        // Custom plugin uninstall.
        if ($plugindirectory = core_component::get_component_directory($plugin)) {
            $uninstalllib = $plugindirectory . '/db/uninstall.php';
            if (file_exists($uninstalllib)) {
                require_once($uninstalllib);
                $uninstallfunction = 'xmldb_' . $plugin . '_uninstall';
                // Mdlcode-disable-next-line cannot-parse-callback.
                if (function_exists($uninstallfunction)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Plugin has subplugins
     *
     * @param string $plugin
     * @return bool
     */
    public static function plugin_has_subplugins(string $plugin): bool {
        [$type, $name] = core_component::normalize_component($plugin);
        $subplugintypes = core_component::get_plugin_types_with_subplugins();
        if (isset($subplugintypes[$type])) {
            $base = core_component::get_plugin_directory($type, $name);

            $subpluginsfile = "{$base}/db/subplugins.json";
            if (file_exists($subpluginsfile)) {
                $subplugins = (array) json_decode(file_get_contents($subpluginsfile))->plugintypes;
            } else if (file_exists("{$base}/db/subplugins.php")) {
                $subplugins = [];
                include("{$base}/db/subplugins.php");
            }

            if (!empty($subplugins)) {
                foreach (array_keys($subplugins) as $subplugintype) {
                    $instances = core_component::get_plugin_list($subplugintype);
                    if ($instances) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Common excluded dataroot paths
     *
     * @return string[]
     */
    public static function common_excluded_dataroot_paths(): array {
        return [
            'cache',
            'localcache',
            'temp',
            'sessions',
            'trashdir',
            'lock',
        ];
    }

    /**
     * Should this dataroot path be skipped
     *
     * @param string $path
     * @return bool
     */
    protected static function is_dataroot_path_skipped_always(string $path): bool {
        return in_array($path, self::common_excluded_dataroot_paths()) ||
            in_array($path, [
                'filedir', // Files are retrieved separately.
                // For phpunit.
                'phpunit',
                'phpunittestdir.txt',
                'originaldatafiles.json',
                // Vault temp dir.
                '__vault_restore__',
            ]) || preg_match('/^\\./', $path);
    }

    /**
     * Should the dataroot subfolder/file be skipped from the backup
     *
     * @param string $path relative path under $CFG->dataroot
     * @return bool
     */
    public static function is_dataroot_path_skipped_backup(string $path): bool {
        if (self::is_dataroot_path_skipped_always($path)) {
            return true;
        }
        $paths = api::get_setting_array('backupexcludedataroot');
        return in_array($path, $paths);
    }

    /**
     * Should the dataroot subfolder/file be skipped during restore
     *
     * @param string $path relative path under $CFG->dataroot
     * @return bool
     */
    public static function is_dataroot_path_skipped_restore(string $path): bool {
        if (self::is_dataroot_path_skipped_always($path)) {
            return true;
        }
        $paths = api::get_setting_array('restorepreservedataroot');
        return in_array($path, $paths);
    }

    /**
     * Examples of the dataroot path (for the admin settings)
     *
     * @return string[]
     */
    public static function skipped_dataroot_path_examples(): array {
        global $CFG;
        $result = [];
        $handle = opendir($CFG->dataroot);
        while (($file = readdir($handle)) !== false) {
            if (self::is_dataroot_path_skipped_always($file)) {
                continue;
            }
            $result[] = $file;
        }
        closedir($handle);
        $examples = ['lang', 'muc', 'antivirus_quarantine'];
        return array_merge($examples, array_diff($result, $examples));
    }

    /**
     * List of plugins that should be excluded during backup
     *
     * @return array
     */
    public static function get_excluded_plugins_backup(): array {
        $plugins = api::get_setting_array('backupexcludeplugins');
        $plugins[] = 'tool_vault';
        return array_unique($plugins);
    }

    /**
     * List of plugins that should be excluded during restore
     * @return array
     */
    public static function get_excluded_plugins_restore(): array {
        $plugins = api::get_setting_array('restorepreserveplugins');
        $plugins[] = 'tool_vault';
        return array_unique($plugins);
    }

    /**
     * Is given table excluded from backup
     *
     * @param string $tablename
     * @param dbtable|null $deftable
     * @return bool
     */
    public static function is_table_excluded_from_backup(string $tablename, ?dbtable $deftable): bool {
        global $CFG;
        if (!$deftable) {
            // This is a table that is not present in the install.xml files of core or any plugins.
            // Exclude this table if it's name is in the 'backupexcludetables' setting.
            $tables = api::get_setting_array('backupexcludetables');
            if (in_array($CFG->prefix . $tablename, $tables)) {
                return true;
            }
        } else {
            // This table has a definition. Check if it belongs to an excluded plugin.
            if (in_array($deftable->get_component(), self::get_excluded_plugins_backup())) {
                return true;
            }
        }
        return false;

    }

    /**
     * Is given table excluded from restore
     *
     * @param string $tablename
     * @param dbtable|null $deftable
     * @return bool
     */
    public static function is_table_preserved_in_restore(string $tablename, ?dbtable $deftable): bool {
        global $CFG;
        if (!$deftable) {
            // This is a table that is not present in the install.xml files of core or any plugins.
            // Exclude this table if it's name is in the 'backupexcludetables' setting.
            $tables = api::get_setting_array('restorepreservetables');
            // TODO this setting does not exist!
            if (in_array($CFG->prefix . $tablename, $tables)) {
                return true;
            }
        } else {
            // This table has a definition. Check if it belongs to an excluded plugin.
            if (in_array($deftable->get_component(), self::get_excluded_plugins_restore())) {
                return true;
            }
        }
        return false;
    }
}
