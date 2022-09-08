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
 * Backup or restore progress
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
define('CACHE_DISABLE_ALL', true);

require(__DIR__ . '/../../../config.php');

$accesskey = required_param('accesskey', PARAM_ALPHANUM);
$PAGE->set_url(new moodle_url('/admin/tool/vault/progress.php', ['accesskey' => $accesskey]));
$operation = \tool_vault\local\models\operation_model::get_by_access_key($accesskey);

$PAGE->set_pagelayout('embedded');
$PAGE->set_heading(get_string('pluginname', 'tool_vault'));
if (method_exists($PAGE, 'set_secondary_navigation')) {
    $PAGE->set_secondary_navigation(false);
}

/** @var tool_vault\output\renderer $renderer */
$renderer = $PAGE->get_renderer('tool_vault');

echo $renderer->header();

echo html_writer::start_div('p-3');
if ($operation instanceof \tool_vault\local\models\backup_model) {
    $data = (new \tool_vault\output\backup_details($operation))->export_for_template($renderer);
    echo $renderer->render_from_template('tool_vault/backup_details', $data);
} else if ($operation instanceof \tool_vault\local\models\restore_model) {
    $data = (new \tool_vault\output\restore_details($operation))->export_for_template($renderer);
    echo $renderer->render_from_template('tool_vault/restore_details', $data);
} else {
    echo 'Accesskey is not valid';
}
echo html_writer::end_div();

echo $renderer->footer();
