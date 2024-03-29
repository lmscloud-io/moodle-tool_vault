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
    /**
     * Insert a number of records into the database
     *
     * @param string $tablename
     * @param array $fields array of field names
     * @param array $data 2-dimensional array of data to insert
     * @param logger $logger
     * @return void
     */
    public static function insert_records(string $tablename, array $fields, array &$data, logger $logger) {
        global $DB;

        if (empty($data)) {
            return;
        }
        $chunksize = 20;
        $chunks = array_chunk($data, $chunksize);
        foreach ($chunks as $chunk) {
            try {
                self::insert_chunk($tablename, $fields, $chunk);
            } catch (\Throwable $t) {
                $logger->add_to_log("- failed to insert chunk of records into table $tablename: ".
                    $t->getMessage(), constants::LOGLEVEL_WARNING);
                if ($t instanceof \dml_exception) {
                    $logger->add_to_log(shorten_text($t->debuginfo, 1000, true), constants::LOGLEVEL_VERBOSE);
                }
                if ($t instanceof \Exception) {
                    $logger->add_to_log($t->getTraceAsString(), constants::LOGLEVEL_VERBOSE);
                }
                self::insert_records_one_by_one($tablename, $fields, $chunk, $logger);
            }
        }
    }

    /**
     * Insert a chunk of records into the database
     *
     * @param string $tablename
     * @param array $fields
     * @param array $rows
     * @return void
     */
    protected static function insert_chunk(string $tablename, array $fields, array &$rows) {
        global $DB;
        $dbgen = $DB->get_manager()->generator;
        $fieldssql = '(' . implode(',', array_map(function ($f) use ($dbgen) {
            return $dbgen->getEncQuoted($f);
        }, $fields)) . ')';

        $valuerowsql = '('.implode(',', array_fill(0, count($fields), '?')).')';
        $valuessql = implode(',', array_fill(0, count($rows), $valuerowsql));

        $params = [];
        foreach ($rows as $dataobject) {
            foreach ($fields as $idx => $field) {
                $params[] = $dataobject[$idx];
            }
        }

        $sql = "INSERT INTO {".$tablename."} $fieldssql VALUES $valuessql";
        $DB->execute($sql, $params);
    }

    /**
     * Insert a chunk of records one by one
     *
     * @param string $tablename
     * @param array $fields
     * @param array $rows
     * @param logger $logger
     * @return void
     */
    protected static function insert_records_one_by_one(string $tablename, array $fields, array &$rows, logger $logger) {
        global $DB;
        foreach ($rows as &$row) {
            try {
                $entry = array_combine($fields, $row);
                $DB->insert_record_raw($tablename, $entry, false, true, true);
            } catch (\Throwable $t) {
                $logger->add_to_log("- failed to insert record with id {$entry['id']} into table $tablename: ".
                    $t->getMessage(), constants::LOGLEVEL_WARNING);
            }
        }
    }

}
