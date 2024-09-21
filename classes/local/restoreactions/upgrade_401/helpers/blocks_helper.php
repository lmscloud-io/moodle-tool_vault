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

namespace tool_vault\local\restoreactions\upgrade_401\helpers;

/**
 * Class blocks_helper
 *
 * @package    tool_vault
 * @copyright  2024 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class blocks_helper {

    /**
     * Remove all instances of a block on pages of the specified pagetypepattern.
     *
     * Note: This is intended as a helper to add blocks to all instances of the standard my-page. It will only work where
     * the subpagepattern is a string representation of an integer. If there are any string values this will not work.
     *
     * @param string $blockname The block name, without the block_ frankenstyle component
     * @param string $pagename The type of my-page to match
     * @param string $pagetypepattern This is typically used on the 'my-index'
     */
    public static function upgrade_block_delete_instances(
        string $blockname,
        string $pagename,
        string $pagetypepattern
    ): void {
        global $DB;

        $deleteblockinstances = function (string $instanceselect, array $instanceparams) use ($DB) {
            $deletesql = <<<EOF
                SELECT c.id AS cid
                FROM {context} c
                JOIN {block_instances} bi ON bi.id = c.instanceid AND c.contextlevel = :contextlevel
                WHERE {$instanceselect}
            EOF;
            $DB->delete_records_subquery('context', 'id', 'cid', $deletesql, array_merge($instanceparams, [
                'contextlevel' => CONTEXT_BLOCK,
            ]));

            $deletesql = <<<EOF
                SELECT bp.id AS bpid
                FROM {block_positions} bp
                JOIN {block_instances} bi ON bi.id = bp.blockinstanceid
                WHERE {$instanceselect}
            EOF;
            $DB->delete_records_subquery('block_positions', 'id', 'bpid', $deletesql, $instanceparams);

            $blockhidden = $DB->sql_concat("'block'", 'bi.id', "'hidden'");
            $blockdocked = $DB->sql_concat("'docked_block_instance_'", 'bi.id');
            $deletesql = <<<EOF
                SELECT p.id AS pid
                FROM {user_preferences} p
                JOIN {block_instances} bi ON p.name IN ({$blockhidden}, {$blockdocked})
                WHERE {$instanceselect}
            EOF;
            $DB->delete_records_subquery('user_preferences', 'id', 'pid', $deletesql, $instanceparams);

            $deletesql = <<<EOF
                SELECT bi.id AS bid
                FROM {block_instances} bi
                WHERE {$instanceselect}
            EOF;
            $DB->delete_records_subquery('block_instances', 'id', 'bid', $deletesql, $instanceparams);
        };

        // Delete the default indexsys version of the block.
        $subpagepattern = $DB->get_record('my_pages', [
            'userid' => null,
            'name' => $pagename,
            'private' => 1 /* MY_PAGE_PRIVATE */,
        ], 'id', IGNORE_MULTIPLE)->id;

        $instanceselect = <<<EOF
                blockname = :blockname
            AND pagetypepattern = :pagetypepattern
            AND subpagepattern = :subpagepattern
        EOF;

        $params = [
            'blockname' => $blockname,
            'pagetypepattern' => $pagetypepattern,
            'subpagepattern' => $subpagepattern,
        ];
        $deleteblockinstances($instanceselect, $params);

        // The subpagepattern is a string.
        // In all core blocks it contains a string represnetation of an integer, but it is theoretically possible for a
        // community block to do something different.
        // This function is not suited to those cases.
        $subpagepattern = $DB->sql_cast_char2int('bi.subpagepattern');

        // Look for any and all instances of the block in customised /my pages.
        $subpageempty = $DB->sql_isnotempty('block_instances', 'bi.subpagepattern', true, false);
        $instanceselect = <<<EOF
            bi.id IN (
                SELECT * FROM (
                    SELECT bi.id
                    FROM {my_pages} mp
                    JOIN {block_instances} bi
                            ON bi.blockname = :blockname
                        AND bi.subpagepattern IS NOT NULL AND {$subpageempty}
                        AND bi.pagetypepattern = :pagetypepattern
                        AND {$subpagepattern} = mp.id
                    WHERE mp.private = :private
                    AND mp.name = :pagename
                ) bid
            )
        EOF;

        $params = [
            'blockname' => $blockname,
            'pagetypepattern' => $pagetypepattern,
            'pagename' => $pagename,
            'private' => 1 /* MY_PAGE_PRIVATE */,
        ];

        $deleteblockinstances($instanceselect, $params);
    }

    /**
     * Update the block instance parentcontext to point to the correct user context id for the specified block on a my page.
     *
     * @param string $blockname
     * @param string $pagename
     * @param string $pagetypepattern
     */
    public static function upgrade_block_set_my_user_parent_context(
        string $blockname,
        string $pagename,
        string $pagetypepattern
    ): void {
        global $DB;

        $subpagepattern = $DB->sql_cast_char2int('bi.subpagepattern');
        // Look for any and all instances of the block in customised /my pages.
        $subpageempty = $DB->sql_isnotempty('block_instances', 'bi.subpagepattern', true, false);

        $dbman = $DB->get_manager();
        // Mdlcode-disable-next-line unknown-db-tablename.
        $temptablename = 'block_instance_context';
        $xmldbtable = new \xmldb_table($temptablename);
        $xmldbtable->add_field('instanceid', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, null);
        $xmldbtable->add_field('contextid', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, null);
        $xmldbtable->add_key('primary', XMLDB_KEY_PRIMARY, ['instanceid']);
        $dbman->create_temp_table($xmldbtable);

        $sql = <<<EOF
            INSERT INTO {block_instance_context} (
                instanceid,
                contextid
            ) SELECT
                bi.id as instanceid,
                c.id as contextid
            FROM {my_pages} mp
            JOIN {context} c ON c.instanceid = mp.userid AND c.contextlevel = :contextuser
            JOIN {block_instances} bi
                    ON bi.blockname = :blockname
                AND bi.subpagepattern IS NOT NULL AND {$subpageempty}
                AND bi.pagetypepattern = :pagetypepattern
                AND {$subpagepattern} = mp.id
            WHERE mp.name = :pagename AND bi.parentcontextid <> c.id
        EOF;

        $DB->execute($sql, [
            'blockname' => $blockname,
            'pagetypepattern' => $pagetypepattern,
            'contextuser' => CONTEXT_USER,
            'pagename' => $pagename,
        ]);

        $dbfamily = $DB->get_dbfamily();
        if ($dbfamily === 'mysql') {
            // MariaDB and MySQL.
            $sql = <<<EOF
                UPDATE {block_instances} bi, {block_instance_context} bic
                SET bi.parentcontextid = bic.contextid
                WHERE bi.id = bic.instanceid
            EOF;
        } else if ($dbfamily === 'oracle') {
            $sql = <<<EOF
                UPDATE {block_instances} bi
                SET (bi.parentcontextid) = (
                    SELECT bic.contextid
                    FROM {block_instance_context} bic
                    WHERE bic.instanceid = bi.id
                ) WHERE EXISTS (
                    SELECT 'x'
                    FROM {block_instance_context} bic
                    WHERE bic.instanceid = bi.id
                )
            EOF;
        } else {
            // Postgres and sqlsrv.
            $sql = <<<EOF
                UPDATE {block_instances}
                SET parentcontextid = bic.contextid
                FROM {block_instance_context} bic
                WHERE {block_instances}.id = bic.instanceid
            EOF;
        }

        $DB->execute($sql);

        $dbman->drop_table($xmldbtable);
    }

    /**
     * Update all instances of a block shown on a pagetype to a new default region, adding missing block instances where
     * none is found.
     *
     * Note: This is intended as a helper to add blocks to all instances of the standard my-page. It will only work where
     * the subpagepattern is a string representation of an integer. If there are any string values this will not work.
     *
     * @param string $blockname The block name, without the block_ frankenstyle component
     * @param string $pagename The type of my-page to match
     * @param string $pagetypepattern The page type pattern to match for the block
     * @param string $newdefaultregion The new region to set
     */
    public static function upgrade_block_set_defaultregion(
        string $blockname,
        string $pagename,
        string $pagetypepattern,
        string $newdefaultregion
    ): void {
        global $DB;

        // The subpagepattern is a string.
        // In all core blocks it contains a string represnetation of an integer, but it is theoretically possible for a
        // community block to do something different.
        // This function is not suited to those cases.
        $subpagepattern = $DB->sql_cast_char2int('bi.subpagepattern');
        $subpageempty = $DB->sql_isnotempty('block_instances', 'bi.subpagepattern', true, false);

        // If a subquery returns any NULL then the NOT IN returns no results at all.
        // By adding a join in the inner select on my_pages we remove any possible nulls and prevent any need for
        // additional casting to filter out the nulls.
        $sql = <<<EOF
            INSERT INTO {block_instances} (
                blockname,
                parentcontextid,
                showinsubcontexts,
                pagetypepattern,
                subpagepattern,
                defaultregion,
                defaultweight,
                timecreated,
                timemodified
            ) SELECT
                :selectblockname AS blockname,
                c.id AS parentcontextid,
                0 AS showinsubcontexts,
                :selectpagetypepattern AS pagetypepattern,
                mp.id AS subpagepattern,
                :selectdefaultregion AS defaultregion,
                0 AS defaultweight,
                :selecttimecreated AS timecreated,
                :selecttimemodified AS timemodified
            FROM {my_pages} mp
            JOIN {context} c ON c.instanceid = mp.userid AND c.contextlevel = :contextuser
            WHERE mp.id NOT IN (
                SELECT mpi.id FROM {my_pages} mpi
                JOIN {block_instances} bi
                        ON bi.blockname = :blockname
                    AND bi.subpagepattern IS NOT NULL AND {$subpageempty}
                    AND bi.pagetypepattern = :pagetypepattern
                    AND {$subpagepattern} = mpi.id
            )
            AND mp.private = 1
            AND mp.name = :pagename
        EOF;

        $DB->execute($sql, [
            'selectblockname' => $blockname,
            'contextuser' => CONTEXT_USER,
            'selectpagetypepattern' => $pagetypepattern,
            'selectdefaultregion' => $newdefaultregion,
            'selecttimecreated' => time(),
            'selecttimemodified' => time(),
            'pagetypepattern' => $pagetypepattern,
            'blockname' => $blockname,
            'pagename' => $pagename,
        ]);

        // Update the existing instances.
        $sql = <<<EOF
            UPDATE {block_instances}
            SET defaultregion = :newdefaultregion
            WHERE id IN (
                SELECT * FROM (
                    SELECT bi.id
                    FROM {my_pages} mp
                    JOIN {block_instances} bi
                            ON bi.blockname = :blockname
                        AND bi.subpagepattern IS NOT NULL AND {$subpageempty}
                        AND bi.pagetypepattern = :pagetypepattern
                        AND {$subpagepattern} = mp.id
                    WHERE mp.private = 1
                    AND mp.name = :pagename
                    AND bi.defaultregion <> :existingnewdefaultregion
                ) bid
            )
        EOF;

        $DB->execute($sql, [
            'newdefaultregion' => $newdefaultregion,
            'pagetypepattern' => $pagetypepattern,
            'blockname' => $blockname,
            'existingnewdefaultregion' => $newdefaultregion,
            'pagename' => $pagename,
        ]);

        // Note: This can be time consuming!
        \context_helper::create_instances(CONTEXT_BLOCK);
    }
}
