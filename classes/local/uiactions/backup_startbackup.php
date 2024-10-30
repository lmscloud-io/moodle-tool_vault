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
use tool_vault\local\helpers\ui;

/**
 * Schedule new backup
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_startbackup extends base {
    /** @var bool */
    protected $withsesskey = true;

    /**
     * Process action
     */
    public function process() {
        parent::process();
        require_sesskey();
        $backup = \tool_vault\site_backup::schedule([
            'passphrase' => optional_param('passphrase', null, PARAM_RAW),
            'description' => optional_param('description', null, PARAM_NOTAGS),
            'bucket' => optional_param('bucket', '', PARAM_TEXT),
            'expiredays' => optional_param('expiredays', 0, PARAM_INT),
            'backupplugincode' => api::allow_backup_plugincode() >= 0 ?
                optional_param('backupplugincode', 0, PARAM_BOOL) : 0,
        ]);
        redirect(ui::progressurl(['accesskey' => $backup->get_model()->accesskey]));
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
