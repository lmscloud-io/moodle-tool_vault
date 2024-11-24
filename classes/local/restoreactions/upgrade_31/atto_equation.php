<?php
// This file is part of Moodle - http://moodle.org/
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
// Mdlcode-disable incorrect-package-name.

/**
 * Atto equation plugin upgrade script.
 *
 * @package    atto_equation
 * @copyright  2015 Sam Chaffee <sam@moodlerooms.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Run all Atto equation upgrade steps between the current DB version and the current version on disk.
 * @param int $oldversion The old version of atto equation in the DB.
 * @return bool
 */
function tool_vault_31_xmldb_atto_equation_upgrade($oldversion) {

    if ($oldversion < 2015083100) {
        tool_vault_31_atto_equation_update_librarygroup4_setting();

        // Atto equation savepoint reached.
        upgrade_plugin_savepoint(true, 2015083100, 'atto', 'equation');
    }

    // Moodle v3.0.0 release upgrade line.
    // Put any upgrade step following this.

    // Moodle v3.1.0 release upgrade line.
    // Put any upgrade step following this.

    return true;
}

/**
 * Updates the librarygroup4 setting if has not been changed from the default.
 */
function tool_vault_31_atto_equation_update_librarygroup4_setting() {
    // Original default setting for librarygroup4.
    $settingdefault = '
\sum{a,b}
\int_{a}^{b}{c}
\iint_{a}^{b}{c}
\iiint_{a}^{b}{c}
\oint{a}
(a)
[a]
\lbrace{a}\rbrace
\left| \begin{matrix} a_1 & a_2 \\ a_3 & a_4 \end{matrix} \right|
';
    // Make a comparison string.
    $settingdefaultcmpr = trim(str_replace(array("\r", "\n"), '', $settingdefault));

    // Make the current librarygroup4 setting into a comparison string.
    $currentsetting = get_config('atto_equation', 'librarygroup4');
    $currentsettingcmpr = trim(str_replace(array("\r", "\n"), '', $currentsetting));

    if ($settingdefaultcmpr === $currentsettingcmpr) {
        // Only if the original defaults match the current setting do we set the new config.
        $newconfig = '
\sum{a,b}
\sqrt[a]{b+c}
\int_{a}^{b}{c}
\iint_{a}^{b}{c}
\iiint_{a}^{b}{c}
\oint{a}
(a)
[a]
\lbrace{a}\rbrace
\left| \begin{matrix} a_1 & a_2 \\ a_3 & a_4 \end{matrix} \right|
\frac{a}{b+c}
\vec{a}
\binom {a} {b}
{a \brack b}
{a \brace b}
';
        set_config('librarygroup4', $newconfig, 'atto_equation');
    }
}