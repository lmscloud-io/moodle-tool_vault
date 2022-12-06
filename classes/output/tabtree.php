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

namespace tool_vault\output;

use tabobject;
use tool_vault\local\uiactions\backup;
use tool_vault\local\uiactions\overview;
use tool_vault\local\uiactions\restore;
use tool_vault\local\uiactions\settings;

/**
 * Tabs
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tabtree extends \tabtree {

    /**
     * Constructor
     */
    public function __construct() {
        $section = optional_param('section', null, PARAM_ALPHANUMEXT) ?? 'overview';
        $action = optional_param('action', null, PARAM_ALPHANUMEXT);
        $linkwhenselected = !empty($action);

        $tabs = [];
        $tabs[] = new tabobject('overview', overview::url(),
            get_string('taboverview', 'tool_vault'), '', $linkwhenselected);
        $tabs[] = new tabobject('backup', backup::url(),
            get_string('tabbackup', 'tool_vault'), '', $linkwhenselected);
        $tabs[] = new tabobject('restore', restore::url(),
            get_string('tabrestore', 'tool_vault'), '', $linkwhenselected);
        $tabs[] = new tabobject('settings', settings::url(),
            get_string('tabsettings', 'tool_vault'), '', $linkwhenselected);

        parent::__construct($tabs, $section);
    }
}
