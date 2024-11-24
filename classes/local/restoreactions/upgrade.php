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

use core_component;
use tool_vault\api;
use tool_vault\constants;
use tool_vault\local\checks\version_restore;
use tool_vault\local\restoreactions\upgrade_31\upgrade_31;
use tool_vault\local\restoreactions\upgrade_311\upgrade_311;
use tool_vault\local\restoreactions\upgrade_36\upgrade_36;
use tool_vault\local\restoreactions\upgrade_401\upgrade_401;
use tool_vault\site_restore;

/**
 * Class upgrade_old
 *
 * @package    tool_vault
 * @copyright  2024 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upgrade extends restore_action {

    /**
     * Executes individual action
     *
     * @param site_restore $logger
     * @param string $stage
     * @return void
     */
    public function execute(site_restore $logger, string $stage) {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir.'/upgradelib.php');

        $codeinfo = recalc_version_hash::fetch_core_version();
        $codeversion = $codeinfo['version'];
        $coderelease = $codeinfo['release'];

        if (!api::get_setting_checkbox('upgradeafterrestore')) {
            return;
        }

        $siteupgraded = false;

        // Upgrade to intermediate release.
        $intermediaterelease = version_restore::get_required_core_intermediate_release($CFG->release, $coderelease);

        if ($intermediaterelease && version_compare(normalize_version($CFG->release), '3.1', '<=')) {
            $siteupgraded = true;
            $logger->add_to_log('Upgrading Moodle from '.$CFG->release.' to 3.1...');
            upgrade_31::upgrade($logger);
            $logger->add_to_log('...done');
        }

        if ($intermediaterelease && version_compare(normalize_version($CFG->release), '3.6', '<=')) {
            $siteupgraded = true;
            $logger->add_to_log('Upgrading Moodle from '.$CFG->release.' to 3.6...');
            upgrade_36::upgrade($logger);
            $logger->add_to_log('...done');
        }

        if ($intermediaterelease && version_compare(normalize_version($CFG->release), '3.11.8', '<=')) {
            $siteupgraded = true;
            $logger->add_to_log('Upgrading Moodle from '.$CFG->release.' to 3.11.8...');
            upgrade_311::upgrade($logger);
            $logger->add_to_log('...done');
        }

        if ($intermediaterelease === '4.1.2') {
            $siteupgraded = true;
            $logger->add_to_log('Upgrading Moodle from '.$CFG->release.' to 4.1.2...');
            upgrade_401::upgrade($logger);
            $logger->add_to_log('...done');
        }

        // Upgrade required.
        if ($intermediaterelease || moodle_needs_upgrading()) {
            $siteupgraded = true;
            $logger->add_to_log('Upgrading Moodle from '.$CFG->release.' to '.$coderelease.'...');
            try {
                if ($codeversion > $CFG->version) {
                    upgrade_core($codeversion, true);
                }
                set_config('release', $coderelease);
                set_config('branch', $codeinfo['branch']);
                upgrade_noncore(true);
                $curuser = $USER;
                \core\session\manager::set_user(get_admin());
                admin_apply_default_settings(null, false);
                if ($curuser instanceof \stdClass) {
                    \core\session\manager::set_user($curuser);
                }
            } catch (\Throwable $e) {
                $logger->add_to_log('Error occurred while upgrading: ' . $e->getMessage(),
                    constants::LOGLEVEL_WARNING);
                api::report_error($e);
            }
            set_config('upgraderunning', 0);
            $logger->add_to_log('...done');
        }

        if (!$siteupgraded) {
            $logger->add_to_log('Moodle core and plugins are up to date. No upgrade is required.');
        }
    }

    /**
     * Helper method used by the intermediate upgrades
     *
     * @param \tool_vault\site_restore $logger
     * @param string $dir
     * @param array $versions
     * @param string $funcnameprefix
     * @return void
     */
    public static function upgrade_plugins_to_intermediate_release(
                site_restore $logger, string $dir, array $versions, string $funcnameprefix): void {
        global $DB;
        $allcurversions = $DB->get_records_menu('config_plugins', ['name' => 'version'], '', 'plugin, value');
        foreach ($versions as $plugin => $version) {
            if (empty($allcurversions[$plugin])) {
                // Standard plugin {$plugin} not found. It will be installed during the full upgrade.
                continue;
            }
            if (!core_component::get_component_directory($plugin)) {
                // Plugin code no longer exists, no point upgrading it.
                continue;
            }
            if (file_exists($dir ."/". $plugin .".php")) {
                require_once($dir ."/". $plugin .".php");
                $pluginshort = preg_replace("/^mod_/", "", $plugin);
                $funcname = "tool_vault_{$funcnameprefix}_xmldb_{$pluginshort}_upgrade";
                try {
                    $funcname($allcurversions[$plugin]);
                } catch (\Throwable $t) {
                    $logger->add_to_log("Exception executing upgrade script for plugin {$plugin}: ".
                        $t->getMessage(), constants::LOGLEVEL_WARNING);
                    api::report_error($t);
                }
            }
            set_config('version', $version, $plugin);
        }
    }
}
