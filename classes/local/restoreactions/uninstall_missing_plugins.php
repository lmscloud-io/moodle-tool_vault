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
        global $CFG;
        if (!api::get_setting_checkbox('restoreremovemissing')) {
            return;
        }
        \core_plugin_manager::reset_caches();
        self::remove_missing_plugins($logger);
    }

    /**
     * Uinstall missing plugins if there are any and if no upgrade is pending.
     *
     * @param \tool_vault\local\logger $logger
     * @return void
     */
    public static function remove_missing_plugins(\tool_vault\local\logger $logger) {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php'); // Core core_plugin_manager does not include it!

        $needsupgrade = recalc_version_hash::fetch_core_version()['version'] != (float)$CFG->version;
        $pluginman = \core_plugin_manager::instance();
        /** @var \core\plugininfo\base[][] $plugininfo */
        $plugininfo = $pluginman->get_plugins();

        $missingplugins = [];
        foreach ($plugininfo as $type => $pluginsoftype) {
            foreach ($pluginsoftype as $name => $plugin) {
                $status = $plugin->get_status();
                if ($status === \core_plugin_manager::PLUGIN_STATUS_MISSING) {
                    $missingplugins[] = "{$type}_{$name}";
                } else if ($status !== \core_plugin_manager::PLUGIN_STATUS_UPTODATE) {
                    $needsupgrade = true;
                }
            }
        }
        if (!$missingplugins) {
            $logger->add_to_log('There are no missing plugins. Nothing to do.');
            return;
        }

        if ($needsupgrade) {
            $logger->add_to_log('Cannot uninstall missing plugins ('.count($missingplugins).') because a Moodle upgrade is pending',
                constants::LOGLEVEL_WARNING);
            return;
        }

        $logger->add_to_log('Uninstall missing plugins ('.count($missingplugins).')...');
        foreach ($missingplugins as $pluginname) {
            if ($pluginman->can_uninstall_plugin($pluginname)) {
                $logger->add_to_log('Uninstalling: ' . $pluginname);

                $progress = new \null_progress_trace();
                try {
                    $pluginman->uninstall_plugin($pluginname, $progress);
                } catch (\Throwable $e) {
                    $logger->add_to_log('Error occurred while trying to uninstall plugin '. $pluginname .
                        ', some plugin data may still be present in the database: ' . $e->getMessage(),
                        constants::LOGLEVEL_WARNING);
                    api::report_error($e);
                }
                $progress->finished();
            } else {
                $logger->add_to_log('Can not be uninstalled: ' . $pluginname, constants::LOGLEVEL_WARNING);
            }
        }

        $logger->add_to_log('...done');

        recalc_version_hash::recalculate_version_hash($logger);
    }
}
