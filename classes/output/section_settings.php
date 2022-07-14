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

use core\notification;
use tool_vault\api;
use tool_vault\form\backup_settings_form;
use tool_vault\form\general_settings_form;
use tool_vault\form\restore_settings_form;

/**
 * Tab settings
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section_settings extends section_base {

    /** @var general_settings_form */
    protected $generalform;
    /** @var backup_settings_form */
    protected $backupform;
    /** @var restore_settings_form */
    protected $restoreform;

    /**
     * Requested action
     *
     * @return string|null
     */
    protected function get_action(): ?string {
        return optional_param('action', null, PARAM_ALPHANUMEXT);
    }

    /**
     * Process tab actions
     */
    public function process() {
        global $PAGE;

        $returnurl = optional_param('returnurl', null, PARAM_LOCALURL);
        $returnurl = $returnurl ? new \moodle_url($returnurl) : $PAGE->url;
        if ($this->get_action() === 'forgetapikey' && confirm_sesskey()) {
            api::store_config('apikey', null);
            redirect($returnurl);
        } else if ($this->get_action() === 'register' && confirm_sesskey()) {
            \tool_vault\api::register();
            notification::add('You are now registered. You can find your API key under the Settings tab',
                \core\output\notification::NOTIFY_SUCCESS);
            redirect($returnurl);
        }

        $form = $this->get_general_form() ?: ($this->get_backup_form() ?: $this->get_restore_form());

        if ($form && $form->is_cancelled()) {
            redirect($returnurl);
        } else if ($form && $form->get_data()) {
            $form->process();
            redirect($returnurl);
        }
    }

    /**
     * Backup settings form
     *
     * @return general_settings_form
     */
    public function get_general_form(): ?general_settings_form {
        $editable = $this->get_action() === 'general';
        if ($this->get_action() && !$editable) {
            return null;
        } else if ($this->generalform === null) {
            $this->generalform = new general_settings_form($editable);
        }
        return $this->generalform;
    }

    /**
     * Backup settings form
     *
     * @return backup_settings_form
     */
    public function get_backup_form(): ?backup_settings_form {
        global $PAGE;
        $editable = $this->get_action() === 'backup';
        if ($this->get_action() && !$editable) {
            return null;
        } else if ($this->backupform === null) {
            $this->backupform = new backup_settings_form($PAGE->url, $editable);
        }
        return $this->backupform;
    }

    /**
     * Restore settings form
     *
     * @return restore_settings_form
     */
    public function get_restore_form(): ?restore_settings_form {
        global $PAGE;
        $editable = $this->get_action() === 'restore';
        if ($this->get_action() && !$editable) {
            return null;
        } else if ($this->restoreform === null) {
            $this->restoreform = new restore_settings_form($PAGE->url, $editable);
        }
        return $this->restoreform;
    }
}
