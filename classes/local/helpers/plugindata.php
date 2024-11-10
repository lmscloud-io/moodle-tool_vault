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

/**
 * Helper class for excluding plugin data from various tables
 *
 * {@see \uninstall_plugin()}
 *
 * {@see \core_tag_area::uninstall()}
 * {@see \external_delete_descriptions()}
 * {@see \unset_all_config_for_plugin()}
 * {@see \message_provider_uninstall()}
 * {@see \capabilities_cleanup()}
 * {@see \file_storage::delete_component_files()}
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugindata {
    /** @var string Prefix for generated param names */
    const GENERATE_PARAM_PREFIX = 'vaultparam';

    /**
     * List of tables that contains plugin-related data that needs to be _deleted_
     *
     * In case when plugin is excluded from the backup - we exclude plugin-related data from these tables
     * during backup
     *
     * In case when plugin is preserved during restore - we exclude plugin-related data from these tables
     * that was restored from the backup
     *
     * See also {@see self::get_tables_with_possible_plugin_data_to_preserve()} for the tables where
     * data needs to be _preserved_ during restore
     *
     * @return array
     */
    public static function get_tables_with_possible_plugin_data(): array {
        return array_unique(array_merge(
            self::tables_with_component_field(),
            array_map(function($r) {
                return $r[0];
            }, self::tables_with_dependent_component_field()),
            [
                'config',
                'config_plugins',
                'user_preferences',
            ]
        ));
    }

    /**
     * Following tables have 'component' field referring to moodle plugin/component
     *
     * @return string[]
     */
    protected static function tables_with_component_field(): array {
        global $CFG;
        // We don't take into account exceptions for 'mod' plugin type because this type is never going to be supported.
        $tables = [
            'external_services',
            'external_functions',
            'event',
            'task_adhoc',
            'task_scheduled',
            'messageinbound_handlers',
            'log_display',
            'tag_area',
            'tag_coll',
            'tag_instance',
            'message_providers',
            'capabilities',
            'files',
        ];
        if ((int)($CFG->branch) < 39) {
            $tables = array_diff($tables, ['event']); // Component field was added in 3.9.
        }
        return $tables;
    }

    /**
     * Following tables have data dependent on the tables with 'component' field
     *
     * @return array[]
     */
    protected static function tables_with_dependent_component_field(): array {
        // We don't take into account exceptions for 'mod' plugin type because this type is never going to be supported.
        return [
            ['messageinbound_datakeys', 'handler', 'messageinbound_handlers', 'id'],
            ['external_tokens', 'externalserviceid', 'external_services', 'id'],
            ['external_services_users', 'externalserviceid', 'external_services', 'id'],
            ['external_services_functions', 'functionname', 'external_services', 'name'],
            ['role_capabilities', 'capability', 'capabilities', 'name'],
        ];
    }

    /**
     * Generates unique parameter name that must be used in generated SQL
     *
     * @return string
     */
    public static function generate_param_name(): string {
        static $paramcount = 0;
        return static::GENERATE_PARAM_PREFIX . ($paramcount++);
    }

    /**
     * Tables that have plugin-related data but do not have 'component' field
     *
     * @param string $tablename
     * @param string $plugin
     * @return array|null
     */
    protected static function get_special_sql_for_table(string $tablename, string $plugin) {
        global $DB;

        if ($tablename === 'config_plugins') {
            $p = self::generate_param_name();
            $p1 = self::generate_param_name();
            $p2 = self::generate_param_name();
            $params = [];

            $sql1 = "plugin = :{$p}";
            $params[$p] = $plugin;
            $sql2 = "plugin = :{$p1} AND ".$DB->sql_like('name', ":{$p2}", false);
            $params[$p1] = 'message';
            $params[$p2] = "%_provider_{$plugin}_%";
            return ["({$sql1}) OR ({$sql2})", $params];
        }
        if ($tablename === 'user_preferences') {
            $p = self::generate_param_name();
            $sql = $DB->sql_like('name', ":{$p}", false);
            $params = [$p => "message_provider_{$plugin}_%"];
            return [$sql, $params];
        }
        if ($tablename === 'config') {
            $p = self::generate_param_name();
            $sql = $DB->sql_like('name', ":{$p}", true, true, false, '|');
            $params = [$p => $DB->sql_like_escape($plugin.'_', '|') . '%'];
            return [$sql, $params];
        }

        return null;
    }

    /**
     * Forms SQL and params to use in $DB->get_records_select() to select plugin-related data
     *
     * @param string $tablename
     * @param array $plugins
     * @param bool $negated - if true selects data NOT related to given plugins
     * @return array [$sql, $params]
     */
    public static function get_sql_for_plugins_data_in_table(string $tablename, array $plugins, bool $negated = false): array {
        global $DB;
        $params = [];
        $sqls = [];

        if ($plugins) {
            if (in_array($tablename, self::tables_with_component_field())) {
                $p = self::generate_param_name();
                list($s, $pp) = $DB->get_in_or_equal($plugins, SQL_PARAMS_NAMED, $p.'_', !$negated);
                $sqls[] = $negated ? "(component IS NULL OR component $s)" : "component $s";
                $params += $pp;
            }
            foreach (self::tables_with_dependent_component_field() as $entry) {
                if ($entry[0] === $tablename) {
                    $p = self::generate_param_name();
                    list($s, $pp) = $DB->get_in_or_equal($plugins, SQL_PARAMS_NAMED, $p.'_');
                    $sqls[] = "{$entry[1]} ".($negated ? ' NOT ' : '').
                        "IN (SELECT {$entry[3]} FROM {{$entry[2]}} WHERE component {$s})";
                    $params += $pp;
                }
            }
            foreach ($plugins as $plugin) {
                if ($res = self::get_special_sql_for_table($tablename, $plugin)) {
                    $sqls[] = $negated ? "(NOT ({$res[0]}))" : $res[0];
                    $params += $res[1];
                }
            }
        }

        if (!$sqls) {
            // This table does not have plugin-related data or there are no plugins selected.
            return [$negated ? '' : '1=0', []];
        }

        if ($negated) {
            $sql = join(' AND ', $sqls);
        } else {
            $sql = '(' . join(' OR ', $sqls) . ')';
        }

        return [$sql, $params];
    }

    /**
     * List of tables that contains plugin-related data that needs to be _preserved_
     *
     * This list is different from {@see self::get_tables_with_possible_plugin_data()} because
     * users and roles ids will be different on the restored site and some associations are never
     * possible to preserve.
     *
     * @return array
     */
    public static function get_tables_with_possible_plugin_data_to_preserve(): array {
        return array_diff(
            self::get_tables_with_possible_plugin_data(),
            // Exclude the tables that link to the data from the backed up site (users, roles).
            [
                'event', // Contains ids of course, category, user, etc.
                'tag_instance', // Contains tag id and userid.
                'user_preferences',
                'role_capabilities', // Contains role id that will not match different after restore.
                'external_tokens',
                'external_services_users',
                'messageinbound_datakeys', // Generated for users or forum posts.
            ]);
    }

    /**
     * When we preserve data during restore we preserve less than what we exclude during backup
     *
     * For example, data associated with userid, courseid, tagid, etc can not be preserved because
     * users/courses/tags with these ids will not be present or will be different on the restored site
     *
     * @param string $tablename
     * @param array $plugins
     * @param int $substituteuserid
     * @return array
     */
    public static function get_sql_for_plugins_data_in_table_to_preserve(string $tablename, array $plugins,
                                                                         int $substituteuserid): array {
        global $DB;
        $fields = '*';
        list($sql, $params) = self::get_sql_for_plugins_data_in_table($tablename, $plugins);

        if ($sql !== '1=0') {
            if (in_array($tablename, ['task_adhoc'])) {
                $sql .= ' AND userid IS NULL';
            }
            if (in_array($tablename, ['files'])) {
                $sql .= ' AND referencefileid IS NULL';
                // Replace userid with $substituteuserid in the fields.
                $cols = array_map(function(\database_column_info $columninfo) use ($substituteuserid) {
                    $name = strtolower($columninfo->name);
                    return $name === 'userid' ? "{$substituteuserid} AS userid" : $name;
                    // Mdlcode-disable-next-line cannot-parse-db-tablename.
                }, $DB->get_columns($tablename));
                $fields = join(', ', $cols);
            }
        }

        return [$sql, $params, $fields];
    }
}
