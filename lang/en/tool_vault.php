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

$string['accesskeyisnotvalid'] = 'Accesskey is not valid';
$string['anotherbackupisinprogress'] = 'Another backup is in progress';
$string['anotherrestoreisinprogress'] = 'Another restore is in progress';
$string['backup'] = 'Backup';
$string['backupdisabledanotherinprogress'] = 'You can not start backup because there is another backup in progress.';
$string['backupdisablednoapikey'] = 'You can not start backup because you do not have API key';
$string['backupfinished'] = '<p>This backup has already finished. You can access the logs <a href="{$a}">here</a></p>';
$string['backupkey'] = 'Backup key';
$string['backupprocesslog'] = 'Backup process log';
$string['backupscheduled'] = 'Backup (scheduled)';
$string['backuptitle'] = 'Backup {$a}';
$string['checkagain'] = 'Check again';
$string['collapselogs'] = 'Collapse logs';
$string['configoverrides'] = 'Config overrides';
$string['databasemodifications'] = 'Database modifications';
$string['diskspace'] = 'Disk space';
$string['enterapikey'] = 'I have an API key';
$string['errorapikeynotvalid'] = 'API key not valid';
$string['expandlogs'] = 'Expand logs';
$string['hidebacktrace'] = 'Hide backtrace';
$string['history'] = 'History';
$string['isencrypted'] = 'Encrypted';
$string['lastop_backupfailed_header'] = 'Backup failed';
$string['lastop_backupfailed_text'] = 'Backup performed on {$a} has failed';
$string['lastop_backupfinished_header'] = 'Backup completed';
$string['lastop_backupfinished_text'] = 'Backup {$a->backupkey} started on {$a->started} and finished on {$a->finished}';
$string['lastop_backupinprogress_header'] = 'Backup in progress';
$string['lastop_backupinprogress_text'] = 'You have a backup in progress';
$string['lastop_backupscheduled_header'] = 'Backup scheduled';
$string['lastop_backupscheduled_text'] = 'You backup is now scheduled and will be executed during the next cron run';
$string['lastop_restorefailed_header'] = 'Restore failed';
$string['lastop_restorefailed_text'] = 'Restore performed on {$a} has failed';
$string['lastop_restorefinished_header'] = 'Restore completed';
$string['lastop_restorefinished_text'] = 'Restore from backup {$a->backupkey} started on {$a->started} and finished on {$a->finished}';
$string['lastop_restoreinprogress_header'] = 'Restore in progress';
$string['lastop_restoreinprogress_text'] = 'You have a restore in progress';
$string['lastop_restoreprecheckfailed_header'] = 'Restore pre-check failed';
$string['lastop_restoreprecheckfailed_text'] = 'Restore pre-check finished at {$a}. Restore will not be possible until all problems are fixed';
$string['lastop_restoreprecheckfinished_header'] = 'Restore pre-check succeeded';
$string['lastop_restoreprecheckfinished_text'] = 'Restore pre-check completed at {$a->finished}. Backup {$a->backupkey} can be restored on this site now';
$string['lastop_restoreprecheckinprogress_header'] = 'Restore pre-check in progress';
$string['lastop_restoreprecheckinprogress_text'] = 'You have a pre-check in progress';
$string['lastop_restoreprecheckscheduled_header'] = 'Restore pre-check scheduled';
$string['lastop_restoreprecheckscheduled_text'] = 'Your restore pre-check is now scheduled and will be executed during the next cron run';
$string['lastop_restorescheduled_header'] = 'Restore scheduled';
$string['lastop_restorescheduled_text'] = 'Your restore is scheduled and will be executed during the next cron run';
$string['lastupdated'] = 'Last updated';
$string['loginexplanation'] = "Create an account on {\$a} to be able to backup or restore the site.
 With the <b>free account</b> you will be able to backup and restore small sites and store them up to 7 days on the server.";
$string['logoutfromvault'] = 'Forget API key';
$string['logrestoredtables'] = 'Restored {$a->cnt}/{$a->totalcnt} tables ({$a->percent}%)';
$string['logsnotavailable'] = 'Not available (backup was performed on a different site)';
$string['managelsmvault'] = 'Manage your account';
$string['messageprovider:statusupdate'] = 'Status update for Vault - Site migration';
$string['nobackupsavailable'] = 'There are no remote backups avaiable for restore';
$string['nopastbackups'] = "You don't have any past backups";
$string['nopastrestores'] = "You don't have any past restores";
$string['operation'] = 'Operation {$a}';
$string['passphrase'] = 'Passphrase';
$string['passphrasewrong'] = 'Backup passphrase is not correct';
$string['pastbackupslist'] = 'Past backups on this site';
$string['pastrestoreslist'] = 'Past restores on this site';
$string['performedby'] = 'Performed by';
$string['pleasewait'] = 'Please wait...';
$string['pluginname'] = 'Vault - Site migration';
$string['pluginsdirectory'] = 'Plugins directory';
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
$string['restorefinished'] = '<p>This restore has already finished. You can access the logs <a href="{$a}">here</a></p>';
$string['restoreinprogress'] = 'During restore the site is placed in maintenance mode. Use this page to access logs about the current process';
$string['restoreremovemissing'] = 'Automatically remove missing plugins after restore';
$string['restoreremovemissing_desc'] = "If backup contains data for the plugins that are not present in this site codebase, they will be marked as \"Missing from disk\" in the plugin overview.<br>
- Enabling this option will automatically uninstall these plugins and remove all data associated with them from the database and file storage<br>
- Disabling this option will leave the data in the database and you will be able to add plugins code after the restore is finished or run the uninstall script manually.";
$string['restoresnotallowed'] = 'Restores are not allowed on this site';
$string['returntothesite'] = 'Return to the site';
$string['seefullreport'] = 'See full report';
$string['settings_allowrestore'] = 'Allow restores on this site';
$string['settings_allowrestore_desc'] = 'Site restore will completely remove all contents of this site and replace with the restored contents. Double check everything before enabling this option';
$string['settings_backupexcludedataroot'] = 'Exclude paths in dataroot';
$string['settings_backupexcludedataroot_desc'] = 'All paths within dataroot folder will be included in the backup except for: <b>filedir</b> (backed up separately), <b>{$a->always}</b>. If you want to exclude more paths list them here. Examples of paths that can be excluded: <b>{$a->examples}</b>';
$string['settings_backupexcludeplugins'] = 'Exclude plugins';
$string['settings_backupexcludeplugins_desc'] = "Only for plugins with server-specific configuration, for example, file storage or session management.<br>
For other plugins include them in backup and uninstall after restore.<br>
Note that this will only exclude data in plugin's own tables, settings, associated files, scheduled tasks and other known common types of plugin-related data. It may not be accurate for complicated plugins or plugins with dependencies.";
$string['settings_backupexcludetables'] = 'Exclude tables';
$string['settings_backupexcludetables_desc'] = "Vault will back up all tables that start with the prefix '{\$a->prefix}' even if they are not listed in xmldb schemas of the core or installed plugins.<br>You can list here the extra tables that should not be backed up an asterisk ('*') to exclude all extra tables.";
$string['settings_header'] = 'Vault - Site migration settings';
$string['settings_headerbackup'] = 'Backup settings';
$string['settings_headerrestore'] = 'Restore settings';
$string['settings_restorepreservedataroot'] = 'Preserve paths in dataroot';
$string['settings_restorepreservedataroot_desc'] = 'During restore all paths within dataroot folder will be removed except for: <b>filedir</b> (restored separately), <b>{$a->always}</b>. If you want to keep more paths list them here. Examples of the paths that can be preserved: <b>{$a->examples}</b>';
$string['settings_restorepreserveplugins'] = 'Preserve plugins';
$string['settings_restorepreserveplugins_desc'] = "Only for plugins with server-specific configuration, for example, file storage or session management. <br>
Restore process will attempt to preserve existing data associated with these plugins and not restore data from the backup if the same plugin is included.<br>
Note that this will only process data in plugin's own tables, settings, associated files, scheduled tasks and other known common types of plugin-related data. It may not be accurate for complicated plugins or plugins with dependencies.";
$string['showbacktrace'] = 'Show backtrace';
$string['sitebackup'] = 'Site backup';
$string['siterestore'] = 'Site restore';
$string['startbackup'] = 'Start backup';
$string['startdryrun'] = 'Run pre-check';
$string['startrestore'] = 'Restore this backup';
$string['statusfailed'] = 'Failed';
$string['statusfailedtostart'] = 'Failed to start';
$string['statusfinished'] = 'Completed';
$string['statusinprogress'] = 'In progress';
$string['statusscheduled'] = 'Scheduled';
$string['subpluginof'] = 'Subplugin of';
$string['timefinished'] = 'Completed on';
$string['timestarted'] = 'Started on';
$string['totalsizearchived'] = 'Total size (archived)';
$string['viewbackupdetails'] = 'View backup details';
$string['viewdetails'] = 'View details';
$string['waitforcron'] = '... Wait for cron to finish to view report ...';
