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

/**
 * Plugin version and other meta-data are defined here.
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('tool_vault_index');
$PAGE->set_heading(get_string('pluginname', 'tool_vault'));
$PAGE->set_secondary_navigation(false);
$action = optional_param('action', null, PARAM_ALPHANUMEXT);

if ($action === 'startbackup' && confirm_sesskey()) {
    \tool_vault\site_backup::schedule_backup();
    redirect($PAGE->url);
} else if ($action === 'restore' && confirm_sesskey()) {
    $backupkey = required_param('backupkey', PARAM_ALPHANUMEXT);
    \tool_vault\site_restore::schedule_restore($backupkey);
    redirect($PAGE->url);
}

$isregistered = \tool_vault\api::is_registered();
if (!$isregistered) {
    $form = new \tool_vault\form\register_form($PAGE->url);
    if ($form->get_data()) {
        $form->process();
        redirect($PAGE->url);
    }
}

echo $OUTPUT->header();

if ($isregistered) {
    echo html_writer::div("Your API key: " . \tool_vault\api::get_api_key());
    // TODO allow to ditch the old API key and create/enter a new one.
} else {
    $form->display();
}

echo $OUTPUT->heading(get_string('sitebackup', 'tool_vault'), 3);

// TODO: this is just a POC, it needs templates, AJAX, strings, etc.
if ($isregistered) {
    if ($backup = \tool_vault\site_backup::get_scheduled_backup()) {
        echo html_writer::div("You backup is now scheduled and will be executed during the next cron run");
    } else if ($backup = \tool_vault\site_backup::get_backup_in_progress()) {
        echo html_writer::div("You have a backup '{$backup->backupkey}' in progress");
    } else {
        if ($backup = \tool_vault\site_backup::get_last_backup()) {
            // @codingStandardsIgnoreLine
            echo html_writer::div("Your last backup: <pre>".print_r($backup, true)."</pre>");
        }
        echo $OUTPUT->single_button(new moodle_url($PAGE->url,
            ['action' => 'startbackup', 'sesskey' => sesskey()]), 'Start backup', 'get');
    }
}
echo $OUTPUT->heading(get_string('siterestore', 'tool_vault'), 3);

// @codingStandardsIgnoreLine
print_object($DB->get_records('tool_vault_restores', [], 'timecreated desc'));

if ($isregistered) {
    if ($action === 'findbackups' && confirm_sesskey()) {
        $backups = \tool_vault\api::get_remote_backups();
        foreach ($backups as $backup) {
            echo html_writer::div("Backup: {$backup['backupkey']} - {$backup['status']}");
            if ($backup['status'] === \tool_vault\site_backup::STATUS_FINISHED) {
                echo $OUTPUT->single_button(new moodle_url($PAGE->url,
                    ['action' => 'restore', 'backupkey' => $backup['backupkey'], 'sesskey' => sesskey()]),
                    'Restore this backup', 'get');
            }
            // @codingStandardsIgnoreLine
            print_object($backup);
        }
    } else {
        $link = new moodle_url($PAGE->url, ['action' => 'findbackups', 'sesskey' => sesskey()]);
        echo html_writer::div(html_writer::link($link, 'Search for available backups'));
    }
}

echo $OUTPUT->footer();
