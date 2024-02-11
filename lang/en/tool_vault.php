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
 * Plugin strings are defined here.
 *
 * @package     tool_vault
 * @category    string
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addapikey'] = 'Add API key';
$string['allowrestore'] = 'Allow restores on this site';
$string['allowrestore_desc'] = 'Site restore will completely remove all contents of this site and replace with the restored contents. Double check everything before enabling this option';
$string['backup'] = 'Backup';
$string['backupdisabledanotherinprogress'] = 'You can not start backup because there is another backup in progress.';
$string['backupdisablednoapikey'] = 'You can not start backup because you do not have API key';
$string['backupexcludedataroot'] = 'Exclude paths in dataroot';
$string['backupexcludedataroot_desc'] = 'All paths within dataroot folder will be included in the backup except for: <b>filedir</b> (backed up separately), <b>{$a->always}</b>. If you want to exclude more paths list them here. Examples of paths that can be excluded: <b>{$a->examples}</b>';
$string['backupexcludeplugins'] = 'Exclude plugins';
$string['backupexcludeplugins_desc'] = "Only for plugins with server-specific configuration, for example, file storage or session management.<br>
For other plugins include them in backup and uninstall after restore.<br>
Note that this will only exclude data in plugin's own tables, settings, associated files, scheduled tasks and other known common types of plugin-related data. It may not be accurate for complicated plugins or plugins with dependencies.";
$string['backupexcludetables'] = 'Exclude tables';
$string['backupexcludetables_desc'] = "Vault will back up all tables that start with the prefix '{\$a->prefix}' even if they are not listed in xmldb schemas of the core or installed plugins.<br>You can list here the extra tables that should not be backed up an asterisk ('*') to exclude all extra tables.";
$string['backupkey'] = 'Backup key';
$string['backupprocesslog'] = 'Backup process log';
$string['backupsettingsheader'] = 'Backup settings';
$string['backuptitle'] = 'Backup {$a}';
$string['checkagain'] = 'Check again';
$string['clihelp'] = 'Print out this help';
$string['climissingargument'] = 'Argument --{$a} is required';
$string['clititlebackup'] = 'Command line site backup';
$string['clititlelist'] = 'Command line remote backup list';
$string['clititlerestore'] = 'Command line site restore';
$string['collapselogs'] = 'Collapse logs';
$string['databasemodifications'] = 'Database modifications';
$string['diskspace'] = 'Disk space';
$string['enterapikey'] = 'I have an API key';
$string['errorapikeynotvalid'] = 'API key not valid';
$string['errorrestorenotallowed'] = 'Restores are not allowed on this site.';
$string['expandlogs'] = 'Expand logs';
$string['failed'] = 'Failed';
$string['hidebacktrace'] = 'Hide backtrace';
$string['history'] = 'History';
$string['isencrypted'] = 'Encrypted';
$string['lastupdated'] = 'Last updated';
$string['logoutfromvault'] = 'Forget API key';
$string['logrestoredtables'] = 'Restored {$a->cnt}/{$a->totalcnt} tables ({$a->percent}%)';
$string['logsnotavailable'] = 'Not available (backup was performed on a different site)';
$string['managelsmvault'] = 'Manage your account';
$string['messageprovider:statusupdate'] = 'Status update for Vault - Site migration';
$string['nobackupsavailable'] = 'There are no remote backups avaiable for restore';
$string['nopastbackups'] = "You don't have any past backups";
$string['nopastrestores'] = "You don't have any past restores";
$string['passphrase'] = 'Passphrase';
$string['passphrasewrong'] = 'Backup passphrase is not correct';
$string['pastbackupslist'] = 'Past backups on this site';
$string['pastrestoreslist'] = 'Past restores on this site';
$string['performedby'] = 'Performed by';
$string['pleasewait'] = 'Please wait...';
$string['pluginname'] = 'Name';
$string['pluginsdirectory'] = 'Plugins directory';
$string['pluginsettings'] = 'Vault - Site migration settings';
$string['pluginversioninbackup'] = 'Version in the backup';
$string['pluginversiononsite'] = 'Current version on this site';
$string['pluginversions'] = 'Plugin versions';
$string['privacy:metadata'] = 'The Vault plugin doesn\'t store any personal data.';
$string['remotebackup'] = 'Remote backup';
$string['remotebackupdetails'] = 'Remote backup details';
$string['remotebackups'] = 'Remote backups';
$string['remotesignin'] = 'Sign In';
$string['remotesignup'] = 'Create account';
$string['repeatprecheck'] = 'Repeat pre-check';
$string['restoreinprogress'] = 'During restore the site is placed in maintenance mode. Use this page to access logs about the current process';
$string['restorepreservedataroot'] = 'Preserve paths in dataroot';
$string['restorepreservedataroot_desc'] = 'During restore all paths within dataroot folder will be removed except for: <b>filedir</b> (restored separately), <b>{$a->always}</b>. If you want to keep more paths list them here. Examples of the paths that can be preserved: <b>{$a->examples}</b>';
$string['restorepreserveplugins'] = 'Preserve plugins';
$string['restorepreserveplugins_desc'] = "Only for plugins with server-specific configuration, for example, file storage or session management. <br>
Restore process will attempt to preserve existing data associated with these plugins and not restore data from the backup if the same plugin is included.<br>
Note that this will only process data in plugin's own tables, settings, associated files, scheduled tasks and other known common types of plugin-related data. It may not be accurate for complicated plugins or plugins with dependencies.";
$string['restoreremovemissing'] = 'Automatically remove missing plugins after restore';
$string['restoreremovemissing_desc'] = "If backup contains data for the plugins that are not present in this site codebase, they will be marked as \"Missing from disk\" in the plugin overview.<br>
- Enabling this option will automatically uninstall these plugins and remove all data associated with them from the database and file storage<br>
- Disabling this option will leave the data in the database and you will be able to add plugins code after the restore is finished or run the uninstall script manually.";
$string['restoresettingsheader'] = 'Restore settings';
$string['restoresnotallowed'] = 'Restores are not allowed on this site';
$string['returntothesite'] = 'Return to the site';
$string['seefullreport'] = 'See full report';
$string['showbacktrace'] = 'Show backtrace';
$string['sitebackup'] = 'Site backup';
$string['siterestore'] = 'Site restore';
$string['startbackup'] = 'Start backup';
$string['startdryrun'] = 'Run pre-check';
$string['startrestore'] = 'Restore this backup';
$string['subpluginof'] = 'Subplugin of';
$string['timefinished'] = 'Finished on';
$string['timestarted'] = 'Started on';
$string['totalsizearchived'] = 'Total size (archived)';
$string['viewbackupdetails'] = 'View backup details';
$string['viewdetails'] = 'View details';
$string['waitforcron'] = '... Wait for cron to finish to view report ...';
