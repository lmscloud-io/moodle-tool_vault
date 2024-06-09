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

/**
 * Display H5P upgrade code
 *
 * @package    filter_displayh5p
 * @copyright  2019 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * xmldb_filter_displayh5p_upgrade
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function tool_vault_311_xmldb_filter_displayh5p_upgrade($oldversion) {
    global $CFG;

    if ($oldversion < 2019110800) {
        // We need to move up the displayh5p filter over urltolink and activitynames filters to works properly.
        tool_vault_311_filter_displayh5p_reorder();

        upgrade_plugin_savepoint(true, 2019110800, 'filter', 'displayh5p');
    }

    // Automatically generated Moodle v3.8.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2020031700) {
        // References to h5p.org has to be removed as default value for the allowedsources in the filter because H5P is going
        // to close it down completely so that only the author can see the test content.
        $h5porgurl = 'https://h5p.org/h5p/embed/[id]';
        $config = get_config('filter_displayh5p', 'allowedsources');
        if (strpos($config, $h5porgurl) !== false) {
            set_config('allowedsources', str_replace($h5porgurl, '', $config), 'filter_displayh5p');
        }

        upgrade_plugin_savepoint(true, 2020031700, 'filter', 'displayh5p');
    }

    // Automatically generated Moodle v3.9.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.10.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.11.0 release upgrade line.
    // Put any upgrade step following this.

    return true;
}

function tool_vault_311_filter_displayh5p_reorder() {

    // The filter enabled is mandatory to be able to display the H5P content.
    filter_set_global_state('displayh5p', TEXTFILTER_ON);

    $states = filter_get_global_states();
    $displayh5ppos = $states['displayh5p']->sortorder;
    $activitynamespos = 1;
    if (!empty($states['activitynames'])) {
        $activitynamespos = $states['activitynames']->sortorder;
    }
    $urltolinkpos = 1;
    if (!empty($states['urltolink'])) {
        $urltolinkpos = $states['urltolink']->sortorder;
    }
    $minpos = ($activitynamespos < $urltolinkpos) ? $activitynamespos : $urltolinkpos;
    while ($minpos < $displayh5ppos) {
        filter_set_global_state('displayh5p', TEXTFILTER_ON, -1);
        $displayh5ppos--;
    }
}
