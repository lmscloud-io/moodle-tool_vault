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
// Mdlcode-disable unknown-db-tablename.
// Mdlcode-disable incorrect-package-name.

/**
 * Upgrade code for the feedback_editpdf module.
 *
 * @package   assignfeedback_editpdf
 * @copyright 2013 Jerome Mouneyrac
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_vault\local\restoreactions\upgrade_401\helpers\general_helper;

defined('MOODLE_INTERNAL') || die();

/**
 * EditPDF upgrade code
 * @param int $oldversion
 * @return bool
 */
function tool_vault_401_xmldb_assignfeedback_editpdf_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    // Automatically generated Moodle v4.0.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2022061000) {
        $table = new xmldb_table('assignfeedback_editpdf_queue');
        if ($dbman->table_exists($table)) {
            // Convert not yet converted submissions into adhoc tasks.
            $rs = $DB->get_recordset('assignfeedback_editpdf_queue');
            foreach ($rs as $record) {
                $data = [
                    'submissionid' => $record->submissionid,
                    'submissionattempt' => $record->submissionattempt,
                ];
                general_helper::queue_adhoc_task(assignfeedback_editpdf\task\convert_submission::class, true, $data);
            }
            $rs->close();

            // Drop the table.
            $dbman->drop_table($table);
        }

        // Editpdf savepoint reached.
        upgrade_plugin_savepoint(true, 2022061000, 'assignfeedback', 'editpdf');
    }

    if ($oldversion < 2022082200) {
        // Conversion records need to be removed in order for conversions to restart.
        $DB->delete_records('file_conversion');

        // Schedule an adhoc task to fix existing stale conversions.
        general_helper::queue_adhoc_task(\assignfeedback_editpdf\task\bump_submission_for_stale_conversions::class);

        upgrade_plugin_savepoint(true, 2022082200, 'assignfeedback', 'editpdf');
    }

    // Automatically generated Moodle v4.1.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2022112801) {
        general_helper::queue_adhoc_task(\assignfeedback_editpdf\task\remove_orphaned_editpdf_files::class);

        upgrade_plugin_savepoint(true, 2022112801, 'assignfeedback', 'editpdf');
    }

    return true;
}
