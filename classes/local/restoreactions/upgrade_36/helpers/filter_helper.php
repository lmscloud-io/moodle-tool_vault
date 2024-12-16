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

namespace tool_vault\local\restoreactions\upgrade_36\helpers;

/**
 * Class filter_helper
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_helper {

    /**
     * This function takes an existing mathjax url and, if it was using the standard mathjax cdn,
     * upgrades it to use the cloudflare matchjax cdn (because the standard one is shutting down).
     * @param string $mathjaxurl - The current url.
     * @param boolean $httponly - Use http instead of https - really only for 3.1 upgrades.
     * @return string The new url.
     */
    public static function filter_mathjaxloader_upgrade_cdn_cloudflare($mathjaxurl, $httponly = false) {
        return \tool_vault\local\restoreactions\upgrade_31\helpers\filter_helper::
            filter_mathjaxloader_upgrade_cdn_cloudflare($mathjaxurl, $httponly);
    }

    /**
     * Compares two values of the 'mathjaxconfig' config option.
     *
     * This is used during the upgrade to see if the two text values of the 'mathjaxconfig' config option should be
     * considered equal of different. The strings are normalized so that EOL characters and whitespace is not significant.
     *
     * @param string $val1 value
     * @param string $val2 value
     * @return bool true if the texts should be considered equals, false otherwise
     */
    public static function filter_mathjaxloader_upgrade_mathjaxconfig_equal($val1, $val2) {

        $val1lines = preg_split("/[\r\n]/", $val1);
        $val2lines = preg_split("/[\r\n]/", $val2);

        $val1lines = array_map('trim', $val1lines);
        $val2lines = array_map('trim', $val2lines);

        $val1lines = array_filter($val1lines, function($value) {
            return $value !== '';
        });

        $val2lines = array_filter($val2lines, function($value) {
            return $value !== '';
        });

        return (implode(' ', $val1lines) === implode(' ', $val2lines));
    }
}