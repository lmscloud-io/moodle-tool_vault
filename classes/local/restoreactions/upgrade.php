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

        // Upgrade required.
        if (moodle_needs_upgrading()) {
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
}
