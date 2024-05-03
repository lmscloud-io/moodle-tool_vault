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

use tool_vault\local\checks\check_base;

/**
 * Schedule new backup pre-check
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_newcheck extends base {

    /**
     * Process action
     */
    public function process() {
        parent::process();
        require_sesskey();
        $check = check_base::schedule(['type' => required_param('type', PARAM_ALPHANUMEXT)]);
        if (optional_param('detailed', false, PARAM_BOOL)) {
            redirect(backup_checkreport::url(['id' => $check->get_model()->id]));
        }
        redirect(backup::url());
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
