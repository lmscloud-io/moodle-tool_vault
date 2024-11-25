<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tool vault autoloader for Moodle < 2.6
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Moodle autoloader was added in version 2.6.
if ($CFG->version < 2013111800) {
    spl_autoload_register(function($class) {
        global $CFG;
        $parts = explode("\\", trim($class, '\\'));
        if (count($parts) > 1 && $parts[0] == "tool_vault") {
            array_shift($parts);
            $path = $CFG->dirroot . '/' . $CFG->admin . '/tool/vault/classes/' . join('/', $parts) . '.php';
            if (file_exists($path)) {
                require_once($path);
            }
        }
    });
    interface templatable {}
}