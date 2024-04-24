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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_vault\local\restoreactions;

use tool_vault\api;
use tool_vault\constants;
use tool_vault\site_restore;

/**
 * Recalculate version hash if no plugins require upgrade
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recalc_version_hash extends restore_action {

    /**
     * Get the core version.
     *
     * @return float core version.
     */
    public static function fetch_core_version() {
        global $CFG;
        $version = null; // Prevent IDE complaints.
        require($CFG->dirroot . '/version.php');
        return (float)$version;
    }

    /**
     * Executes individual action
     *
     * @param site_restore $logger
     * @param string $stage
     * @return void
     */
    public function execute(site_restore $logger, string $stage) {
        global $CFG;
        if (!api::get_setting_checkbox('restoreremovemissing') && $stage == self::STAGE_AFTER_ALL) {
            return;
        }

        $logger->add_to_log('Recalculating all versions hash...');

        $manager = \core_plugin_manager::instance();
        // Purge caches to make sure we have the fresh information about versions.
        $manager::reset_caches();
        $configcache = \cache::make('core', 'config');
        $configcache->purge();
        $needsupgrade = [];
        $versiontoohigh = [];

        // Check that the main version hasn't changed.
        $version = self::fetch_core_version();
        if ((float) $CFG->version > $version) {
            $versiontoohigh[] = 'core';
        } else if ('' . $CFG->version !== '' . $version) {
            $needsupgrade[] = 'core';
        }

        $plugininfo = $manager->get_plugins();
        foreach ($plugininfo as $type => $plugins) {
            foreach ($plugins as $name => $plugin) {
                $frankenstyle = sprintf("%s_%s", $type, $name);
                if ($plugin->get_status() === \core_plugin_manager::PLUGIN_STATUS_DOWNGRADE) {
                    // This should not happen as it should be detected during a pre-check.
                    $versiontoohigh[] = $frankenstyle;
                } else if ($plugin->get_status() !== \core_plugin_manager::PLUGIN_STATUS_UPTODATE) {
                    // This means the plugin is missing or needs upgrade.
                    $needsupgrade[] = $frankenstyle;
                }
            }
        }

        if (!$needsupgrade && !$versiontoohigh) {
            // Update version hash so Moodle doesn't think that we need to run upgrade.
            $manager::reset_caches();
            set_config('allversionshash', \core_component::get_all_versions_hash());
        }

        // Purge relevant caches again.
        $manager::reset_caches();
        $configcache->purge();

        $logger->add_to_log('...done');
    }
}
