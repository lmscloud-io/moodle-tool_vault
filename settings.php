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
require_once("{$CFG->dirroot}/{$CFG->admin}/tool/vault/autoloader.php");

if ($hassiteconfig) {

    // Main page for the Vault (under Site administration -> Server).
    $ADMIN->add('server', new admin_externalpage('tool_vault_index', get_string('pluginname', 'tool_vault'),
        \tool_vault\local\helpers\ui::baseurl(), 'moodle/site:config', get_config('tool_vault', 'clionly')));

    // Vault plugin settings (under Site administration -> Plugins -> Admin tools).
    $settings = new admin_settingpage('tool_vault', get_string('settings_header', 'tool_vault'));
    $ADMIN->add('tools', $settings);

    $settings->add(new admin_setting_configcheckbox(
        'tool_vault/clionly',
        get_string('settings_clionly', 'tool_vault'),
        get_string('settings_clionly_desc', 'tool_vault'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'tool_vault/forcedebug',
        get_string('settings_forcedebug', 'tool_vault'),
        get_string('settings_forcedebug_desc', 'tool_vault'),
        1
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

    $settings->add(new admin_setting_configselect(
        'tool_vault/backupcompressionlevel',
        get_string('settings_backupcompressionlevel', 'tool_vault'),
        get_string('settings_backupcompressionlevel_desc', 'tool_vault',
            join(', ', \tool_vault\constants::COMPRESSED_FILE_EXTENSIONS)),
        9,
        array_combine(range(0, 9), range(0, 9))
    ));
}
