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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_vault\local\models;

use tool_vault\constants;

/**
 * Model for restore dry-run (checks only)
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dryrun_model extends restore_base_model {
    /** @var string */
    protected static $defaulttype = 'dryrun';

    /**
     * Get last record for a given backupkey, if exists
     *
     * @param string $backupkey
     * @return static|null
     */
    public static function get_last_dry_run(string $backupkey): ?self {
        $records = self::get_records_select('type = ? AND backupkey = ?',
            [static::$defaulttype, $backupkey]);
        return $records ? reset($records) : null;
    }
}
