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

namespace tool_vault\local\restoreactions\upgrade_311\helpers;

/**
 * Class general_helper
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class general_helper {

    /**
     * Detects if the site may need to get the calendar events fixed or no. With optional output.
     *
     * @param bool $output true if the function must output information, false if not.
     * @return bool true if the site needs to run the fixes, false if not.
     */
    public static function upgrade_calendar_site_status(bool $output = true): bool {
        global $DB;

        // List of upgrade steps where the bug happened.
        $badsteps = [
            '3.9.5'   => '2020061504.08',
            '3.10.2'  => '2020110901.09',
            '3.11dev' => '2021022600.02',
            '4.0dev'  => '2021052500.65',
        ];

        // List of upgrade steps that ran the fixer.
        $fixsteps = [
            '3.9.6+'  => '2020061506.05',
            '3.10.3+' => '2020110903.05',
            '3.11dev' => '2021042100.02',
            '4.0dev'  => '2021052500.85',
        ];

        $targetsteps = array_merge(array_values($badsteps), array_values( $fixsteps));
        list($insql, $inparams) = $DB->get_in_or_equal($targetsteps);
        $foundsteps = $DB->get_fieldset_sql("
            SELECT DISTINCT version
            FROM {upgrade_log}
            WHERE plugin = 'core'
            AND version " . $insql . "
        ORDER BY version", $inparams);

        // Analyse the found steps, to decide if the site needs upgrading or no.
        $badfound = false;
        $fixfound = false;
        foreach ($foundsteps as $foundstep) {
            $badfound = $badfound ?: array_search($foundstep, $badsteps, true);
            $fixfound = $fixfound ?: array_search($foundstep, $fixsteps, true);
        }
        $needsfix = $badfound && !$fixfound;

        // Let's output some textual information if required to.
        if ($output) {
            mtrace("");
            if ($badfound) {
                mtrace("This site has executed the problematic upgrade step {$badsteps[$badfound]} present in {$badfound}.");
            } else {
                mtrace("Problematic upgrade steps were NOT found, site should be safe.");
            }
            if ($fixfound) {
                mtrace("This site has executed the fix upgrade step {$fixsteps[$fixfound]} present in {$fixfound}.");
            } else {
                mtrace("Fix upgrade steps were NOT found.");
            }
            mtrace("");
            if ($needsfix) {
                mtrace("This site NEEDS to run the calendar events fix!");
                mtrace('');
                mtrace("You can use this CLI tool or upgrade to a version of Moodle that includes");
                mtrace("the fix and will be executed as part of the normal upgrade procedure.");
                mtrace("The following versions or up are known candidates to upgrade to:");
                foreach ($fixsteps as $key => $value) {
                    mtrace("  - {$key}: {$value}");
                }
                mtrace("");
            }
        }
        return $needsfix;
    }
}
