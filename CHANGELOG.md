# Changelog

All notable changes to the tool_vault plugin will be documented in this file.

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
