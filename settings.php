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

/**
 * Plugin administration pages are defined here.
 *
 * @package     tool_vault
 * @category    admin
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_vault\local\helpers\siteinfo;

defined('MOODLE_INTERNAL') || die();

global $CFG;

if ($hassiteconfig) {

    // Main page for the Vault (under Site administration -> Server).
    $ADMIN->add('server', new admin_externalpage('tool_vault_index', new lang_string('pluginname', 'tool_vault'),
        \tool_vault\local\helpers\ui::baseurl(), 'moodle/site:config', get_config('tool_vault', 'clionly')));

    // Vault plugin settings (under Site administration -> Plugins -> Admin tools).
    $settings = new admin_settingpage('tool_vault', new lang_string('settings_header', 'tool_vault'));
    $ADMIN->add('tools', $settings);

    $settings->add(new admin_setting_configcheckbox(
        'tool_vault/clionly',
        get_string('settings_clionly', 'tool_vault'),
        get_string('settings_clionly_desc', 'tool_vault'),
        0
    ));

    $yesnooptions = [
        0 => get_string('no'),
        1 => get_string('yes'),
    ];

    $datarootpaths = [
        'always' => join(', ', siteinfo::common_excluded_dataroot_paths()),
        'examples' => join(', ', siteinfo::skipped_dataroot_path_examples()),
    ];

    // Backup settings.
    $settings->add(new admin_setting_heading(
        'tool_vault/backupsettings',
        get_string('settings_headerbackup', 'tool_vault'),
        ''
    ));

    $settings->add(new admin_setting_configtextarea(
        'tool_vault/backupexcludetables',
        get_string('settings_backupexcludetables', 'tool_vault'),
        get_string('settings_backupexcludetables_desc', 'tool_vault', ['prefix' => $CFG->prefix]),
        '',
        PARAM_RAW,
        60,
        2
    ));

    $settings->add(new admin_setting_configtextarea(
        'tool_vault/backupexcludedataroot',
        get_string('settings_backupexcludedataroot', 'tool_vault'),
        get_string('settings_backupexcludedataroot_desc', 'tool_vault', $datarootpaths),
        'muc, antivirus_quarantine',
        PARAM_RAW,
        60,
        2
    ));

    $settings->add(new admin_setting_configtextarea(
        'tool_vault/backupexcludeplugins',
        get_string('settings_backupexcludeplugins', 'tool_vault'),
        get_string('settings_backupexcludeplugins_desc', 'tool_vault'),
        '',
        PARAM_RAW,
        60,
        2
    ));

    // Restore settings.
    $settings->add(new admin_setting_heading(
        'tool_vault/restoresettings',
        get_string('settings_headerrestore', 'tool_vault'),
        ''
    ));

    $settings->add(new admin_setting_configselect(
        'tool_vault/allowrestore',
        get_string('settings_allowrestore', 'tool_vault'),
        get_string('settings_allowrestore_desc', 'tool_vault'),
        0,
        $yesnooptions
    ));

    $settings->add(new admin_setting_configselect(
        'tool_vault/upgradeafterrestore',
        get_string('setting_upgradeafterrestore', 'tool_vault'),
        get_string('setting_upgradeafterrestore_desc', 'tool_vault'),
        0,
        $yesnooptions
    ));

    $settings->add(new admin_setting_configselect(
        'tool_vault/restoreremovemissing',
        get_string('setting_restoreremovemissing', 'tool_vault'),
        get_string('setting_restoreremovemissing_desc', 'tool_vault'),
        0,
        $yesnooptions
    ));

    $settings->add(new admin_setting_configtextarea(
        'tool_vault/restorepreservedataroot',
        get_string('settings_restorepreservedataroot', 'tool_vault'),
        get_string('settings_restorepreservedataroot_desc', 'tool_vault', $datarootpaths),
        '',
        PARAM_RAW,
        60,
        2
    ));

    $settings->add(new admin_setting_configtextarea(
        'tool_vault/restorepreserveplugins',
        get_string('settings_restorepreserveplugins', 'tool_vault'),
        get_string('settings_restorepreserveplugins_desc', 'tool_vault'),
        '',
        PARAM_RAW,
        60,
        2
    ));
}
