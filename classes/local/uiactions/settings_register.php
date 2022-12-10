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

namespace tool_vault\local\uiactions;

use core\notification;

/**
 * Register
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings_register extends base {

    /**
     * Process action
     */
    public function process() {
        require_sesskey();
        \tool_vault\api::register();
        notification::add('You are now registered. You can find your API key under the Settings tab',
            \core\output\notification::NOTIFY_SUCCESS);
        $returnurl = optional_param('returnurl', null, PARAM_LOCALURL);
        $returnurl = $returnurl ? new \moodle_url($returnurl) : settings::url();
        redirect($returnurl);
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