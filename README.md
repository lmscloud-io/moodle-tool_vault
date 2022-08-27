# Vault - Site migration #

Vault allows you to make a full backup of the site, push it to the cloud and then restore on the other Moodle site.

### Requirements:
- The site where you restore also has to have **Moodle installed**, it can be a fresh installation;
- Plugin **tool_vault** has to be installed on both sites - where you backup and where you restore;
- These two Moodle sites have to have the **same major version** and the site where you are restoring
  has to have at least the same version as the backup site;
- Supported databases - **postgres**, **mysql** and **mariadb**. Other database types are not supported;
- There are some restrictions on database modifications, see below;
- You need to have **enough space** in your temp folder on the backed up site and enough space to store
  downloaded files on the site where you plan to restore.

### Features:
- Vault will export and import **database** structure, schema modifications, tables content and sequences.
- Plugin does not need to have access to mysqldump or pgdump, database will be exported using Moodle DML.
- Vault will export and import all **files**, regardless of the file system (local disk, S3, etc).
- Vault can backup site from postgres and restore it in mysql, backup the files from
  S3 file system and restore in local disk and so on.
- Vault will export and import additional folders in the **dataroot**.
- After restore Vault will perform some necessary **post-restore** actions, for example, purge caches, kill all
  sessions, optionally reset site identifier, etc.
- Vault does not back up or restore its own tables.
- You may choose to set passphrase during backup which will be used to encrypt uploaded
  files. Vault API never has access to your passphrase, it is only used to sign the
  requests to AWS S3.

## Site backup with Vault

- Login as site administrator and navigate to **Site administration > Server > Vault - Site migration**
- On the **"Overview" tab** you can see the results of the several bakckup pre-checks that are automatically
  scheduled when the tool_vault plugin is intalled. You can re-run every check at any moment.
- Backup can be performed only when all pre-checks are successful, which means you have enough disk space,
  and do not have (or excluded) incompatible database schema modifications. The pre-checks will run
  again in the beginning of the backup process.
- If you want to make some changes to what needs to be backed up, you can do it on the **"Settings" tab** of
  the same page.
- When everything is ready, open the **"Site backup" tab**, register with the Vault cloud (or enter existing
  API key) and schedule a backup.
- Backup will be performed during the next cron run. Every step is logged and you can monitor the process
  on the backup page.
- TODO: restricted site access during backup

### Protecting your backup site from accidental restores

Ability to restore is already disabled by default when tool_vault is installed. Also restores are not
possible if you did not register, enter API key or your API key does not support restores.

If you want to additionally
make sure that no other admin can accidentally restore something and wipe out production server, you can
add this to the config.php:

```php
$CFG->forced_plugin_settings = $CFG->forced_plugin_settings ?? [];
$CFG->forced_plugin_settings['tool_vault'] = ['allowrestore' => 0];
```

## Site restore with Vault

- Login as site administrator and navigate to **Site administration > Server > Vault - Site migration**
- Go to the "Settings" tab and **enable restores** on this site.
- Enter your **API key** that you received during registration when you performed backup. It can also be
  found in the "Settings" tab of your Vault on the backup site.
- On the **"Site restore" tab** you will see the list of all backups that are available for you to restore.
  This list is cached and you can re-fetch it again at any moment to look for the new backups.
- **Choose the backup** you want to restore, execute pre-checks for it to make sure that you have enough
  disk space and maybe you need to adjust the restore settings.
- **Schedule the restore**. It will provide you with a link to the restore progress page that can be accessed
  without authentication. Remember that during restore everything is erased from the database and
  nobody will be able to login.
- TODO: lock access to all other pages during restore
- Remember to check **the restore log** since it may not be possible to send a notification to the person who
  initiated restore after the restore process has finished because the site configuration will be overridden
  with the config of the other site.
- **After restore** has completed login with the username and password of a user from the backed up site
  (remember that the users table in the database was replaced).
- If your site version is higher than the version of the restored site or you have plugins that were not
  present on the restored site, you will be prompted to **run upgrade** process.
- If the backed up site had **plugins** that are not present on the restored site, all database tables, settings
  and files of these plugins will be restored and Moodle will report them as **"Missing from disk"**. You will
  need to install these plugins code yourself.

## Backup and restore of site configuration

- As part of backing up the database, Vault backs up the **tables 'config' and 'config_plugins'**.
- Vault will also backup the config values that are specified as `$CFG->settingname="value";` or in `$CFG->forced_plugin_settings` and restore them as if they were set in the database. However Vault
  will only do it for the settings that have controls in Site administration tree.
- Settings such as `$CFG->tempdir` or `$CFG->session_handler_class` or `$CFG->alternative_file_system_class`
  will not be backed up. You can see the full list of the config settings that will and will not be
  backed up in the **"Config overrides" pre-check** on the backed up site.
- On the restored site if there is anything set in **config.php** it will take precedence over the restored
  config settings values.
- TODO: option to "keep" some settings during restore.
- Vault does not backup or restore cache and localcache directories and also 'muc' directory in the dataroot.

## Backup and restore of database modifications

- As part of the **"Database modifications" pre-check** Vault analyses the actual database and compares it
  with the schema definitions in install.xml files in Moodle core, standard and add-on plugins.
- There are the following **types** of possible database modifications: extra tables, missing tables, extra
  indexes, missing indexes, extra table fields, missing table fields, modified table fields.
- Note that not all **field modifications** can be detected, for example, changing the length or precision
  of the int/double/float fields.
- Some database modifications are not supported in Moodle DML, for example, date/time field types, indexes
  on the text fields, varchar fields with the length over 1333. If you have such modifications, the pre-check
  will fail and Vault **will not be able to perform backup**.
- On the "Settings" tab you can specify that some **modifications should be excluded**. You can re-run
  the pre-check after you modified the settings to see if it passes.
- TODO: ignore some modifications during restore.

# Installation of the plugin tool_vault

## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/admin/tool/vault

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.
