<?php
// This file is part of Moodle - https://moodle.org/
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
 * Uninstall missing plugins action
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class uninstall_missing_plugins extends restore_action {

    /**
     * Executes individual action
     *
     * @param site_restore $logger
     * @param string $stage
     * @return void
     */
    public function execute(site_restore $logger, string $stage) {
        if (!api::get_config('removemissing')) {
            return;
        }

        \core_plugin_manager::reset_caches();
        $pluginman = \core_plugin_manager::instance();
        $plugininfo = $pluginman->get_plugins();

        $plugins = [];
        foreach ($plugininfo as $type => $pluginsoftype) {
            foreach ($pluginsoftype as $name => $plugin) {
                if ($plugin->get_status() === \core_plugin_manager::PLUGIN_STATUS_MISSING) {
                    $plugins[] = $plugin;
                }
            }
        }
        if (!$plugins) {
            return;
        }

        $logger->add_to_log('Uninstall missing plugins ('.count($plugins).')...');
        foreach ($plugins as $plugin) {
            if ($pluginman->can_uninstall_plugin($plugin->component)) {
                $logger->add_to_log('Uninstalling: ' . $plugin->component);

                $progress = new \null_progress_trace();
                $pluginman->uninstall_plugin($plugin->component, $progress);
                $progress->finished();
            } else {
                $logger->add_to_log('Can not be uninstalled: ' . $plugin->component, constants::LOGLEVEL_WARNING);
            }
        }

        $logger->add_to_log('...done');

        (new recalc_version_hash())->execute($logger, $stage);
    }
}
