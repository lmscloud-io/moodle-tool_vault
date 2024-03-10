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

$string['addonplugins'] = 'Add-on plugins';
$string['addonplugins_extraplugins'] = 'Extra plugins';
$string['addonplugins_extraplugins_desc'] = "The following plugins are present on this site but not present in the backup. All data associated with
these plugins will be deleted during the restore process. After the restore process is finished you will
be prompted to run Moodle upgrade process that will re-install these plugins.";
$string['addonplugins_fail'] = 'Some plugins have lower version than the same plugins in the backup';
$string['addonplugins_missing'] = 'Missing plugins';
$string['addonplugins_missing_autoremove'] = 'Missing plugins will be automatically removed after restore. <a href="{$a}">Change</a>';
$string['addonplugins_missing_desc'] = "The following plugins are present in the backup but are not installed on this site. After the restore
these plugins will be listed as \"Missing form disk\", you can choose to uninstall them or keep the
data until you add the code for the plugins. Until then, you may experience errors, for example,
missing scheduled tasks.";
$string['addonplugins_missing_noautoremove'] = 'You can choose to automatically remove missing plugins after restore in the <a href="{$a}">settings</a>';
$string['addonplugins_notpresent'] = 'Plugins found on this site but not present in the backup';
$string['addonplugins_pluginsdirectory'] = 'Plugins directory';
$string['addonplugins_pluginversioninbackup'] = 'Version in the backup';
$string['addonplugins_pluginversiononsite'] = 'Current version on this site';
$string['addonplugins_subpluginof'] = 'Subplugin of';
$string['addonplugins_success'] = 'Plugins versions in the backup and on this site match';
$string['addonplugins_success_needsupgrade'] = 'Some plugins will need to be upgraded after restore';
$string['addonplugins_success_withmissing'] = 'Some plugins are missing but the restore is possible';
$string['addonplugins_willrequireupgrade'] = 'Will require upgrade';
$string['addonplugins_willrequireupgrade_desc'] = "The following plugins have higher version than in the backup. After the restore process is finished
you will be prompted to run Moodle upgrade process.";
$string['addonplugins_withhigherversion'] = 'Plugins have higher version than the same plugins in the backup';
$string['addonplugins_withlowerversion'] = 'Plugins have lower version than the same plugins in the backup';
$string['addonplugins_withlowerversion_desc'] = "The following plugins have lower version on this site than in the backup.
You must upgrade the code for these plugins before restoring from this backup.";
$string['backup'] = 'Backup';
$string['backupdetails'] = 'Backup details';
$string['backupfinished'] = 'This backup has already finished. You can access the logs <a href="{$a}">here</a>';
$string['backupinprogres'] = 'During backup the site is placed in maintenance mode. Use this page to access logs about the current process';
$string['backupkey'] = 'Backup key';
$string['backupprocesslog'] = 'Backup process log';
$string['backupscheduled'] = 'Backup (scheduled)';
$string['backuptitle'] = 'Backup {$a}';
$string['checkagain'] = 'Check again';
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
$string['dbmodifications_extratable_warning'] = 'Extra table: Table is absent in the definition but is present in the actual database';
$string['dbmodifications_extratables'] = 'Extra tables';
$string['dbmodifications_invalidtables'] = 'Invalid tables';
$string['dbmodifications_missingtable_warning'] = 'Missing table: Table is present in the definition but is absent in the actual database';
$string['dbmodifications_missingtables'] = 'Missing tables';
$string['dbmodifications_status_clean'] = 'Your database tables match descriptions in install.xml';
$string['dbmodifications_status_invalid'] = "Your database has modifications that can not be processed by Moodle.
You need to adjust the 'Backup settings' and exclude some entities if you want to perform site backup";
$string['dbmodifications_status_modified'] = "Your database state does not match specifications in install.xml.
The site can be backed up, the modifications will be included in the backup";
$string['dbmodifications_status_nomodifications'] = 'Site backup can be performed without any database modifications';
$string['defaultbackupdescription'] = '{$a->site} by {$a->name}';
$string['diskspacebackup'] = 'Disk space';
$string['diskspacebackup_countfiles'] = 'Number of files in the file storage';
$string['diskspacebackup_datarootsize'] = 'Required space for dataroot (excluding filedir)';
$string['diskspacebackup_dbmaxsize'] = 'The largest DB table size (approx)';
$string['diskspacebackup_dbrecords'] = 'Total number of rows in DB tables';
$string['diskspacebackup_dbtotalsize'] = 'Required space for database';
$string['diskspacebackup_fail'] = 'There is not enough disk space to perform site backup';
$string['diskspacebackup_freespace'] = 'Free space in temp dir';
$string['diskspacebackup_maxdatarootfilesize'] = 'The largest file in dataroot';
$string['diskspacebackup_maxfilesize'] = 'The largest file in the file storage';
$string['diskspacebackup_success'] = 'There is enough disk space to perform site backup';
$string['diskspacebackup_totalfilesize'] = 'Total size of files in the file storage';
$string['diskspacerestore'] = 'Disk space';
$string['diskspacerestore_dbtotalsizefootnote'] = 'Note, tool Vault is <b>not able to check</b> if there is enough space in the database to perform restore.';
$string['diskspacerestore_fail'] = 'There is not enough disk space in the temporary directory to perform site restore';
$string['diskspacerestore_filedirsize'] = 'Required space for files';
$string['diskspacerestore_mintmpspace'] = 'Minimum space required in temp dir';
$string['diskspacerestore_success'] = 'There is enough disk space in the temporary directory to perform site restore';
$string['diskspacerestore_success_warning'] = "There is enough disk space in the temporary directory however
there may not be enough space for all files and dataroot if they are in the same local disk partition";
$string['enterapikey'] = 'I have an API key';
$string['enterpassphrase'] = 'This backup is encrypted, you need a passphrase';
$string['error_accesskeyisnotvalid'] = 'Accesskey is not valid';
$string['error_anotherbackupisinprogress'] = 'Another backup is in progress';
$string['error_anotherrestoreisinprogress'] = 'Another restore is in progress';
$string['error_apikeynotvalid'] = 'API key not valid';
$string['error_backupfailed'] = 'Backup with the key {$a} has failed';
$string['error_backuphaswrongstatus'] = 'Backup with the key {$a} has a wrong status';
$string['error_backupinprogressnotfound'] = 'Backup in progress not found';
$string['error_backupnotavailable'] = 'Backup with the key {$a} is no longer avaialable';
$string['error_backupnotfinished'] = 'Backup with the key {$a} is not yet completed';
$string['error_backupprecheckfailed'] = "Error occurred while executing backup pre-check '{\$a->name}': {\$a->message}";
$string['error_cannotcreatezip'] = 'Can not create ZIP file';
$string['error_dbstructurenotvalid'] = 'Archive {$a} does not contain database structure';
$string['error_filenotfound'] = 'Expected file {$a} not found';
$string['error_invaliddownloadlink'] = 'Vault API did not return a valid download link for {$a->filename}: {$a->url}';
$string['error_invaliduploadlink'] = 'Vault API did not return a valid upload link {$a}';
$string['error_metadatanotvalid'] = 'Archive {$a} does not contain backup metadata';
$string['error_notavalidlink'] = 'Vault API did not return a valid link: {$a}';
$string['error_restoresnotallowed'] = 'Restores are not allowed on this site';
$string['error_serverreturnednodata'] = 'Server returned no data';
$string['error_unabletorunprecheck'] = 'Unable to run pre-check {$a}';
$string['expandlogs'] = 'Expand logs';
$string['forgetapikey'] = 'Forget API key';
$string['hidebacktrace'] = 'Hide backtrace';
$string['history'] = 'History';
$string['history_desc'] = 'Logs of past backups and restores on this site.';
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
$string['manageremoteaccount'] = 'Manage your account';
$string['messageprovider:statusupdate'] = 'Status update for Vault - Site migration';
$string['moodleversion_backupinfo'] = 'Backup made in version {$a->version} (branch {$a->branch})';
$string['moodleversion_fail'] = "Site version number has to be greater than or equal to the version in the backup.
This backup is {\$a->version} and this site is {\$a->siteversion}";
$string['moodleversion_siteinfo'] = 'This website has version {$a->version} (branch {$a->branch})';
$string['moodleversion_success'] = 'Moodle version matches';
$string['nobackupsavailable'] = 'There are no remote backups avaiable for restore';
$string['nopastbackups'] = "You don't have any past backups";
$string['nopastrestores'] = "You don't have any past restores";
$string['operation'] = 'Operation {$a}';
$string['passphrase'] = 'Passphrase';
$string['pastbackupslist'] = 'Past backups on this site';
$string['pastrestoreslist'] = 'Past restores on this site';
$string['performedby'] = 'Performed by';
$string['pleasewait'] = 'Please wait...';
$string['pluginname'] = 'Vault - Site migration';
$string['precheckdetails'] = 'Pre-check details';
$string['privacy:metadata'] = 'The Vault plugin doesn\'t store any personal data.';
$string['remotebackup'] = 'Remote backup';
$string['remotebackupdetails'] = 'Remote backup details';
$string['remotebackups'] = 'Remote backups';
$string['remotesignin'] = 'Sign in';
$string['remotesignup'] = 'Create account';
$string['repeatprecheck'] = 'Repeat pre-check';
$string['restoredetails'] = 'Restore details';
$string['restorefinished'] = 'This restore has already finished. You can access the logs <a href="{$a}">here</a>';
$string['restorefrombackup'] = 'Restore from backup {$a}';
$string['restoreinprogress'] = 'During restore the site is placed in maintenance mode. Use this page to access logs about the current process';
$string['restoreprechecks'] = 'Restore pre-checks';
$string['restoreremovemissing'] = 'Automatically remove missing plugins after restore';
$string['restoreremovemissing_desc'] = "If backup contains data for the plugins that are not present in this site codebase, they will be marked as \"Missing from disk\" in the plugin overview.<br>
- Enabling this option will automatically uninstall these plugins and remove all data associated with them from the database and file storage<br>
- Disabling this option will leave the data in the database and you will be able to add plugins code after the restore is finished or run the uninstall script manually.";
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
$string['settings_desc'] = "Vault plugin settings where you can configure what to exclude during backup or
preserve during restore.";
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
$string['sitebackup_desc'] = "Backup your whole site to the cloud with possibility to restore it on the same or different
server, in a different database or different file system.";
$string['siterestore'] = 'Site restore';
$string['siterestore_desc'] = "Restore a site from the cloud backup that you have made yourself or somebody shared with you.<br>
Note that the whole content of the current site will be replaced with the content of the backup.";
$string['siterestore_notallowed_desc'] = 'Restoring on this site is not allowed. You can change it in the plugin settings.';
$string['startbackup'] = 'Start backup';
$string['startbackup_desc'] = "Your backup will be scheduled and performed during the next cron run. You may choose to encrypt your backup with a passphrase,
in this case you will need to enter the same passphrase on restore. Passphrase will only be stored on your moodle site
until the end of the backup process.";
$string['startdryrun'] = 'Run pre-check';
$string['startrestore'] = 'Restore this backup';
$string['startrestore_desc'] = 'Restore will be executed on the next cron run. Important! All data from this site will be deleted!';
$string['startrestoreprecheck_desc'] = 'Restore pre-check will be executed on the next cron run.';
$string['status_failed'] = 'Failed';
$string['status_failedtostart'] = 'Failed to start';
$string['status_finished'] = 'Completed';
$string['status_inprogress'] = 'In progress';
$string['status_scheduled'] = 'Scheduled';
$string['timefinished'] = 'Completed on';
$string['timestarted'] = 'Started on';
$string['totalsizearchived'] = 'Total size (archived)';
$string['unknownstatusa'] = 'Unknown status - {$a}';
$string['viewbackupdetails'] = 'View backup details';
$string['viewdetails'] = 'View details';
$string['waitforcron'] = '... Wait for cron to finish to view report ...';
$string['warning_backupdisabledanotherinprogress'] = 'You can not start backup because there is another backup in progress.';
$string['warning_backupdisablednoapikey'] = 'You can not start backup because you do not have API key';
$string['warning_logsnotavailable'] = 'Not available (backup was performed on a different site)';
$string['youareusingapikey'] = 'You are using API key {$a}';
