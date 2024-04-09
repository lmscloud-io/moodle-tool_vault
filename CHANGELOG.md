# Changelog

All notable changes to the tool_vault plugin will be documented in this file.

## Unreleased

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
