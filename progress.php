<?php
// This file is part of plugin tool_vault - https://lmsvault.io
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
define('NO_UPGRADE_CHECK', true);

require(__DIR__ . '/../../../config.php');

$accesskey = required_param('accesskey', PARAM_ALPHANUM);
$operation = \tool_vault\local\models\operation_model::get_by_access_key($accesskey);

@header('Content-Type: text/html; charset=utf-8');

$title = get_string('pluginname', 'tool_vault');
echo <<<EOF
<html>
<head>
    <title>$title</title>
    <style>
    body {
        padding: 1rem;
    }
    .generaltable {
        width: 100%;
        margin-bottom: 1rem;
        color: #1d2125;
    }
    .generaltable tbody tr:nth-of-type(odd) {
        background-color: rgba(0,0,0,.03);
    }
    .generaltable th, .generaltable td {
        padding: 0.75rem;
        vertical-align: top;
        border-top: 1px solid #dee2e6;
    }
    .generaltable th {
        text-align: -webkit-match-parent;
    }
    </style>
</head>
<body>
EOF;

echo html_writer::start_div('p-3');
/** @var tool_vault\output\renderer $renderer */
$renderer = $PAGE->get_renderer('tool_vault');

if ($operation) {
    $operation->fail_if_stuck();
}

$isoldoperation = $operation &&
    !in_array($operation->status, [\tool_vault\constants::STATUS_INPROGRESS, \tool_vault\constants::STATUS_SCHEDULED]) &&
    $operation->timemodified < time() - DAYSECS;

if ($operation instanceof \tool_vault\local\models\backup_model) {
    if ($isoldoperation) {
        $url = tool_vault\local\uiactions\backup_details::url(['id' => $operation->id])->out();
        echo '<p>' . get_string('backupfinished', 'tool_vault', $url) . '</p>';
    } else {
        $data = (new \tool_vault\output\backup_details($operation, null, true, true))->export_for_template($renderer);
        echo $renderer->render_from_template('tool_vault/backup_details', $data);
    }
} else if ($operation instanceof \tool_vault\local\models\restore_model) {
    $url = \tool_vault\local\uiactions\restore_details::url(['id' => $operation->id]);
    if ($isoldoperation) {
        echo '<p>' . get_string('restorefinished', 'tool_vault', (string)$url) . '</p>';
    } else {
        $data = (new \tool_vault\output\restore_details($operation))->export_for_template($renderer);
        $data['isprogresspage'] = 1;
        if ($data['errormessage']) {
            $data['errordetailsurl'] = $url->out(false);
        }
        echo $renderer->render_from_template('tool_vault/restore_details', $data);
    }
} else {
    echo get_string('error_accesskeyisnotvalid', 'tool_vault');
}
echo html_writer::end_div();

echo <<<EOF
</body>
</html>
EOF;
