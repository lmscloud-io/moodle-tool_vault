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

use tool_vault\form\backup_settings_form;

/**
 * Tab settings
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings_backup extends base {
    /** @var backup_settings_form */
    protected $form;

    /**
     * Process tab actions
     */
    public function process() {
        $returnurl = $this->get_return_url() ?? settings::url();
        $form = $this->get_form();
        if ($form->is_cancelled()) {
            redirect($returnurl);
        } else if ($form->get_data()) {
            $form->process();
            redirect($returnurl);
        }
    }

    /**
     * Display
     *
     * @param \renderer_base $output
     * @return string
     */
    public function display(\renderer_base $output) {
        return $output->heading(get_string('backupsettingsheader', 'tool_vault'), 3) .
            $this->get_form()->render();
    }

    /**
     * Form
     *
     * @return backup_settings_form
     */
    public function get_form(): backup_settings_form {
        if ($this->form === null) {
            $this->form = new backup_settings_form(true);
        }
        return $this->form;
    }

}
