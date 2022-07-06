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

/**
 * Tab backup
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section_backup extends section_base {

    /** @var bool */
    protected $isregistered;
    /** @var \moodleform */
    protected $form;

    /**
     * Process tab actions
     */
    public function process() {
        global $PAGE, $DB;
        $action = optional_param('action', null, PARAM_ALPHANUMEXT);

        if ($action === 'startbackup' && confirm_sesskey()) {
            \tool_vault\site_backup::schedule_backup();
            redirect($PAGE->url);
        }

        $this->isregistered = \tool_vault\api::is_registered();
        if (!$this->isregistered) {
            $this->form = new \tool_vault\form\register_form($PAGE->url);
            if ($this->form->get_data()) {
                $this->form->process();
                redirect($PAGE->url);
            }
        }
    }

    /**
     * Is registered
     *
     * @return bool
     */
    public function get_is_registered() {
        return $this->isregistered;
    }

    /**
     * Form
     *
     * @return \moodleform
     */
    public function get_form(): \moodleform {
        return $this->form;
    }
}
