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

namespace tool_vault\local\helpers;

use tool_vault\constants;
use tool_vault\local\logger;

/**
 * Class dbops
 *
 * @package    tool_vault
 * @copyright  2024 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dbops {
    /** @var int|null|false stores the cached value for max_allowed_packet (null - no limit, false - unknown) */
    protected static $maxallowedpacket = false;

    /**
     * Returns the size of max_allowed_packet variable for mysql or null for postgres
     *
     * @return int|null
     */
    public static function get_max_allowed_packet() {
        global $DB, $CFG;
        if (self::$maxallowedpacket === false) {
            if ($DB->get_dbfamily() === 'mysql') {
                $sql = "SHOW VARIABLES LIKE 'max_allowed_packet'";
                $res = $DB->get_record_sql($sql);
                $value = $res ? (int)$res->value : 4 * 1024 * 1024;
                self::$maxallowedpacket = (int)floor($value / 1024) * 1024;
            } else {
                self::$maxallowedpacket = null;
            }
        }
        return self::$maxallowedpacket;
    }

    /**
     * Manipulates the static cache for tests
     *
     * @param int|false|null $newvalue
     */
    public static function set_max_allowed_packet($newvalue) {
        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
            self::$maxallowedpacket = $newvalue;
        } else {
            debugging('Function '.__FUNCTION__.' can not be called outside of unittests', DEBUG_DEVELOPER);
        }
    }

    /**
     * Insert a number of records into the database
     *
     * @param string $tablename
     * @param array $fields array of field names
     * @param array $data 2-dimensional array of data to insert
     * @param logger $logger
     * @return void
     */
    public static function insert_records($tablename, array $fields, array &$data, logger $logger) {
        global $DB;

        if (empty($data)) {
            return;
        }
        $packetsizes = self::calculate_row_packet_sizes(count($fields), $data);
        $nofrows = count($data);
        $startrow = 0;
        while ($startrow < $nofrows) {
            $endrow = self::prepare_next_chunk($tablename, $fields, $packetsizes, $startrow);
            try {
                self::insert_chunk($tablename, $fields, $data, $startrow, $endrow);
            } catch (\Throwable $t) {
                $logger->add_to_log("- failed to insert chunk of records into table $tablename: ".
                    $t->getMessage(), constants::LOGLEVEL_WARNING);
                if ($t instanceof \dml_exception) {
                    $logger->add_to_log(shorten_text($t->debuginfo, 1000, true), constants::LOGLEVEL_VERBOSE);
                } else if ($t instanceof \Exception) {
                    $logger->add_to_log(shorten_text($t->getTraceAsString(), 1000, true), constants::LOGLEVEL_VERBOSE);
                }
                self::insert_records_one_by_one($tablename, $fields, $data, $startrow, $endrow, $logger);
            }
            $startrow = $endrow;
        }
    }

    /**
     * Calculates how many records can be inserted in the next chunk
     *
     * @param string $tablename
     * @param array $fields
     * @param array $packetsizes size in bytes of each row in $data if it was added to the SQL query
     * @param int $startrow
     * @return int index of the row after the last row in the chunk
     */
    protected static function prepare_next_chunk($tablename, array $fields, array &$packetsizes, $startrow) {
        global $DB;
        $maxendrow = count($packetsizes);

        $cfg = $DB->export_dbconfig();
        if (!empty($cfg->dboptions) && !empty($cfg->dboptions['bulkinsertsize']) && (int)$cfg->dboptions['bulkinsertsize'] > 0) {
            $maxendrow = min($maxendrow, $startrow + (int)$cfg->dboptions['bulkinsertsize']);
        }

        if ($DB->get_dbfamily() === 'postgres') {
            // Can not pass more than 65535 parameters in one query to postgres.
            $maxrows = floor(65535 / count($fields));
            $maxendrow = min($maxendrow, $startrow + $maxrows);
        }

        if (!self::get_max_allowed_packet()) {
            return $maxendrow;
        }

        list($sql, $params) = $DB->fix_sql_params(self::prepare_insert_sql($tablename, $fields, 0), []);
        $baselen = strlen($sql);
        $len = $baselen + $packetsizes[$startrow];
        for ($irow = $startrow + 1; $irow < $maxendrow; $irow++) {
            $len += $packetsizes[$irow] + 1;
            if ($len > self::get_max_allowed_packet()) {
                return $irow;
            }
        }
        return $maxendrow;
    }

    /**
     * Calculates the size in bytes of SQL query fragment for each of the data rows (only for mysql)
     *
     * @param int $noffields
     * @param array $data
     * @return int[] size in bytes of each row in $data if it was added to the SQL query
     */
    protected static function calculate_row_packet_sizes($noffields, array &$data) {
        global $DB;
        $nofrows = count($data);
        if (!self::get_max_allowed_packet()) {
            // If there is no packet restriction, it is not needed.
            return array_fill(0, $nofrows, 0);
        }

        // Function mysqli_native_moodle_database::emulate_bound_params() is protected but we need to call it
        // to get the exact length of the query.
        $reflector = new \ReflectionObject($DB);
        $method = $reflector->getMethod('emulate_bound_params');
        $method->setAccessible(true);

        $res = [];
        $valuerowsql = self::prepare_value_sql($noffields, 1);
        for ($i = 0; $i < $nofrows; $i++) {
            $res[$i] = strlen($method->invoke($DB, $valuerowsql, $data[$i]));
        }
        return $res;
    }

    /**
     * Prepares SQL for inserting values
     *
     * @param int $noffields
     * @param int $nofrows
     * @return string
     */
    protected static function prepare_value_sql($noffields, $nofrows) {
        if (!$nofrows) {
            return '';
        }
        $valuerowsql = '('.implode(',', array_fill(0, $noffields, '?')).')';
        return implode(',', array_fill(0, $nofrows, $valuerowsql));
    }

    /**
     * Prepares SQL for the whole INSERT statement
     *
     * @param string $tablename
     * @param array $fields
     * @param int $nofrows
     * @return string
     */
    protected static function prepare_insert_sql($tablename, array $fields, $nofrows) {
        global $DB;
        $dbgen = $DB->get_manager()->generator;
        $fieldssql = '(' . implode(',', array_map(function ($f) use ($dbgen) {
            return $dbgen->getEncQuoted($f);
        }, $fields)) . ')';
        $valuessql = self::prepare_value_sql(count($fields), $nofrows);
        return "INSERT INTO {".$tablename."} $fieldssql VALUES $valuessql";
    }

    /**
     * Insert a chunk of records into the database
     *
     * @param string $tablename
     * @param array $fields
     * @param array $rows
     * @param int $startrow
     * @param int $endrow
     * @return void
     */
    protected static function insert_chunk($tablename, array $fields, array &$rows, $startrow, $endrow) {
        global $DB;

        $sql = self::prepare_insert_sql($tablename, $fields, $endrow - $startrow);

        $params = [];
        $noffields = count($fields);
        for ($i = $startrow; $i < $endrow; $i++) {
            for ($j = 0; $j < $noffields; $j++) {
                $params[] = $rows[$i][$j];
            }
        }

        $DB->execute($sql, $params);
    }

    /**
     * Insert a chunk of records one by one
     *
     * @param string $tablename
     * @param array $fields
     * @param array $rows
     * @param int $startrow
     * @param int $endrow
     * @param logger $logger
     * @return void
     */
    protected static function insert_records_one_by_one($tablename, array $fields, array &$rows, $startrow,
            int $endrow, logger $logger) {
        global $DB;
        for ($i = $startrow; $i < $endrow; $i++) {
            $row = $rows[$i];
            try {
                $entry = array_combine($fields, $row);
                // Mdlcode-disable-next-line cannot-parse-db-tablename.
                $DB->insert_record_raw($tablename, $entry, false, true, true);
            } catch (\Throwable $t) {
                $logger->add_to_log("- failed to insert record with id {$entry['id']} into table $tablename: ".
                    $t->getMessage(), constants::LOGLEVEL_WARNING);
            }
        }
    }

}
