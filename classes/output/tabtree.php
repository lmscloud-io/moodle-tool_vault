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

        $baseurl = new moodle_url('/admin/tool/vault/index.php');
        $tabs = [];
        $tabs[] = new tabobject('overview', new moodle_url($baseurl, ['section' => 'overview']),
            get_string('taboverview', 'tool_vault'));
        $tabs[] = new tabobject('backup', new moodle_url($baseurl, ['section' => 'backup']),
            get_string('tabbackup', 'tool_vault'));
        $tabs[] = new tabobject('restore', new moodle_url($baseurl, ['section' => 'restore']),
            get_string('tabrestore', 'tool_vault'));
        $tabs[] = new tabobject('settings', new moodle_url($baseurl, ['section' => 'settings']),
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
        $params = $this->currenttab === 'overview' ? [] : ['section' => $this->currenttab];
        return new moodle_url('/admin/tool/vault/index.php', $params);
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
