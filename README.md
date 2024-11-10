# Vault - Site migration #

Vault allows you to make a **full backup of the site**, push it to the cloud and then **restore** on any Moodle site.

Note that this version can ONLY BACKUP. Restore is only possible in the later Moodle versions.

See also https://lmsvault.io/

### Features:
- Vault will export and import **database** structure, schema modifications, tables content and sequences.
- Vault can restore not only into a different DB version but also into a **different DB type**
  (i.e. from mysql to postgres or vice versa);
- Vault plugin does not need to have access to mysqldump or pgdump, the database will be exported using Moodle DML.
- Vault will export and import all **files**, regardless of the file system (local disk, S3, etc), the file
  system on the restored site can be different from the backup site;
- Vault will export and import additional folders in the **dataroot**.
- After restore Vault will perform some necessary **post-restore** actions, for example, purge caches, kill all
  sessions, optionally reset site identifier, optionally uninstall missing plugins etc.
- Vault does not back up or restore Moodle code or add-on plugins code.
- Vault does not back up or restore its own tables.
- Other plugins can be excluded during backup/restore (with some limitations).
- All data transferred to or from tool_vault is encrypted during transit using SSL/TLS.
- In some plans you may choose to set a passphrase during backup which will be used to encrypt data stored
  at rest in the cloud.
  Vault API never has access to your passphrase, it is only used to sign the
  requests to Amazon S3.

### Requirements:
- The site where you restore must have **Moodle installed**, it can be a fresh installation;
- Plugin **tool_vault** must be installed on both sites - where you back up and where you restore;
- The site where you are restoring to must have the same or higher Moodle version than the backed up site;
- Supported databases - **postgres**, **mysql** and **mariadb**. Other database types are not yet supported;
- There are some restrictions on database modifications, see below;
- You need to have **enough space** in your temp folder on the backed up site and enough space to store
  downloaded files on the site where you plan to restore.

## Site backup with Vault

- Login as site administrator and navigate to **Site administration > Server > Vault - Site migration**
- Before backup, Vault performs a number of pre-checks. They can also
  be executed independently. The results are displayed on the 'Site backup' page.
- Backup can be performed only when all pre-checks are successful, which means you have enough disk space,
  and do not have (or excluded) incompatible database schema modifications. The pre-checks will run
  again in the beginning of the backup process.
- If you want to make changes to what needs to be backed up, you can do it in the "Settings" section.
- When everything is ready, open the **"Site backup"** section, register with the LMSVault cloud (or enter existing
  API key) and schedule a backup.
- Backup will be performed during the next cron run. Every step is logged and you can monitor the process
  on the backup page.

## Backup and restore of site configuration

- As part of backing up the database, Vault backs up the **tables 'config' and 'config_plugins'**.
- Vault will also backup the config values that are specified as `$CFG->settingname="value";` or in `$CFG->forced_plugin_settings` and restore them as if they were set in the database. However Vault
  will only do this for the settings that have controls in Site administration tree.
- Settings such as `$CFG->tempdir` or `$CFG->session_handler_class` or `$CFG->alternative_file_system_class`
  will not be backed up. You can see the full list of the config settings that will or will not be
  backed up in the **"Config overrides" pre-check** on the backed up site.
- On the restored site if there is anything set in **config.php** it will take precedence over the restored
  config settings values.
- Vault does not backup or restore `cache`, `localcache`, or `muc` directories in the dataroot.

## Backup and restore of database modifications

- As part of the **"Database modifications" pre-check** Vault analyses the actual database and compares it
  with the schema definitions in install.xml files in Moodle core, standard and add-on plugins.
- There are the following **types** of possible database modifications: extra tables, missing tables, extra
  indexes, missing indexes, extra table fields, missing table fields, modified table fields.
- Note that not all **field modifications** can be detected, for example, changing the length or precision
  of the int/double/float fields.
- Some database modifications are not supported in the Moodle DML, for example, indexes
  on text fields, varchar fields with length over 1333. If you have such modifications, the pre-check
  will fail and Vault **will not be able to perform backup**.
- In the "Settings" section you can specify that some **modifications should be excluded**. You can re-run
  the pre-check after you modified the settings to see if it passes.

## CLI access

The plugin contains three CLI scripts that can be used to perform backup, restore, or list remote
backups. When using CLI commands there is no need to run cron.

You can completely prevent access to the web interface and use the plugin from CLI only by changing the
settings or adding the following to `config.php`:

```php
$CFG->forced_plugin_settings = $CFG->forced_plugin_settings ?? [];
$CFG->forced_plugin_settings['tool_vault'] = ['clionly' => 1];
```

# Installation of the plugin tool_vault

## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration > Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {YOUR/MOODLE/ROOT}/admin/tool/vault

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.
