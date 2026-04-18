[![MDL Shield](https://img.shields.io/endpoint?url=https%3A%2F%2Fmdlshield.com%2Fapi%2Fbadge%2Ftool_vault)](https://mdlshield.com/plugins/tool_vault)

# Vault - Site backup and migration

Vault allows you to make a **full backup of the site**, push it to the cloud and then **restore** on any Moodle site.

<p align="center"><big><big><strong>
Find more information about Vault features at <a href="https://lmsvault.io">https://lmsvault.io</a> or <a href="https://moodle.org/plugins/tool_vault">https://moodle.org/plugins/tool_vault</a>
</strong></big></big></p>

This README file contains information for the **server administrators**.

### Protecting your site from restores

The ability to restore is disabled by default when `tool_vault` is installed. Restores are not
possible if you did not register an account, did not enter an API key, or your API key does not allow restores.

In order to completely prevent restores to the site, add the following lines to your `config.php`:

```php
$CFG->forced_plugin_settings = $CFG->forced_plugin_settings ?? [];
$CFG->forced_plugin_settings['tool_vault'] = ['allowrestore' => 0];
```

## Backup and restore of site configuration

- As part of backing up the database, Vault backs up the **tables 'config' and 'config_plugins'**.
- Vault will also backup the config values that are specified as `$CFG->settingname="value";` or in `$CFG->forced_plugin_settings` and restore them as if they were set in the database. However Vault
  will only do this for the settings that have controls in Site administration tree.
- Settings such as `$CFG->tempdir` or `$CFG->session_handler_class` or `$CFG->alternative_file_system_class`
  will not be backed up. You can see the full list of the config settings that will or will not be
  backed up in the **"Config overrides" pre-check** on the backed up site.
- On the restored site if there is anything set in **config.php** it will take precedence over the restored
  config settings values.
- When you exclude plugins from backup or choose to preserve plugin data during restore, the settings from these plugins will not be backed up or restored.

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
