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

use html_writer;
use moodle_url;
use plugin_renderer_base;
use tool_vault\api;
use tool_vault\form\general_settings_form;
use tool_vault\local\models\remote_backup;

/**
 * Plugin renderer
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Renderer for tab backups
     *
     * @param section_backup $section
     * @return string
     */
    public function render_section_backup(section_backup $section) {
        $action = optional_param('action', null, PARAM_ALPHANUMEXT);
        $id = optional_param('id', null, PARAM_INT);

        if ($action === 'details' && $id && ($backup = \tool_vault\site_backup::get_backup_by_id($id))) {
            $data = (new backup_details($backup))->export_for_template($this);
            return $this->render_from_template('tool_vault/backup_details', $data);
        }

        return
            $this->render_from_template('tool_vault/section_backup',
                $section->export_for_template($this));
    }

    /**
     * Renderer for tab restore
     *
     * @param section_restore $section
     * @return string
     */
    public function render_section_restore(section_restore $section) {
        global $DB;
        $output = '';
        $action = optional_param('action', null, PARAM_ALPHANUMEXT);
        $output .= $this->heading(get_string('siterestore', 'tool_vault'), 3);

        if (!api::is_registered()) {
            $output .= (new general_settings_form(false, false))->render();
        }

        $records = $DB->get_records('tool_vault_restores', [], 'timecreated desc');
        // @codingStandardsIgnoreLine
        $output .= html_writer::tag('pre', s(print_r($records, true)), array('class' => 'notifytiny'));

        if (api::is_registered()) {
            $output .= $this->heading('Remote backups', 4); // TODO string.
            if ($action === 'findbackups' && confirm_sesskey()) {
                $backups = \tool_vault\api::get_remote_backups();
                $output .= $this->display_remote_backups($backups);
            } else {
                $link = new moodle_url($this->page->url,
                    ['section' => 'restore', 'action' => 'findbackups', 'sesskey' => sesskey()]);
                $output .= html_writer::div(html_writer::link($link, 'Search for available backups'));
            }
        }
        return $output;
    }

    /**
     * Display remote backups
     *
     * @param remote_backup[] $backups
     * @return string
     */
    protected function display_remote_backups(array $backups): string {
        $output = '';
        foreach ($backups as $backup) {
            $started = userdate($backup->timecreated, get_string('strftimedatetimeshort', 'langconfig'));
            $output .= html_writer::div("Backup: {$backup->backupkey} - {$backup->status} - $started - {$backup->info['wwwroot']}");
            if ($backup->status === \tool_vault\constants::STATUS_FINISHED) {
                $output .= $this->single_button(new moodle_url($this->page->url,
                    ['section' => 'restore', 'action' => 'restore',
                        'backupkey' => $backup->backupkey, 'sesskey' => sesskey()]),
                    'Restore this backup', 'get');
            }
        }
        return $output;
    }

    /**
     * Renderer for tab settings
     *
     * @param section_settings $section
     * @return string
     */
    public function render_section_settings(section_settings $section) {
        $output = '';
        if ($form = $section->get_general_form()) {
            $output .= $this->heading('General', 3);
            $output .= $form->render();
        }
        if ($form = $section->get_backup_form()) {
            $output .= $this->heading('Backup settings', 3);
            $output .= $form->render();
        }
        if ($form = $section->get_restore_form()) {
            $output .= $this->heading('Restore settings', 3);
            $output .= $form->render();
        }
        return $output;
    }

    /**
     * Renderer for tab overview
     *
     * @param section_overview $section
     * @return string
     */
    public function render_section_overview(section_overview $section) {
        $rv = '';
        $action = optional_param('action', null, PARAM_ALPHANUMEXT);
        $id = optional_param('id', null, PARAM_INT);

        if ($action === 'details' && $id && ($check = \tool_vault\local\checks\base::load($id)) && $check->has_details()) {
            $data = (new check_display($check, true))->export_for_template($this);
            $data['details'] = $check->detailed_report();
            return $this->render_from_template('tool_vault/check_details', $data);
        }

        foreach (\tool_vault\local\checks\base::get_all_checks() as $check) {
            $data = (new check_display($check))->export_for_template($this);
            $rv .= $this->render_from_template('tool_vault/check_summary', $data);
        }

        return $rv;
    }
}
