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
define('NO_OUTPUT_BUFFERING', true);

require(__DIR__ . '/../../../config.php');

$accesskey = required_param('accesskey', PARAM_ALPHANUM);
$operation = \tool_vault\local\models\operation_model::get_by_access_key($accesskey);

@header('Content-Type: text/html; charset=utf-8');

$title = get_string('pluginname', 'tool_vault');
echo <<<EOF
<html>
<head>
    <title>$title</title>
</head>
<body>
EOF;

echo html_writer::start_div('p-3');

$isoldoperation = $operation &&
    !in_array($operation->status, [\tool_vault\constants::STATUS_INPROGRESS, \tool_vault\constants::STATUS_SCHEDULED]) &&
    $operation->get_last_modified() < time() - HOURSECS;

if ($operation instanceof \tool_vault\local\models\backup_model) {
    if ($isoldoperation) {
        $url = tool_vault\local\uiactions\backup_details::url(['id' => $operation->id]);
        echo "<p>This backup has already finished. You can access the logs <a href=\"$url\">here</a></p>";
    } else {
        $data = (new \tool_vault\output\backup_details($operation))->export_for_template($OUTPUT);
        echo $OUTPUT->render_from_template('tool_vault/backup_details', $data);
    }
} else if ($operation instanceof \tool_vault\local\models\restore_model) {
    if ($isoldoperation) {
        $url = \tool_vault\local\uiactions\restore_details::url(['id' => $operation->id]);
        echo "<p>This restore has already finished. You can access the logs <a href=\"$url\">here</a></p>";
    } else {
        $data = (new \tool_vault\output\restore_details($operation))->export_for_template($OUTPUT);
        echo $OUTPUT->render_from_template('tool_vault/restore_details', $data);
    }
} else {
    echo 'Accesskey is not valid';
}
echo html_writer::end_div();

echo <<<EOF
</body>
</html>
EOF;
