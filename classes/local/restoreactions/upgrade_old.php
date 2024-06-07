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
use tool_vault\local\checks\version_restore;
use tool_vault\site_restore;

/**
 * Class upgrade_old
 *
 * @package    tool_vault
 * @copyright  2024 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upgrade_old extends restore_action {

    /**
     * Executes individual action
     *
     * @param site_restore $logger
     * @param string $stage
     * @return void
     */
    public function execute(site_restore $logger, string $stage) {
        global $CFG, $DB;
        $oldrelease = $DB->get_field('config', 'value', ['name' => 'release']);
        $codeinfo = recalc_version_hash::fetch_core_version();
        $codeversion = $codeinfo['version'];
        $coderelease = $codeinfo['release'];

        $intermediaterelease = version_restore::get_required_core_intermediary_release($oldrelease, $coderelease);
        if ($intermediaterelease === null) {
            // Direct upgrade is possible or no upgrade is needed at all.
            return;
        }

        $logger->add_to_log('Upgrading Moodle core from '.$oldrelease.' to '.$intermediaterelease.'...');
        $logger->add_to_log('Checking values(1): version = '.$CFG->version.', release = '.$CFG->release);

        // At this moment, as we just finished the restore, the values in $CFG are cached as they were
        // on the site before the restore. The upgrade script needs them to reflect the DB state.
        $CFG->siteidentifier = $DB->get_field('config', 'value', ['name' => 'siteidentifier']);
        \cache::make('core', 'config')->purge();
        initialise_cfg();

        $logger->add_to_log('Checking values(2): version = '.$CFG->version.', release = '.$CFG->release);

        // TODO the following code assumes that $intermediaterelease == '3.11.8' since others are not needed yet.

        require_once($CFG->dirroot.'/admin/tool/vault/special/upgrade.php');

        tool_vault_core_upgrade($CFG->version);

        set_config('upgraderunning', 0);
        set_config('version', 2021051708.00);
        set_config('release', '3.11.8');

        $logger->add_to_log('Checking values(3): version = '.$CFG->version.', release = '.$CFG->release);
        $logger->add_to_log('...done');
    }
}
