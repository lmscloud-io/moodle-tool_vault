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

/**
 * Compatibility methods for functions that are not available in all Moodle versions
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class compat {

    /**
     * Function make_backup_temp_directory that was not available in 3.4
     *
     * @param mixed $directory
     * @param mixed $exceptiononerror
     * @return string|false
     */
    public static function make_backup_temp_directory($directory, $exceptiononerror = true) {
        global $CFG;
        if (function_exists('make_backup_temp_directory')) {
            return make_backup_temp_directory($directory, $exceptiononerror);
        }

        $backupdir = defined('PHPUNIT_BACKUPTEMPDIR') ? PHPUNIT_BACKUPTEMPDIR :
            (isset($CFG->backuptempdir) ? $CFG->backuptempdir : null);
        if (!empty($backupdir) && $backupdir !== "$CFG->tempdir/backup") {
            check_dir_exists($backupdir, true, true);
            protect_directory($backupdir);
            return make_writable_directory("$backupdir/$directory", $exceptiononerror);
        } else {
            protect_directory($CFG->tempdir);
            return make_writable_directory("$CFG->tempdir/backup/$directory", $exceptiononerror);
        }
    }

    public static function get_plugin_types() {
        if (class_exists('core_component')) {
            return core_component::get_plugin_types();
        } else {
            return get_plugin_types();
        }
    }

    public static function get_plugin_list($ptype) {
        if (class_exists('core_component')) {
            return core_component::get_plugin_list();
        } else {
            return get_plugin_list($ptype);
        }
    }

    public static function get_plugin_directory($type, $name) {
        if (class_exists('core_component')) {
            return core_component::get_plugin_directory($type, $name);
        } else {
            return get_plugin_directory($type, $name);
        }
    }

    public static function get_component_directory($plugin) {
        if (class_exists('core_component')) {
            return core_component::get_component_directory($plugin);
        } else {
            return get_component_directory($plugin);
        }
    }

    public static function normalize_component($component) {
        if (class_exists('core_component')) {
            return core_component::normalize_component($component);
        } else {
            return normalize_component($component);
        }
    }

    public static function get_plugin_types_with_subplugins() {
        if (class_exists('core_component')) {
            return core_component::get_plugin_types_with_subplugins();
        } else {
            return array_intersect_key(self::get_plugin_types(), ['mod' => 1, 'editor' => 1]);
        }
    }

    public static function get_subtype_parent($type) {
        global $CFG;
        if (class_exists('core_component')) {
            return core_component::get_subtype_parent($type);
        } else {
            require_once($CFG->libdir.'/pluginlib.php');
            $pluginman = \plugin_manager::instance();
            return $pluginman->get_parent_of_subplugin($type);
        }
    }

    public static function standard_plugins_list($type) {
        global $CFG;
        if (class_exists('core_plugin_manager')) {
            return \core_plugin_manager::standard_plugins_list($type);
        } else {
            require_once($CFG->libdir.'/pluginlib.php');
            $pluginman = \plugin_manager::instance();
            $plugins = $pluginman->get_plugins()[$type];
            $standard = [];
            foreach ($plugins as $pluginname => $plugininfo) {
                if ($plugininfo->is_standard()) {
                    $standard[] = $pluginname;
                }
            }
            return $standard;
        }
    }

    /**
     * Get info about plugin
     *
     * @param string $plugin
     * @return mixed object with properties: displayname, dependencies, pluginsupported?, pluginincompatible?
     */
    public static function get_plugin_info($plugin) {
        global $CFG;
        if (class_exists('core_plugin_manager')) {
            $pluginman = \core_plugin_manager::instance();
            $plugin = $pluginman->get_plugin_info($plugin);
            $plugin->load_disk_version();
            return $plugin;
        } else {
            require_once($CFG->libdir.'/pluginlib.php');
            $pluginman = \plugin_manager::instance();
            return $pluginman->get_plugin_info($plugin);
        }
    }
}
