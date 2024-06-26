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

namespace tool_vault\local\uiactions;

use tool_vault\api;
use tool_vault\local\exceptions\api_exception;
use tool_vault\local\helpers\ui;

/**
 * Schedule new dry-run
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_dryrun extends base {

    /**
     * Process action
     */
    public function process() {
        parent::process();
        require_sesskey();
        $backupkey = required_param('backupkey', PARAM_ALPHANUMEXT);
        $passphrase = optional_param('passphrase', '', PARAM_RAW);
        try {
            if (!api::are_restores_allowed()) {
                throw new \moodle_exception('error_restoresnotallowed', 'tool_vault');
            }
            api::validate_backup($backupkey, $passphrase);
        } catch (api_exception $e) {
            redirect(restore::url(), $e->getMessage(), 0, \core\output\notification::NOTIFY_ERROR);
        }
        $op = \tool_vault\site_restore_dryrun::schedule(['backupkey' => $backupkey, 'passphrase' => $passphrase]);
        $viewurl = restore_details::url(['id' => $op->get_model()->id]);
        redirect($viewurl);
    }

    /**
     * Get URL for the current action
     *
     * @param array $params
     */
    public static function url(array $params = []) {
        return parent::url($params + ['sesskey' => sesskey()]);
    }
}
