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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_vault\local\helpers;

use single_button;

/**
 * Wrapper for single_button that creates a primary button in any Moodle version.
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class primary_button extends single_button {
    /**
     * Constructor
     *
     * @param string $text Button text
     * @param array $attributes Additional attributes
     */
    public function __construct(string $text, array $attributes = []) {
        global $CFG, $PAGE;
        $url = $PAGE->url;
        if ((int)$CFG->branch == 401) {
            parent::__construct(
                $url,
                $text,
                'get',
                true,
                $attributes);
        } else {
            parent::__construct(
                $url,
                $text,
                'get',
                single_button::BUTTON_PRIMARY,
                $attributes);
        }
    }
}
