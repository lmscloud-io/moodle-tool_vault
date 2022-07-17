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
 * Plugin message providers are defined here.
 *
 * @package     tool_vault
 * @category    message
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if (defined('MESSAGE_DEFAULT_ENABLED')) {
    $defaultperm = MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED;
} else {
    $defaultperm = MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN + MESSAGE_DEFAULT_LOGGEDOFF;
}

$messageproviders = [

    'statusupdate' => [
        'defaults' => [
            'popup' => $defaultperm,
            'email' => $defaultperm,
        ],
        'capability'  => 'moodle/site:config',
    ],
];
