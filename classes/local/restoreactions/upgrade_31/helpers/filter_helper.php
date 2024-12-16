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

namespace tool_vault\local\restoreactions\upgrade_31\helpers;

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
        $newcdnurl = $mathjaxurl;
        $cdnroot = 'https://cdn.mathjax.org/mathjax/';
        if ($httponly) {
            $cdnroot = 'http://cdn.mathjax.org/mathjax/';
        }
        $usingcdn = strpos($mathjaxurl, $cdnroot) === 0;
        if ($usingcdn) {
            $majorversion = substr($mathjaxurl, strlen($cdnroot), 3);
            $latestversion = '2.7.0';
            if ($majorversion == '2.6') {
                $latestversion = '2.6.1';
            } else if ($majorversion == '2.5') {
                $latestversion = '2.5.3';
            }

            $offset = strpos($mathjaxurl, '/', strlen($cdnroot));
            if ($offset === false) {
                return $newcdnurl;
            }

            $endofurl = substr($mathjaxurl, $offset + 1);

            $newcdnbase = 'https://cdnjs.cloudflare.com/ajax/libs/mathjax/';
            if ($httponly) {
                $newcdnbase = 'http://cdnjs.cloudflare.com/ajax/libs/mathjax/';
            }

            $newcdnurl = $newcdnbase . $latestversion . '/' . $endofurl;
        }

        return $newcdnurl;
    }
}