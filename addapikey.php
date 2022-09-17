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
 * Add API key (callback from the lmsvault.io)
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$apikey = required_param('apikey', PARAM_RAW);

admin_externalpage_setup('tool_vault_addapikey', '', null, '', ['nosearch' => true]);

$PAGE->set_pagelayout('embedded');
$PAGE->set_heading(get_string('addapikey', 'tool_vault'));
if (method_exists($PAGE, 'set_secondary_navigation')) {
    $PAGE->set_secondary_navigation(false);
}
/** @var tool_vault\output\renderer $renderer */
$renderer = $PAGE->get_renderer('tool_vault');

echo $renderer->header();

\tool_vault\api::set_api_key($apikey); // TODO add all validation!!!
echo $OUTPUT->render_from_template('tool_vault/registercallback', ['apikey' => substr($apikey, -8)]);

echo $renderer->footer();
