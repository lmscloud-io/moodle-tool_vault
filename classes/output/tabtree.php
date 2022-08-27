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

use moodle_url;
use tabobject;
use tool_vault\local\helpers\ui;

/**
 * Tabs
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tabtree extends \tabtree {
    /** @var string */
    protected $currenttab = null;

    /**
     * Constructor
     */
    public function __construct() {
        $section = optional_param('section', null, PARAM_ALPHANUMEXT);

        $tabs = [];
        $tabs[] = new tabobject('overview', ui::overviewurl(),
            get_string('taboverview', 'tool_vault'));
        $tabs[] = new tabobject('backup', ui::backupurl(),
            get_string('tabbackup', 'tool_vault'));
        $tabs[] = new tabobject('restore', ui::restoreurl(),
            get_string('tabrestore', 'tool_vault'));
        $tabs[] = new tabobject('settings', ui::settingsurl(),
            get_string('tabsettings', 'tool_vault'));

        $this->currenttab = 'overview';
        foreach ($tabs as $tab) {
            if ($section === $tab->link->param('section')) {
                $this->currenttab = $tab->id;
            }
        }

        parent::__construct($tabs, $this->currenttab);
    }

    /**
     * Current page URL
     *
     * @return moodle_url
     */
    public function get_url(): moodle_url {
        return ui::baseurl(['section' => $this->currenttab]);
    }

    /**
     * Shortname for the current tab
     *
     * @return string
     */
    public function get_current_tab(): string {
        return $this->currenttab;
    }

    /**
     * Instance of the section
     *
     * @return section_base
     */
    public function get_section(): section_base {
        if ($this->get_current_tab() === 'backup') {
            return new section_backup();
        } else if ($this->get_current_tab() === 'restore') {
            return new section_restore();
        } else if ($this->get_current_tab() === 'settings') {
            return new section_settings();
        } else {
            return new section_overview();
        }
    }
}
