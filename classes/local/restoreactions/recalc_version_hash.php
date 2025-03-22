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
use tool_vault\local\helpers\log_capture;
use tool_vault\local\logger;
use tool_vault\site_restore;

/**
 * Recalculate version hash if no plugins require upgrade
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recalc_version_hash extends restore_action {

    /** @var string[] */
    protected $alwaysexcluded = [
        // TODO reuse from admin/tool/vault/classes/local/checks/configoverride.php .
        'dbtype', 'dblibrary', 'dbhost', 'dbname', 'dbuser', 'dbpass', 'prefix', 'dboptions',
        'wwwroot', 'dataroot',
        'dirroot', 'libdir',
        'yui2version', 'yui3version', 'yuipatchlevel', 'yuipatchedmodules',
        'phpunit_dataroot', 'phpunit_prefix',
        'behat_wwwroot', 'behat_prefix', 'behat_dataroot', 'behat_dbname', 'behat_dbuser',
        'behat_dbpass', 'behat_dbhost',
    ];

    /** @var string[] */
    protected $otherexcluded = [
        'config_php_settings', 'debugdeveloper', 'forced_plugin_settings', 'ostype', 'httpswwwroot',
        'dbfamily', 'sessiontimeout', 'sessiontimeoutwarning', 'wordlist', 'moddata', 'os',
        'debugdisplay', 'taskruntimewarn', 'taskruntimeerror',
    ];

    /**
     * Get the core version of the code
     *
     * @return array of version and release
     */
    public static function fetch_core_version() {
        global $CFG;
        $version = null; // Prevent IDE complaints.
        require($CFG->dirroot . '/version.php');
        return [
            'version' => (float)$version,
            'release' => (string)$release,
            'branch' => (string)($branch ?? ''),
        ];
    }

    /**
     * Make sure $CFG object corresponds to the values in the config table
     *
     * At this moment, as we just finished the restore, the values in $CFG are cached as they were
     * on the site before the restore. The upgrade script needs them to reflect the DB state.
     *
     * @return void
     */
    protected function reinitialise_cfg() {
        global $CFG, $DB;
        log_capture::reset_debug();
        \cache::make('core', 'config')->purge();
        $preservekeys = array_merge(
            array_keys($CFG->config_php_settings),
            $this->otherexcluded,
            $this->alwaysexcluded);
        $removekeys = array_diff(array_keys((array)$CFG), $preservekeys);
        foreach ($removekeys as $key) {
            unset($CFG->$key);
        }

        $CFG->siteidentifier = $DB->get_field('config', 'value', ['name' => 'siteidentifier']);
        initialise_cfg();
        $CFG->debug = $CFG->debug ?? 0;
        $CFG->debugdeveloper = (($CFG->debug & E_ALL) === E_ALL);
        log_capture::force_debug();
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

        $logger->add_to_log('Rebuilding $CFG...');
        $this->reinitialise_cfg();
        $logger->add_to_log('...done');

        self::recalculate_version_hash($logger);
    }

    /**
     * Recalculate version hash
     *
     * @param logger $logger
     * @return void
     */
    public static function recalculate_version_hash(logger $logger) {
        global $CFG, $DB;

        $logger->add_to_log('Recalculating all versions hash...');

        $manager = \core_plugin_manager::instance();
        $dbversion = $DB->get_field('config', 'value', ['name' => 'version']);
        $needsupgrade = [];
        $versiontoohigh = [];

        // Check that the main version hasn't changed.
        $codeversion = self::fetch_core_version()['version'];
        if ((float)$dbversion > $codeversion) {
            $versiontoohigh[] = 'core';
        } else if ('' . $dbversion !== '' . $codeversion) {
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
        \cache::make('core', 'config')->purge();

        $logger->add_to_log('...done');
    }
}
