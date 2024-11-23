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

namespace tool_vault\local\restoreactions\upgrade_36\helpers;

use admin_settingpage;
use core_plugin_manager;

/**
 * Class auth_helper
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auth_helper {

    /**
     * Fix how auth plugins are called in the 'config_plugins' table.
     *
     * For legacy reasons, the auth plugins did not always use their frankenstyle
     * component name in the 'plugin' column of the 'config_plugins' table. This is
     * a helper function to correctly migrate the legacy settings into the expected
     * and consistent way.
     *
     * @param string $plugin the auth plugin name such as 'cas', 'manual' or 'mnet'
     */
    public static function upgrade_fix_config_auth_plugin_names($plugin) {
        global $CFG, $DB, $OUTPUT;

        $legacy = (array) get_config('auth/'.$plugin);
        $current = (array) get_config('auth_'.$plugin);

        // I don't want to rely on array_merge() and friends here just in case
        // there was some crazy setting with a numerical name.

        if ($legacy) {
            $new = $legacy;
        } else {
            $new = [];
        }

        if ($current) {
            foreach ($current as $name => $value) {
                if (isset($legacy[$name]) && ($legacy[$name] !== $value)) {
                    // No need to pollute the output during unit tests.
                    if (!empty($CFG->upgraderunning)) {
                        $message = get_string('settingmigrationmismatch', 'core_auth', [
                            'plugin' => 'auth_'.$plugin,
                            'setting' => s($name),
                            'legacy' => s($legacy[$name]),
                            'current' => s($value),
                        ]);
                        echo $OUTPUT->notification($message, \core\output\notification::NOTIFY_ERROR);

                        // upgrade_log(UPGRADE_LOG_NOTICE, 'auth_'.$plugin, 'Setting values mismatch detected',
                        //     'SETTING: '.$name. ' LEGACY: '.$legacy[$name].' CURRENT: '.$value);
                    }
                }

                $new[$name] = $value;
            }
        }

        foreach ($new as $name => $value) {
            set_config($name, $value, 'auth_'.$plugin);
            unset_config($name, 'auth/'.$plugin);
        }
    }

    /**
     * Populate the auth plugin settings with defaults if needed.
     *
     * As a result of fixing the auth plugins config storage, many settings would
     * be falsely reported as new ones by admin/upgradesettings.php. We do not want
     * to confuse admins so we try to reduce the bewilderment by pre-populating the
     * config_plugins table with default values. This should be done only for
     * disabled auth methods. The enabled methods have their settings already
     * stored, so reporting actual new settings for them is valid.
     *
     * @param string $plugin the auth plugin name such as 'cas', 'manual' or 'mnet'
     */
    public static function upgrade_fix_config_auth_plugin_defaults($plugin) {
        global $CFG;

        $pluginman = core_plugin_manager::instance();
        $enabled = $pluginman->get_enabled_plugins('auth');

        if (isset($enabled[$plugin])) {
            // Do not touch settings of enabled auth methods.
            return;
        }

        // We can't directly use {@link core\plugininfo\auth::load_settings()} here
        // because the plugins are not fully upgraded yet. Instead, we emulate what
        // that method does. We fetch a temporary instance of the plugin's settings
        // page to get access to the settings and their defaults. Note we are not
        // adding that temporary instance into the admin tree. Yes, this is a hack.

        $plugininfo = $pluginman->get_plugin_info('auth_'.$plugin);
        $adminroot = admin_get_root();
        $ADMIN = $adminroot;
        $auth = $plugininfo;

        $section = $plugininfo->get_settings_section_name();
        $settingspath = $plugininfo->full_path('settings.php');

        if (file_exists($settingspath)) {
            $settings = new admin_settingpage($section, 'Emulated settings page for auth_'.$plugin, 'moodle/site:config');
            include($settingspath); // TODO not ideal.

            if ($settings) {
                admin_apply_default_settings($settings, false);
            }
        }
    }

}
