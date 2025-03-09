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

namespace tool_vault\local\uiactions;

use core\exception\moodle_exception;
use tool_vault\local\helpers\ui;

/**
 * Resume restore
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_resume extends base {

    /**
     * Process action
     */
    public function process() {
        parent::process();
        require_sesskey();
        $restoreid = required_param('id', PARAM_INT);
        $passphrase = optional_param('passphrase', '', PARAM_RAW);
        try {
            $restore = \tool_vault\site_restore::schedule(['resume' => true, 'passphrase' => $passphrase]);
        } catch (moodle_exception $e) {
            redirect(restore_details::url(['id' => $restoreid]), $e->getMessage(), 0, \core\output\notification::NOTIFY_ERROR);
        }
        redirect(ui::progressurl(['accesskey' => $restore->get_model()->accesskey]));
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
