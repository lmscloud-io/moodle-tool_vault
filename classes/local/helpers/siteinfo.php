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

namespace tool_vault\local\helpers;

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
        foreach (\core_component::get_plugin_types() as $type => $unused1) {
            $standard = \core_plugin_manager::standard_plugins_list($type);
            foreach (\core_component::get_plugin_list($type) as $plug => $dir) {
                $pluginname = $type.'_'.$plug;
                $isaddon = in_array($plug, $standard) ? null : true;
                $list[$pluginname] = array_filter([
                    'version' => get_config($pluginname, 'version'),
                    'isaddon' => $isaddon,
                    'parent' => \core_component::get_subtype_parent($type),
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
}
