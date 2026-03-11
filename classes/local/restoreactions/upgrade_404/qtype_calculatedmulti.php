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
// Mdlcode-disable incorrect-package-name.

/**
 * Calculated multiple-choice question type upgrade code.
 *
 * @package    qtype_calculatedmulti
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade code for the calculatedmulti question type.
 * @param int $oldversion the version we are upgrading from.
 * @return bool
 */
function tool_vault_404_xmldb_qtype_calculatedmulti_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2024011700) {
        $DB->execute("UPDATE {question_answers}
                              SET answerformat = '" . FORMAT_PLAIN . "'
                            WHERE question IN (
                                SELECT id
                                  FROM {question}
                                 WHERE qtype = 'calculatedmulti'
                                 )"
        );

        // Calculatedmulti savepoint reached.
        upgrade_plugin_savepoint(true, 2024011700, 'qtype', 'calculatedmulti');
    }

    return true;
}
