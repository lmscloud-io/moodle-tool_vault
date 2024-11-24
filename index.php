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
 * Plugin version and other meta-data are defined here.
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$section = \tool_vault\local\uiactions\base::get_handler();
admin_externalpage_setup('tool_vault_index', '', null, $section->url(), ['nosearch' => true]);
if (moodle_needs_upgrading()) {
    redirect(new moodle_url('/admin/index.php'));
}
$PAGE->set_heading(get_string('pluginname', 'tool_vault'));
if (method_exists($PAGE, 'set_secondary_navigation')) {
    $PAGE->set_secondary_navigation(false);
}

$section->process();

$section->page_setup($PAGE);
/** @var tool_vault\output\renderer $renderer */
try {
    $renderer = $PAGE->get_renderer('tool_vault');
} catch (moodle_exception $e) {
    $renderer = new tool_vault\output\renderer($PAGE, '');
}

echo $renderer->header();
echo $section->display($renderer);
echo $renderer->footer();
