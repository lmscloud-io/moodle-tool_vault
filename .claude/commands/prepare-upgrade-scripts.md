# Prepare Upgrade Scripts for tool_vault

This skill adds intermediate upgrade scripts to the tool_vault plugin for the current Moodle version.

**DO NOT execute automatically.** Only run when the user explicitly asks (e.g., "run prepare-upgrade-scripts" or "/prepare-upgrade-scripts").

## When to Use

Run this when preparing the plugin for a new Moodle major version that has changed the minimum required upgrade-from version.

**IMPORTANT: You must switch the Moodle branch BEFORE running this skill.** The upgrade scripts are generated from the Moodle version currently checked out, not the version you are preparing for.

For example: if you are preparing for Moodle 5.2 (which requires upgrading from 4.4+), you must:
1. Switch the Moodle codebase to the `MOODLE_404_STABLE` branch (or the appropriate tag)
2. Make sure the database matches that version (i.e., a working Moodle 4.4 installation)
3. Run this skill to generate `upgrade_404/` scripts
4. Switch back to your development branch and commit the generated files

The generated upgrade scripts capture the database schema and plugin versions at the **checked-out** Moodle version, so being on the wrong branch will produce incorrect results.

## Overview

The tool_vault plugin contains intermediate upgrade scripts under `classes/local/restoreactions/upgrade_{BRANCH}/`. Each upgrade stage represents a Moodle release that the plugin can upgrade from. When Moodle raises its minimum required version, a new upgrade stage must be added.

## Prerequisites

- The Moodle codebase must be checked out to the **target intermediate version** branch (NOT the new release branch)
- PHP CLI must be available
- The database must be populated with a working Moodle installation **matching the checked-out branch**

## Step-by-Step Procedure

### Step 0: Determine Paths and Version Info

1. **Find the Moodle root directory** using the `find-moodle-paths` skill with `PLUGIN_NAME=tool_vault`
2. **Determine if this is Moodle >= 5.1**: Check if `public/version.php` exists in MOODLE_ROOT. If yes, use `public/` prefix for all paths below. If no, no prefix needed. Store this as `PUBLIC_PREFIX` (either `public/` or empty string).
3. **Read version info** from `{MOODLE_ROOT}/{PUBLIC_PREFIX}version.php`:
   - `$version` - the core version number (e.g., `2024042200.00`)
   - `$release` - the release string (e.g., `'4.4 (Build: 20240422)'`)
   - `$branch` - the branch identifier (e.g., `'404'`)
4. **Extract the release version** from the `$release` variable. For example, from `'4.4 (Build: 20240422)'`, extract `'4.4'`. Note: for a stable release it may look like `'4.4.1 (Build: ...)'`.

Store these values:
- `BRANCH` = the branch value (e.g., `404`)
- `VERSION` = the version number (e.g., `2024042200.00`)
- `RELEASE` = the extracted release version string (e.g., `4.4` or `4.4.1`)
- `PLUGIN_PATH` = path to tool_vault plugin
- `UPGRADE_DIR` = `{PLUGIN_PATH}/classes/local/restoreactions/upgrade_{BRANCH}`

### Step 1: Find the Previous Upgrade Stage

1. Read `{PLUGIN_PATH}/classes/local/restoreactions/upgrade_base.php`
2. Find the `get_upgrade_classes()` method
3. Identify the **last** upgrade class listed (e.g., `upgrade_402`)
4. Read the corresponding class file to get the previous branch identifier
5. Store the previous branch as `PREV_BRANCH` (e.g., `402`)
6. Read the previous class's `plugin_versions()` to get the starting plugin versions (needed in step 6)

### Step 2: Create the Upgrade Directory and Main Class

1. Create directory: `{UPGRADE_DIR}/`

2. Create file `{UPGRADE_DIR}/upgrade_{BRANCH}.php` with this template:

```php
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_vault\local\restoreactions\upgrade_{BRANCH};

use tool_vault\local\restoreactions\upgrade_base;

/**
 * Class upgrade_{BRANCH}
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upgrade_{BRANCH} extends upgrade_base {
    /**
     * Release version string
     *
     * @return string
     */
    public static function get_release(): string {
        return '{RELEASE}';
    }

    /**
     * Core version number
     *
     * @return float
     */
    public static function get_version(): float {
        return {VERSION};
    }

    /**
     * Branch identifier
     *
     * @return string
     */
    public static function get_branch(): string {
        return '{BRANCH}';
    }

    /**
     * List of standard plugins in {RELEASE} and their exact versions
     *
     * @return array
     */
    protected static function plugin_versions(): array {
        return [
            // PLACEHOLDER - will be populated in Step 3
        ];
    }
}
```

### Step 3: Populate plugin_versions()

1. Create a temporary PHP file at `{MOODLE_ROOT}/{PUBLIC_PREFIX}tool_vault_get_versions.php`:

```php
<?php
define('CLI_SCRIPT', 1);
require("config.php");

$pluginman = core_plugin_manager::instance();
$plugininfo = $pluginman->get_plugins();
foreach ($plugininfo as $type => $plugins) {
    foreach ($plugins as $name => $plugin) {
        if ($plugin->is_standard()) {
            $plugin->load_db_version();
            $pname = $plugin->type.'_'.$plugin->name;
            echo '            "'.$pname.'" => '.$plugin->versiondb.",\n";
        }
    }
}
```

2. Run the script: `php {MOODLE_ROOT}/{PUBLIC_PREFIX}tool_vault_get_versions.php`
3. Copy the output into the `plugin_versions()` function in `upgrade_{BRANCH}.php`, replacing the placeholder
4. Delete the temporary PHP file

### Step 4: Create core.php (Core Upgrade Script)

1. Read the file `{MOODLE_ROOT}/{PUBLIC_PREFIX}lib/db/upgrade.php`
2. Copy the main upgrade function (e.g., `xmldb_main_upgrade`) into a new file `{UPGRADE_DIR}/core.php`
3. Rename the function to `tool_vault_{BRANCH}_core_upgrade`

4. **Find the previous intermediate version's release marker**: The marker to look for is based on the **previous intermediate upgrade stage** (i.e., `PREV_BRANCH`), NOT the current version being generated. Derive the major.minor version from `PREV_BRANCH`:
   - `PREV_BRANCH` `402` -> `v4.2.0`
   - `PREV_BRANCH` `401` -> `v4.1.0`
   - `PREV_BRANCH` `311` -> `v3.11.0`

   Look for the comment:
   ```
   // Automatically generated Moodle v{PREV_MAJOR}.{PREV_MINOR}.0 release upgrade line.
   ```

   For example, if the last intermediate upgrade stage is `upgrade_402` (Moodle 4.2.3), and you are now generating `upgrade_404`, the marker to find is `// Automatically generated Moodle v4.2.0 release upgrade line.` — because you want to keep all steps from 4.2.0 onwards (those are the steps that run after the previous intermediate version).

   **Important**: The comment always uses `.0` (e.g., `v4.2.0`), NOT the full patch release (e.g., NOT `v4.2.3`).

5. **Remove all upgrade steps ABOVE this marker comment**. Keep the marker comment itself and everything below it.

6. **Remove** the line `require_once($CFG->libdir . '/db/upgradelib.php');` if present.

7. **Remove** the minimum version check block at the top (the `if ($oldversion < XXXX) { echo("You need to upgrade..."); exit(1); }` block).

8. **Check for upgradelib function calls**: Search the remaining code for calls to functions that were defined in `{MOODLE_ROOT}/{PUBLIC_PREFIX}lib/db/upgradelib.php`. If any are found:
   - Read `upgradelib.php`
   - Copy the called functions to the bottom of `core.php`
   - Rename each function to start with `tool_vault_{BRANCH}_` prefix
   - Update the function calls in the upgrade steps to use the new names
   - If the copied functions themselves call other functions from upgradelib.php, copy those too (recursively)

9. Add the file header:
```php
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

// phpcs:ignoreFile
```

Add a docblock describing the version range:
```php
/**
 * All upgrade scripts between {PREV_RELEASE} ({PREV_VERSION}) and {RELEASE} ({VERSION})
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
```

### Step 5: Copy Plugin Upgrade Scripts

1. Create a temporary PHP file at `{MOODLE_ROOT}/{PUBLIC_PREFIX}tool_vault_get_upgrades.php`:

```php
<?php
define('CLI_SCRIPT', 1);
require("config.php");

$pluginman = core_plugin_manager::instance();
$plugininfo = $pluginman->get_plugins();
foreach ($plugininfo as $type => $plugins) {
    foreach ($plugins as $name => $plugin) {
        if ($plugin->is_standard()) {
            $pname = $plugin->type.'_'.$plugin->name;
            $path = $plugin->rootdir.'/db/upgrade.php';
            if (file_exists($path)) {
                $contents = file_get_contents($path);
                $c2 = preg_replace('|//[^\\n]*\\n|m', '', $contents);
                $c2 = preg_replace('|/\\*\\*([^\\0]*?)\\*/|m', '', $c2);
                $c2 = preg_replace('|\\s+|m', ' ', $c2);
                if (preg_match('/upgrade.*_savepoint/', $contents)) {
                    echo 'cp '.$path.' '.$pname.".php\n";
                }
            }
        }
    }
}
```

2. Run the script: `php {MOODLE_ROOT}/{PUBLIC_PREFIX}tool_vault_get_upgrades.php`
3. Execute the copy commands from the `{UPGRADE_DIR}/` directory, so files are placed there
4. Delete the temporary PHP file

5. **Process each copied plugin file**:

   For each file `{UPGRADE_DIR}/{PLUGIN_NAME}.php`:

   a. **Rename the function**: Change the function name from `xmldb_{PLUGINSHORT}_upgrade` to `tool_vault_{BRANCH}_xmldb_{PLUGINSHORT}_upgrade`
      - For `mod_*` plugins, the short name is used (e.g., `mod_quiz` -> function uses `quiz`)
      - For other plugins, the full frankenstyle is used (e.g., `tool_cohortroles` -> function uses `tool_cohortroles`)

   b. **Remove old upgrade steps**: These steps were already handled by the previous intermediate upgrade stage, so they must be removed. To determine which steps to remove:
      1. Open the previous stage's class file: `{PLUGIN_PATH}/classes/local/restoreactions/upgrade_{PREV_BRANCH}/upgrade_{PREV_BRANCH}.php`
      2. In its `plugin_versions()` method, find the version number for this specific plugin. For example, if processing `mod_quiz.php` and the previous stage is `upgrade_402`, look for `"mod_quiz" => 2023042400` in `upgrade_402::plugin_versions()`.
      3. That version number (e.g., `2023042400`) is the **cutoff**. Remove all `if ($oldversion < NUMBER) { ... }` blocks where `NUMBER <= 2023042400`.
      4. If the plugin is not listed in the previous stage's `plugin_versions()`, keep all upgrade steps (it may be a new plugin).

   c. **Remove require_once lines outside the function**: Any `require_once()` calls that appear outside the function body must be removed.

   d. **Handle upgradelib.php includes**: If the file includes an `upgradelib.php` file (via `require_once` inside or outside the function):
      - Remove the `require_once` line for the upgradelib
      - Find what functions from upgradelib are called in the remaining upgrade steps
      - Read the original plugin's `upgradelib.php` file
      - Copy the needed functions into the bottom of the plugin file
      - Rename them to start with `tool_vault_{BRANCH}_`
      - Update the function calls to use the new names

   e. **If no upgrade steps remain** after removing old ones, **delete the file entirely** (it's not needed).

   f. **Ensure the file has these lines** near the top (after the license header):
   ```php
   // phpcs:ignoreFile
   // Mdlcode-disable incorrect-package-name.
   ```

   g. **Preserve all PHPDoc comments**: Do NOT remove file-level or function-level docblocks from the original files. Keep them intact (the file description docblock and the function description docblock).

### Step 6: Update upgrade_base.php

1. Edit `{PLUGIN_PATH}/classes/local/restoreactions/upgrade_base.php`

2. Add the import for the new class:
   ```php
   use tool_vault\local\restoreactions\upgrade_{BRANCH}\upgrade_{BRANCH};
   ```

3. Add the new class to the `get_upgrade_classes()` array:
   ```php
   public static function get_upgrade_classes(): array {
       return [
           upgrade_311::class,
           upgrade_401::class,
           upgrade_402::class,
           upgrade_{BRANCH}::class,  // Add this line
       ];
   }
   ```

### Step 7: Cleanup and Verification

1. Verify all files were created correctly:
   - `{UPGRADE_DIR}/upgrade_{BRANCH}.php` exists with populated `plugin_versions()`
   - `{UPGRADE_DIR}/core.php` exists with the core upgrade function
   - Plugin upgrade files exist in `{UPGRADE_DIR}/`
   - `upgrade_base.php` references the new class

2. Delete any temporary PHP files that were created in the Moodle root

3. Report a summary:
   - Branch: {BRANCH}
   - Version: {VERSION}
   - Release: {RELEASE}
   - Previous stage: upgrade_{PREV_BRANCH}
   - Number of plugin upgrade files created
   - Any upgradelib functions that were copied
   - Any issues or warnings encountered

## Important Notes

- All files in the upgrade directory should have `// phpcs:ignoreFile` to skip coding style checks
- Plugin files should also have `// Mdlcode-disable incorrect-package-name.`
- The `core.php` file does NOT need `// Mdlcode-disable incorrect-package-name.`
- Function names must always start with `tool_vault_{BRANCH}_` to avoid conflicts
- The `plugin_versions()` array should only contain standard (core) plugins
- When removing old upgrade steps, compare version numbers numerically, not as strings
- The upgrade steps are `if ($oldversion < NUMBER)` blocks - remove the entire block including the closing brace
- Keep all `// Automatically generated Moodle vX.Y.0 release upgrade line.` comments that remain after cleanup
