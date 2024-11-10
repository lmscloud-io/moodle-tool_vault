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

namespace tool_vault\local\restoreactions;

use tool_vault\local\helpers\siteinfo;
use tool_vault\site_restore;

/**
 * Remove old files action
 *
 * @package     tool_vault
 * @copyright   2023 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_existing_files extends restore_action {

    /**
     * Executes individual action
     *
     * @param site_restore $logger
     * @param string $stage
     * @return void
     */
    public function execute(site_restore $logger, string $stage) {
        if ($stage === restore_action::STAGE_BEFORE) {
            $logger->add_to_log('Making list of the old files...');
            $this->save_current_files_list($logger->get_model()->id);
            $logger->add_to_log('...done');
        } else if ($stage === restore_action::STAGE_AFTER_ALL) {
            $logger->add_to_log('Removing old files...');
            $this->remove_old_files($logger->get_model()->id);
            $logger->add_to_log('...done');
        }
    }

    /**
     * Save the list of the current files, so we can remove them in the end of restore if they are orphaned
     *
     * @param int $restoreid
     * @return void
     */
    protected function save_current_files_list(int $restoreid) {
        global $DB;
        list($sqlcomponent, $params) = $DB->get_in_or_equal(siteinfo::get_excluded_plugins_restore(), SQL_PARAMS_NAMED);
        $sql = "insert into {tool_vault_table_files_data} (restoreid, contenthash)
            select distinct $restoreid AS restoreid, contenthash
            from {files} where contenthash not in (
                select contenthash
                from {files}
                where component $sqlcomponent
            ) and referencefileid is null";
        $DB->delete_records_select('tool_vault_table_files_data', 'restoreid = ?', [$restoreid]);
        $DB->execute($sql, $params);
    }

    /**
     * For every file saved in the beginning of the restore check if the content needs to be removed
     *
     * @param int $restoreid
     * @return void
     */
    protected function remove_old_files(int $restoreid) {
        global $DB;
        $sql = "DELETE FROM {tool_vault_table_files_data}
            WHERE restoreid=$restoreid
            AND contenthash IN (SELECT contenthash FROM {files})";
        $DB->execute($sql);
        $fs = get_file_storage()->get_file_system();

        while ($records = $DB->get_records('tool_vault_table_files_data', ['restoreid' => $restoreid],
                'contenthash', 'id, contenthash', 0, 100)) {
            $x = [];
            foreach ($records as $record) {
                $fs->remove_file($record->contenthash);
                $x[] = $record->id;
            }
            $DB->delete_records_list('tool_vault_table_files_data', 'id', $x);
        }
    }
}
