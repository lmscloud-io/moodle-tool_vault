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
$string['addonplugins_autoupgrade'] = 'Moodle upgrade process will be performed automatically during restore. <a href="{$a}">Change</a>.';
$string['addonplugins_extraplugins'] = 'Extra plugins';
$string['addonplugins_extraplugins_desc'] = "The following plugins are present on this site but not present in the backup. All data associated with these plugins will be deleted during the restore process. After the restore process is finished you will be prompted to run the Moodle upgrade process that will re-install these plugins.";
$string['addonplugins_fail'] = 'The backup contains plugins with higher versions than the code on this site. You must upgrade the code for these plugins before restoring from this backup.';
$string['addonplugins_fail_missing'] = 'Some add-on plugins are present in the backup but not installed on this site. You must either install these plugins or enable the setting "Allow restore with missing plugins".';
$string['addonplugins_missing'] = 'Missing plugins';
$string['addonplugins_missing_autoremove'] = 'Missing plugins will be automatically removed after restore. <a href="{$a}">Change</a>';
$string['addonplugins_missing_desc'] = "The following plugins are present in the backup but are not installed on this site. After the restore these plugins will be listed as \"Missing from disk\", you can choose to uninstall them or keep the data until you add the code for the plugins. Until then, you may experience errors, for example, missing scheduled tasks.";
$string['addonplugins_missing_noautoremove'] = 'You can choose to automatically remove missing plugins after restore in the <a href="{$a}">settings</a>';
$string['addonplugins_noautoupgrade'] = 'You can choose to automatically run the Moodle upgrade process as part of the restore on the <a href="{$a}">settings page</a>';
$string['addonplugins_notpresent'] = 'Plugins found on this site but not present in the backup';
$string['addonplugins_pluginsdirectory'] = 'Plugins directory';
$string['addonplugins_pluginversioninbackup'] = 'Version in the backup';
$string['addonplugins_pluginversiononsite'] = 'Current version on this site';
$string['addonplugins_restorewithmissing_not_allowed'] = 'Restore with missing plugins is not allowed. <a href="{$a}">Change</a>.';
$string['addonplugins_subpluginof'] = 'Subplugin of';
$string['addonplugins_success'] = 'All plugins in the backup have the same version as the plugins on this site';
$string['addonplugins_success_needsupgrade'] = 'Some plugins will need to be upgraded after restore';
$string['addonplugins_success_withmissing'] = 'Some plugins are missing but the restore is possible';
$string['addonplugins_willrequireupgrade'] = 'Will require upgrade';
$string['addonplugins_willrequireupgrade_desc'] = "The following plugins have a higher version than in the backup. After the restore process is finished you will be prompted to run the Moodle upgrade process.";
$string['addonplugins_withhigherversion'] = 'Plugins that have a higher code version compared to what exists in the backup';
$string['addonplugins_withlowerversion'] = 'Plugins that have a lower code version compared to what exists in the backup';
$string['addonplugins_withlowerversion_desc'] = "The following plugins have a lower version on this site than in the backup. You must upgrade the code for these plugins before restoring from this backup.";
$string['apikey'] = 'API key';
$string['backup'] = 'Backup';
$string['backupdescription'] = 'Backup description';
$string['backupdetails'] = 'Backup details';
$string['backupfinished'] = 'This backup has already finished. You can access the logs <a href="{$a}">here</a>';
$string['backupinprogres'] = 'During backup the site is placed in maintenance mode. Use this page to access logs about the current process';
$string['backupkey'] = 'Backup key';
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
$string['containsstandardplugins'] = '{$a} standard plugins';
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
$string['diskspacerestore'] = 'Disk space';
$string['diskspacerestore_dbtotalsizefootnote'] = 'Note, tool Vault is <b>not able to check</b> if there is enough space in the database to perform restore.';
$string['diskspacerestore_fail'] = 'There is not enough disk space in the temporary directory to perform site restore';
$string['diskspacerestore_filedirsize'] = 'Required space for files';
$string['diskspacerestore_mintmpspace'] = 'Minimum space required in temp dir';
$string['diskspacerestore_success'] = 'There is enough disk space in the temporary directory to perform site restore';
$string['diskspacerestore_success_warning'] = "There is enough disk space in the temporary directory however there may not be enough space for all files and dataroot if they are in the same local disk partition";
$string['enterapikey'] = 'I have an API key';
$string['enterpassphrase'] = 'This backup is protected with a passphrase';
$string['environ_fail'] = 'Tool vault was not able to raise max_execution_time setting. Please refer to <a href="{$a}" target="_blank">{$a}</a>';
$string['environ_success_warning'] = 'Tool vault was not able to raise max_execution_time setting. If the process does not finish within {$a->value}, it will be aborted. Please refer to <a href="{$a->url}" target="_blank">{$a->url}</a>';
$string['environbackup'] = 'Environment check';
$string['environbackup_maxexecutiontime'] = 'Maximum execution time (max_execution_time)';
$string['error_accesskeyisnotvalid'] = 'Accesskey is not valid';
$string['error_anotherbackupisinprogress'] = 'Another backup is in progress';
$string['error_anotherrestoreisinprogress'] = 'Another restore is in progress';
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
$string['error_dbstructurenotvalid'] = 'Archive {$a} does not contain database structure';
$string['error_failedmultipartupload'] = 'Failed to start multipart upload. UploadId not found in the command output: {$a}';
$string['error_filenotfound'] = 'Expected file {$a} not found';
$string['error_invaliddownloadlink'] = 'Vault API did not return a valid download link for {$a->filename}: {$a->url}';
$string['error_invaliduploadlink'] = 'Vault API did not return a valid upload link {$a}';
$string['error_metadatanotvalid'] = 'Archive {$a} does not contain backup metadata';
$string['error_notavalidlink'] = 'Vault API did not return a valid link: {$a}';
$string['error_passphrasewrong'] = 'Error accessing passphrase protected backup. Verify the passphrase and try again.';
$string['error_restoreprecheckfailed'] = 'Error occurred while executing restore pre-check \'{$a->name}\': {$a->message}';
$string['error_restoresnotallowed'] = 'Restores are not allowed on this site';
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
$string['loginexplanation'] = 'Create an account on {$a} to be able to backup or restore the site. With the <b>free account</b> you will be able to backup and restore small sites and store them up to 7 days in the cloud.';
$string['manageremoteaccount'] = 'Manage your account';
$string['messageprovider:statusupdate'] = 'Status update for Vault - Site migration';
$string['moodleversion_backupinfo'] = 'Backup made in version {$a->version} (Moodle {$a->branch})';
$string['moodleversion_fail'] = 'Site version number has to be greater than or equal to the version in the backup. This backup is {$a->version} and this site is {$a->siteversion}';
$string['moodleversion_fail_cannotupgrade'] = 'Restore is blocked because Moodle cannot upgrade from {$a->backuprelease} to {$a->currentrelease}. See the <a href="{$a->url}">environment page</a> for upgrade requirements. Vault is able to upgrade from this version if you choose "Automatically upgrade Moodle after restore" in the <a href="{$a->settingsurl}">settings</a>.';
$string['moodleversion_siteinfo'] = 'This website has version {$a->version} (Moodle {$a->branch})';
$string['moodleversion_success'] = 'Moodle version matches';
$string['moodleversion_success_withautoupgrade'] = 'Restore can be performed. Vault will automatically run the Moodle upgrade process as part of the restore.';
$string['moodleversion_success_withextraupgrade'] = 'Restore can be performed. Vault will upgrade the site from {$a->backuprelease} to {$a->intermediaryrelease} and then perform a standard Moodle upgrade to {$a->currentrelease}.';
$string['moodleversion_success_withupgrade'] = 'Restore can be performed but you will need to run the Moodle upgrade process after it completes.';
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
$string['privacy:metadata:alldata'] = 'All data from the database, file system and dataroot';
$string['privacy:metadata:lmsvault'] = 'Vault miration tool can backup the whole site to the cloud. See https://lmsvault.io for more information.';
$string['refreshpage'] = '<a href="{$a}">Refresh</a> this page to see the updated logs.';
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
$string['returntothesite'] = 'Return to the site';
$string['scheduledtask'] = 'Scheduled task';
$string['seefullreport'] = 'See full report';
$string['setting_restoreremovemissing'] = 'Automatically remove missing plugins after restore';
$string['setting_restoreremovemissing_desc'] = "If a backup contains data for plugins that are not present in this site codebase, they will be marked as \"Missing from disk\" in the plugin overview.<br>
- Enabling this option will automatically uninstall these plugins and remove all data associated with them from the database and file storage.<br>
- Disabling this option will leave the data in the database and you will be able to add the plugins code after the restore is finished or run the uninstall script manually.<br>
<em>Missing plugins will not be removed if the upgrade is pending after the restore, i.e. if you restore into a higher Moodle version or other plugins need to be upgraded. In this case you can select \"Automatically upgrade Moodle after restore\".</em>";
$string['setting_upgradeafterrestore'] = 'Automatically upgrade Moodle after restore';
$string['setting_upgradeafterrestore_desc'] = 'After the site is restored, immediately launch the Moodle upgrade process. When selected, Vault will be able to upgrade Moodle from any version it supported for the backup. For example, you can upgrade from 3.9 directly to 4.3, even though Moodle by itself would require you to first upgrade to 3.11.8-4.2 and only then to 4.3.';
$string['settings_allowrestore'] = 'Allow restores on this site';
$string['settings_allowrestore_desc'] = 'Site restore will completely remove all contents of this site and replace with the restored contents. Double check everything before enabling this option';
$string['settings_allowrestorewithmissing'] = 'Allow restore with missing plugins';
$string['settings_allowrestorewithmissing_desc'] = 'If there were add-on plugins present in the backed up site but the code for these plugins is not present on this site, the restore can still be performed. After the restore these plugins will be listed as "Missing from disk", and you can choose to uninstall them or keep the data until you add the code for the plugins. Until then, you may experience errors, for example, missing scheduled tasks.';
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
$string['settings_backupplugincode'] = 'Backup code of add-on plugins';
$string['settings_backupplugincode_desc'] = "Include the code of the add-on plugins into the backup. Important notes:
<ul>
<li>If a plugin code was added using git, no information about git repository or commit history will be included</li>
<li>there is no guarantee that the site where you restore this backup will allow to write to the codebase and restore may not be possible</li>
<li>If you are restoring into a higher version of Moodle, this version of the plugin may not be compatible with it.</li>
<li>no code modifications to Moodle core or standard plugins will be included in the backup</li>
</ul>
";
$string['settings_clionly'] = 'Allow to perform site backup and restore from CLI only';
$string['settings_clionly_desc'] = 'Checking this will hide the "Vault - Site migration" from the site administration and prevent access to backup and restore. ';
$string['settings_desc'] = "Vault plugin settings where you can configure what to exclude during backup or preserve during restore.";
$string['settings_forcedebug'] = 'Force debugging during backup and restore';
$string['settings_forcedebug_desc'] = 'Regardless of the site configuration, developer debugging and debug display will be enabled during backup and restore. This will affect the tool vault logs only, and will not change the values for these settings that are included in the backup.';
$string['settings_header'] = 'Vault - Site migration settings';
$string['settings_headerbackup'] = 'Backup settings';
$string['settings_headerrestore'] = 'Restore settings';
$string['settings_restorepreservedataroot'] = 'Preserve paths in dataroot';
$string['settings_restorepreservedataroot_desc'] = 'During restore all paths within the dataroot folder will be removed except for: <b>filedir</b> (restored separately), <b>{$a->always}</b>.<br>If you want to keep more paths, list them here. Examples of paths that can be preserved: <b>{$a->examples}</b>';
$string['settings_restorepreservepasswords'] = 'Preserve user passwords';
$string['settings_restorepreservepasswords_desc'] = "List of usernames (comma-separated) of the users whose passwords need to be preserved after restore (for example, \"admin\").
During restore the 'user' table will be overridden with the contents of the 'user' table in the backup.
This means that after the restore the admin will need to login with the password that was set on the backed up site.
When this setting is not empty, the password of the listed users will be changed after restore to match the value on the current site.
This will apply only to users with the 'manual' authentication method.";
$string['settings_restorepreserveplugins'] = 'Preserve plugins';
$string['settings_restorepreserveplugins_desc'] = "Only for plugins with server-specific configuration, for example, file storage or session management. <br>
Restore process will attempt to preserve existing data associated with these plugins and not restore data from the backup if the same plugin is included.<br>
Note that this will only process data in the plugin's own tables, settings, associated files, scheduled tasks and other known common types of plugin-related data. It may not be accurate for complicated plugins or plugins with dependencies.";
$string['showbacktrace'] = 'Show backtrace';
$string['sitebackup'] = 'Site backup';
$string['sitebackup_desc'] = "Backup your whole site to the cloud to restore it on the same or different server, in a different database or different file system.";
$string['siterestore'] = 'Site restore';
$string['siterestore_desc'] = "Restore a cloud backup into this site.<br>
Note that the whole content of the current site will be replaced with the content of the backup.";
$string['siterestore_notallowed_desc'] = 'Restoring on this site is not allowed. You can change it in the plugin settings.';
$string['startbackup'] = 'Start backup';
$string['startbackup_bucket'] = 'Store data in';
$string['startbackup_cta'] = 'Need more? See <a href="{$a->href}" target="_blank">{$a->link}</a> for pricing.';
$string['startbackup_desc'] = "Your backup will be scheduled and performed during the <b>next cron run</b>.";
$string['startbackup_enc_desc'] = "You may choose to protect your backup with a passphrase, in which case you will need to enter the same passphrase on restore. The passphrase will only be stored on your moodle site until the end of the backup process. If you forget your passphrase, you will not be able to restore this backup.";
$string['startbackup_expiredays_prefix'] = 'Automatically expire backup after';
$string['startbackup_expiredays_suffix'] = 'days';
$string['startbackup_limit_desc'] = 'You can upload backups up to {$a}. If the size of the archived backup is larger than this, your backup will fail.';
$string['startbackup_noenc_desc'] = 'Your data will be encrypted at rest. Specifying your own encryption key is not supported with your current subscription.';
$string['startdryrun'] = 'Run pre-check';
$string['startrestore'] = 'Restore this backup';
$string['startrestore_desc'] = 'Restore will be executed on the next cron run. Important! All data from this site will be deleted!';
$string['startrestoreprecheck_desc'] = 'Restore pre-check will be executed on the next cron run.';
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
$string['tools_uninstallmissingplugins'] = 'Uninstall missing plugins';
$string['tools_uninstallmissingplugins_desc'] = 'Delete all data associated with the plugins that are marked as "Missing from disk" in the plugin overview. This process will be performed asynchronously during the next cron run.';
$string['toschedule'] = 'Schedule';
$string['totalsizearchived'] = 'Total size (archived)';
$string['unknownstatusa'] = 'Unknown status - {$a}';
$string['validationerror'] = 'Validation error: {$a}';
$string['validationmsg_pathnotremovable'] = 'Plugin directory {$a} can not be deleted since it contains read-only objects';
$string['validationmsg_pathnotwritable'] = 'Directory {$a} is not writable';
$string['validationmsg_requiresmoodle'] = 'Plugin requires Moodle version {$a} that is higher than the current version.';
$string['viewbackupdetails'] = 'View backup details';
$string['viewdetails'] = 'View details';
$string['viewerrordetails'] = 'View details of the error';
$string['waitforcron'] = '... Wait for cron to finish to view report ...';
$string['warning_backupdisabledanotherinprogress'] = 'You can not start backup because there is another backup in progress.';
$string['warning_backupdisablednoapikey'] = 'You can not start backup because you do not have API key';
$string['warning_logsnotavailable'] = 'Not available (backup was performed on a different site)';
$string['withpassphrase'] = 'Passphrase';
$string['youareusingapikey'] = 'You are using API key {$a}';
