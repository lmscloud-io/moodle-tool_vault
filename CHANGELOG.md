# Changelog

All notable changes to the tool_vault plugin will be documented in this file.

## Unreleased

### Added
- Support for Moodle 5.0 and PHP 8.4

## [2.10] - 2025-03-14

### Added
- Allow to resume a restore that failed during datadir or files stages. For restores that
  require upgrade step, the restore can only be resumed from the CLI, since cron will not
  run if an upgrade is pending.

### Fixed
- Improved required temporary disk space calculation for very small backups
- Improved server error logging when "Mysql has gone away" and can't write to database

## [2.9] - 2024-11-17

### Fixed
- Correction to the additional header 'X-Tool-Vault: true'

## [2.8] - 2024-11-17

### Fixed
- Fixed built-in upgrade from 3.11 to 4.5 (exception about undefined function
  `upgrade_delete_orphaned_file_records()`)
- Better error message during disk space pre-check if any paths inside dataroot
  are not readable
- When function `free_disk_space()` is not available, use a fallback to check if
  there is enough space in the temp directory for backup or restore.
### Added
- Allow to backup and restore char fields longer than 1333 symbols, even though
  Moodle does not normally allow them.
- Additional header 'X-Tool-Vault: true' when vault puts the site in maintenance
  mode during backup or restore.

## [2.7] - 2024-10-18

### Added
- Shutdown handler to detect if the process was interrupted.
- Capture all output and show in the vault log.
- Setting to force developer debugging during backup/restore.
### Fixed
- Fixed error when restoring data into tables with reserved words as fields if backup and
  restore sites are on different database engines (mysql/postgres).
- Improved warning message about operation timeout and restoring access to the site.

## [2.6] - 2024-10-13

### Added
- Callback to allow custom distributions to execute code after each table is restored #17
- Setting to change the zip compression level at the backup
### Fixed
- Files over 5Gb were rejected by the storage provider. They are now uploaded using
  multipart upload.

## [2.5] - 2024-10-06

### Added
- Pre-check for both backup and restore checking the PHP setting max_execution_time.
  Fail if it is under one hour, warn if it has a limit.
- Support for Moodle 4.5
### Fixed
- Moved missing plugins information on the top of pre-check results
- Added lang string for 'API key' and changed quotes for some lang strings to
  work around AMOS import bug #14

## [2.4] - 2024-09-24

### Fixed
- Fixed failing restore pre-check when restoring into a higher Moodle version where
  some standard plugins were removed.

## [2.3] - 2024-09-22

### Added
- CHANGE OF DEFAULT BEHAVIOR! New setting "Allow restore with missing plugins", when
  disabled (default), the restore pre-check will fail if there are plugins in the backup
  that are not present on this site.
- Setting to preserve admin password after restore
- Improved logging of the database restore progress (showing restored size rather than
  number of tables).
- Increased size of batches when inserting data in the DB taking into account
  mysql config variable max_allowed_packet

### Fixed
- Migrated after_config callback to hook for Moodle 4.5
- Do not schedule backup precheck in unittests and behat, improving test performance #12

## [2.2] - 2024-08-19

### Fixed
- Avoid exception when the field type in the actual database does not match definition

## [2.1] - 2024-06-12

### Fixed
- Fixed error in the "uninstall missing plugins" script when it is executed right after
  upgrade
- Improved performance by skipping compression of the files that are already compressed
  (for example, zip, jpg, mbz, mp4, etc). It may very slightly increase the backup size
  however it will noticeably reduce the time needed to create archives.

## [2.0] - 2024-06-10

### Added
- Setting to run upgrade as part of the restore process. When selected, Vault will upgrade
  from any version that it supports, which means the upgrades directly from 3.9 -> 4.3 will be
  possible without the need to upgrade to intermediate version (by itself Moodle 4.3 can only
  upgrade from 3.11.8 or later).
- This also allows to run "uninstall missing plugins" script after any restore.
- Added "Tools" section and the "Uninstall missing plugins" as a tool that can be run
  independently.

## [1.9] - 2024-05-05

### Added
- Improvements to the CLI backup/restore, allow to run backup pre-check from CLI
- Setting to disable web access to tool_vault and execute from CLI only
### Fixes
- Fixes to README file and automated tests

## [1.8] - 2024-04-30

### Fixed
- Changed wording for backups with passphrases and a number of other strings
- Fixes to coding style to comply with the latest version of moodle-plugin-ci

## [1.7] - 2024-04-24

### Added
- Possibility to choose where in which region to store your data during backup (for eligible plans)
- Possibility to choose expiration date during backup
- Explanation of the restrictions on the backup screen (for plans with restrictions)
- Reminder to refresh the progress page to see the updated logs
### Fixed
- Do not automatically uninstall missing plugins if an upgrade is pending - it can cause exceptions
  and plugins can be left in half-deleted state that is hard to fix.
- Small fixes to exception messages
- Validate the S3 domain only if S3 encryption headers are sent

## [1.6] - 2024-04-09

### Added
- Improve how extra/invalid tables are reported, allow to exclude them in one click
### Fixed
- Removed excessive validation checks, some plugins have `datetime` columns, it is allowed in xmldb
  but reported as error in the "Database check" in Moodle. Vault should allow them.
- Fixed exceptions when table names contain characters that Moodle does not allow (i.e. `mdl_tablename-old`)

## [1.5] - 2024-04-07

### Added
- Ability to restore into a higher major version of Moodle
### Fixed
- Display more details when backup or restore pre-checks fail, send error backtrace to the server

## [1.4] - 2024-03-29

### Fixed
- Quoting names of the columns that are reserved words (for example, there is a database column
  'count' in the local_wunderbyte_table plugin)

## [1.3] - 2024-03-27

### Fixed
- Prevent curl from sending Authorization header to AWS S3 where it is not needed and
  causes an error.
- Improved error reporting

## [1.2] - 2024-03-25

### Fixed
- Catch exceptions when building list of database modifications, since some plugins
  may have errors in the schema (i.e. block_grade_me)

## [1.1] - 2024-03-24

### Added
- Privacy provider notification that the site is backed up to the external location
- Syntax validation for submitted API key

## [1.0] - 2024-03-16
Initial release
