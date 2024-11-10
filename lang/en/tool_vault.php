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

$string['addonplugins_connectionerror'] = 'Error connecting to moodle.org.';
$string['apikey'] = 'API key';
$string['backup'] = 'Backup';
$string['backupdescription'] = 'Backup description';
$string['backupdetails'] = 'Backup details';
$string['backupfinished'] = 'This backup has already finished. You can access the logs <a href="{$a}">here</a>';
$string['backupinprogres'] = 'During backup the site is placed in maintenance mode. Use this page to access logs about the current process';
$string['backupkey'] = 'Backup key';
$string['backuponlyversion'] = 'Restore is not available. You can back up this site and restore it into a later Moodle version. Please see <a href="https://moodle.org/plugins/tool_vault">tool_vault information page</a>.';
$string['backupprocesslog'] = 'Backup process log';
$string['backupscheduled'] = 'Backup (scheduled)';
$string['backuptitle'] = 'Backup {$a}';
$string['checkagain'] = 'Check again';
$string['cliprocess'] = 'CLI process';
$string['collapselogs'] = 'Collapse logs';
$string['configoverrides'] = 'Config overrides';
$string['configoverrides_settingname'] = 'Setting name';
$string['configoverrides_settingvalue'] = 'Setting value';
$string['configoverrides_valueredacted'] = 'Redacted';
$string['configoverrides_willbeincluded'] = 'Settings from config.php that will be included in backup';
$string['configoverrides_willbeincludedplugin'] = 'Plugin settings from config.php that will be included in backup';
$string['configoverrides_willnotbeincluded'] = 'Settings from config.php that will NOT be included in backup';
$string['configoverrides_willnotbeincludedplugin'] = 'Plugin settings from config.php that will NOT be included in backup';
$string['dbmodifications'] = 'Database modifications';
$string['dbmodifications_changedtables'] = 'Changed tables';
$string['dbmodifications_extratable_warning'] = 'Extra table: Table is absent in the definition but is present in the actual database.';
$string['dbmodifications_extratables'] = 'Extra tables';
$string['dbmodifications_invalidtables'] = 'Invalid tables';
$string['dbmodifications_missingtable_warning'] = 'Missing table: Table is present in the definition but is absent in the actual database';
$string['dbmodifications_missingtables'] = 'Missing tables';
$string['dbmodifications_status_clean'] = 'Your database tables match descriptions in install.xml';
$string['dbmodifications_status_invalid'] = "Your database has modifications that can not be processed by Moodle. You need to adjust the 'Backup settings' and exclude some entities if you want to perform a site backup";
$string['dbmodifications_status_modified'] = "Your database state does not match the specifications in install.xml. The site can be backed up, the modifications will be included in the backup";
$string['dbmodifications_status_nomodifications'] = 'Site backup can be performed without any database modifications';
$string['defaultbackupdescription'] = '{$a->site} by {$a->name}';
$string['diskspacebackup'] = 'Disk space';
$string['diskspacebackup_codesize'] = 'Total size of add-on plugins code ({$a} plugins)';
$string['diskspacebackup_countfiles'] = 'Number of files in file storage';
$string['diskspacebackup_datarootsize'] = 'Required space for dataroot (excluding filedir)';
$string['diskspacebackup_dbmaxsize'] = 'The largest DB table size (approx)';
$string['diskspacebackup_dbrecords'] = 'Total number of rows in DB tables';
$string['diskspacebackup_dbtotalsize'] = 'Required space for database';
$string['diskspacebackup_fail'] = 'There is not enough disk space to perform the site backup';
$string['diskspacebackup_fail_datarootunreadable'] = 'Can not read path(s) <b>{$a->paths}</b> in the dataroot. Either fix file permissions on the server or exclude these paths from the backup in the vault <a href="{$a->settingsurl}">settings</a>';
$string['diskspacebackup_freespace'] = 'Free space in temp dir';
$string['diskspacebackup_maxdatarootfilesize'] = 'The largest file in dataroot';
$string['diskspacebackup_maxfilesize'] = 'The largest file in file storage';
$string['diskspacebackup_success'] = 'There is enough disk space to perform site backup';
$string['diskspacebackup_totalfilesize'] = 'Total size of files in file storage';
$string['enterapikey'] = 'I have an API key';
$string['environ_fail'] = 'Tool vault was not able to raise max_execution_time setting. Please refer to <a href="{$a}" target="_blank">{$a}</a>';
$string['environ_success_warning'] = 'Tool vault was not able to raise max_execution_time setting. If the process does not finish within {$a->value}, it will be aborted. Please refer to <a href="{$a->url}" target="_blank">{$a->url}</a>';
$string['environbackup'] = 'Environment check';
$string['environbackup_maxexecutiontime'] = 'Maximum execution time (max_execution_time)';
$string['error_accesskeyisnotvalid'] = 'Accesskey is not valid';
$string['error_anotherbackupisinprogress'] = 'Another backup is in progress';
$string['error_apikeycharacters'] = 'API key may only contain latin letters and digits';
$string['error_apikeynotvalid'] = 'API key not valid';
$string['error_apikeytoolong'] = 'API key is too long';
$string['error_apikeytooshort'] = 'API key is too short';
$string['error_backupfailed'] = 'Backup with the key {$a} has failed';
$string['error_backuphaswrongstatus'] = 'Backup with the key {$a} has a wrong status';
$string['error_backupinprogressnotfound'] = 'Backup in progress not found';
$string['error_backupnotavailable'] = 'Backup with the key {$a} is no longer avaialable';
$string['error_backupnotfinished'] = 'Backup with the key {$a} is not yet completed';
$string['error_backupprecheckfailed'] = 'Error occurred while executing backup pre-check \'{$a->name}\': {$a->message}';
$string['error_cannotcreatezip'] = 'Can not create ZIP file';
$string['error_failedmultipartupload'] = 'Failed to start multipart upload. UploadId not found in the command output: {$a}';
$string['error_invaliduploadlink'] = 'Vault API did not return a valid upload link {$a}';
$string['error_notavalidlink'] = 'Vault API did not return a valid link: {$a}';
$string['error_passphrasewrong'] = 'Error accessing passphrase protected backup. Verify the passphrase and try again.';
$string['error_serverreturnednodata'] = 'Server returned no data';
$string['error_shutdown'] = 'Shutdown detected. {$a} timed out or was interrupted by the user.';
$string['error_unabletorunprecheck'] = 'Unable to run pre-check {$a}';
$string['error_usecli'] = 'You can not perform site backup and restore from this page, please use command-line interface.';
$string['excludetablefrombackup'] = 'Add "{$a}" to excluded tables setting.';
$string['expandlogs'] = 'Expand logs';
$string['forgetapikey'] = 'Forget API key';
$string['hidebacktrace'] = 'Hide backtrace';
$string['history'] = 'History';
$string['history_desc'] = 'Logs of past backups and restores on this site.';
$string['lastop_backupfailed_header'] = 'Backup failed';
$string['lastop_backupfailed_text'] = 'Backup performed on {$a} has failed';
$string['lastop_backupfinished_header'] = 'Backup completed';
$string['lastop_backupfinished_text'] = 'Backup {$a->backupkey} started on {$a->started} and finished on {$a->finished}';
$string['lastop_backupinprogress_header'] = 'Backup in progress';
$string['lastop_backupinprogress_text'] = 'You have a backup in progress';
$string['lastop_backupscheduled_header'] = 'Backup scheduled';
$string['lastop_backupscheduled_text'] = 'You backup is now scheduled and will be executed during the next cron run';
$string['loginexplanation'] = 'Create an account on {$a} to be able to backup or restore the site. With the <b>free account</b> you will be able to backup and restore small sites and store them up to 7 days in the cloud.';
$string['manageremoteaccount'] = 'Manage your account';
$string['messageprovider:statusupdate'] = 'Status update for Vault - Site migration';
$string['nopastbackups'] = "You don't have any past backups";
$string['passphrase'] = 'Passphrase';
$string['pastbackupslist'] = 'Past backups on this site';
$string['performedby'] = 'Performed by';
$string['pleasewait'] = 'Please wait...';
$string['pluginname'] = 'Vault - Site migration';
$string['precheckdetails'] = 'Pre-check details';
$string['privacy:metadata'] = 'The Vault plugin doesn\'t store any personal data.';
$string['privacy:metadata:alldata'] = 'All data from the database, file system and dataroot';
$string['privacy:metadata:lmsvault'] = 'Vault miration tool can backup the whole site to the cloud. See https://lmsvault.io for more information.';
$string['refreshpage'] = '<a href="{$a}">Refresh</a> this page to see the updated logs.';
$string['remotesignin'] = 'Sign in';
$string['remotesignup'] = 'Create account';
$string['returntothesite'] = 'Return to the site';
$string['scheduledtask'] = 'Scheduled task';
$string['seefullreport'] = 'See full report';
$string['settings_backupcompressionlevel'] = 'Zip compression level';
$string['settings_backupcompressionlevel_desc'] = 'When the compression level is lower, it will take less time to create zip archives but archives will be larger and it will increase the size of the backup.<br>
Note that files with extensions {$a} are always added to the zip archives with compression level 0, since they are already compressed.';
$string['settings_backupexcludedataroot'] = 'Exclude paths in dataroot';
$string['settings_backupexcludedataroot_desc'] = 'All paths within the dataroot folder will be included in the backup except for: <b>filedir</b> (backed up separately), <b>{$a->always}</b>.<br>If you want to exclude more paths, list them here.<br>Examples of paths that can be excluded: <b>{$a->examples}</b>';
$string['settings_backupexcludeplugins'] = 'Exclude plugins';
$string['settings_backupexcludeplugins_desc'] = "Exclude certain plugins from backups, for example file storage or session management.<br>
For most plugins, you can include them in the backup and uninstall them after restore.<br>
Note that this will only exclude data in the plugin's own tables, settings, associated files, scheduled tasks and other known common types of plugin-related data. It may not be accurate for complicated plugins or plugins with dependencies.";
$string['settings_backupexcludetables'] = 'Exclude tables';
$string['settings_backupexcludetables_desc'] = 'Vault will back up all tables that start with the prefix \'{$a->prefix}\' even if they are not listed in xmldb schemas of the core or installed plugins.<br>Here you can list extra tables that should not be backed up. Use an asterisk (\'*\') to exclude all extra tables.<br>You must include the table prefix when excluding tables.';
$string['settings_clionly'] = 'Allow to perform site backup and restore from CLI only';
$string['settings_clionly_desc'] = 'Checking this will hide the "Vault - Site migration" from the site administration and prevent access to backup and restore. ';
$string['settings_desc'] = "Vault plugin settings where you can configure what to exclude during backup or preserve during restore.";
$string['settings_forcedebug'] = 'Force debugging during backup and restore';
$string['settings_forcedebug_desc'] = 'Regardless of the site configuration, developer debugging and debug display will be enabled during backup and restore. This will affect the tool vault logs only, and will not change the values for these settings that are included in the backup.';
$string['settings_header'] = 'Vault - Site migration settings';
$string['settings_headerbackup'] = 'Backup settings';
$string['showbacktrace'] = 'Show backtrace';
$string['sitebackup'] = 'Site backup';
$string['sitebackup_desc'] = "Backup your whole site to the cloud to restore it on the same or different server, in a different database or different file system.";
$string['siterestore'] = 'Site restore';
$string['startbackup'] = 'Start backup';
$string['startbackup_bucket'] = 'Store data in';
$string['startbackup_cta'] = 'Need more? See <a href="{$a->href}" target="_blank">{$a->link}</a> for pricing.';
$string['startbackup_desc'] = "Your backup will be scheduled and performed during the <b>next cron run</b>.";
$string['startbackup_enc_desc'] = "You may choose to protect your backup with a passphrase, in which case you will need to enter the same passphrase on restore. The passphrase will only be stored on your moodle site until the end of the backup process. If you forget your passphrase, you will not be able to restore this backup.";
$string['startbackup_expiredays_prefix'] = 'Automatically expire backup after';
$string['startbackup_expiredays_suffix'] = 'days';
$string['startbackup_limit_desc'] = 'You can upload backups up to {$a}. If the size of the archived backup is larger than this, your backup will fail.';
$string['startbackup_noenc_desc'] = 'Your data will be encrypted at rest. Specifying your own encryption key is not supported with your current subscription.';
$string['status_failed'] = 'Failed';
$string['status_failedtostart'] = 'Failed to start';
$string['status_finished'] = 'Completed';
$string['status_inprogress'] = 'In progress';
$string['status_scheduled'] = 'Scheduled';
$string['tablealreadyexcluded'] = 'Table "{$a}" is already excluded, re-run this check to see updated status.';
$string['therewasnoactivity'] = 'There was no activity for <b>{$a->elapsedtime}</b>. It is possible that the cron process was interrupted or timed out. The operation will be marked as failed after <b>{$a->locktimeout} of inactivity</b> and access to the site will be restored.';
$string['timefinished'] = 'Completed on';
$string['timestarted'] = 'Started on';
$string['tools'] = 'Tools';
$string['tools_desc'] = 'Various tools and scripts you can run before backup or after restore.';
$string['toschedule'] = 'Schedule';
$string['totalsizearchived'] = 'Total size (archived)';
$string['unknownstatusa'] = 'Unknown status - {$a}';
$string['validationerror'] = 'Validation error: {$a}';
$string['validationmsg_pathnotremovable'] = 'Plugin directory {$a} can not be deleted since it contains read-only objects';
$string['validationmsg_pathnotwritable'] = 'Directory {$a} is not writable';
$string['validationmsg_requiresmoodle'] = 'Plugin requires Moodle version {$a} that is higher than the current version.';
$string['viewdetails'] = 'View details';
$string['waitforcron'] = '... Wait for cron to finish to view report ...';
$string['warning_backupdisabledanotherinprogress'] = 'You can not start backup because there is another backup in progress.';
$string['warning_backupdisablednoapikey'] = 'You can not start backup because you do not have API key';
$string['warning_logsnotavailable'] = 'Not available (backup was performed on a different site)';
$string['withpassphrase'] = 'Passphrase';
$string['youareusingapikey'] = 'You are using API key {$a}';
