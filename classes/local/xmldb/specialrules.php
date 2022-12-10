<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_vault\local\xmldb;

use tool_vault\api;

/**
 * Special rules when tables or indexes need to be excluded or modified
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class specialrules {
    /**
     * Ignore index in the actual table
     *
     * @param \xmldb_table $table
     * @param string $dbindexname
     * @param array $dbindex
     * @return bool
     */
    public static function is_actual_index_ignored(\xmldb_table $table, $dbindexname, $dbindex) {
        global $CFG;
        if ($table->getName() === 'search_simpledb_index') {
            // Hack - skip for table 'search_simpledb_index' as this plugin adds indexes dynamically on install
            // which are not included in install.xml. See search/engine/simpledb/db/install.php.
            if (preg_match('/to_tsvector/', $dbindex['columns'][0])) {
                return true;
            }
            if (in_array('description1', $dbindex['columns'])) {
                return true;
            }
        }

        return false;
    }
}
