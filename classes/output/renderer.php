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
        $output = '';

        if ($section->get_is_registered()) {
            $output .= html_writer::div("Your API key: " . \tool_vault\api::get_api_key());
            // TODO allow to ditch the old API key and create/enter a new one.
        } else {
            $output .= $section->get_form()->render();
        }

        $output .= $this->heading(get_string('sitebackup', 'tool_vault'), 3);

        // TODO: this is just a POC, it needs templates, AJAX, strings, etc.
        if ($section->get_is_registered()) {
            if ($backup = \tool_vault\site_backup::get_scheduled_backup()) {
                $output .= html_writer::div("You backup is now scheduled and will be executed during the next cron run");
            } else if ($backup = \tool_vault\site_backup::get_backup_in_progress()) {
                $output .= html_writer::div("You have a backup '{$backup->backupkey}' in progress");
            } else {
                if ($backup = \tool_vault\site_backup::get_last_backup()) {
                    // @codingStandardsIgnoreLine
                    $output .= html_writer::div("Your last backup: <pre>".print_r($backup, true)."</pre>");
                }
                $output .= $this->single_button(new moodle_url($this->page->url,
                    ['section' => 'backup', 'action' => 'startbackup', 'sesskey' => sesskey()]), 'Start backup', 'get');
            }
        }

        return $output;
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

        $records = $DB->get_records('tool_vault_restores', [], 'timecreated desc');
        // @codingStandardsIgnoreLine
        $output .= html_writer::tag('pre', s(print_r($records, true)), array('class' => 'notifytiny'));

        if ($section->get_is_registered()) {
            if ($action === 'findbackups' && confirm_sesskey()) {
                $backups = \tool_vault\api::get_remote_backups();
                foreach ($backups as $backup) {
                    $output .= html_writer::div("Backup: {$backup['backupkey']} - {$backup['status']}");
                    if ($backup['status'] === \tool_vault\site_backup::STATUS_FINISHED) {
                        $output .= $this->single_button(new moodle_url($this->page->url,
                            ['section' => 'restore', 'action' => 'restore',
                                'backupkey' => $backup['backupkey'], 'sesskey' => sesskey()]),
                            'Restore this backup', 'get');
                    }
                    // @codingStandardsIgnoreLine
                    $output .= html_writer::tag('pre', s(print_r($backup, true)), array('class' => 'notifytiny'));
                }
            } else {
                $link = new moodle_url($this->page->url,
                    ['section' => 'restore', 'action' => 'findbackups', 'sesskey' => sesskey()]);
                $output .= html_writer::div(html_writer::link($link, 'Search for available backups'));
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
        return 'hi settings';
    }

    /**
     * Renderer for tab overview
     *
     * @param section_overview $section
     * @return string
     */
    public function render_section_overview(section_overview $section) {
        return 'hi overview';
    }
}
