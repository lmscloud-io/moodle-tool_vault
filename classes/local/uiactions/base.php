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

namespace tool_vault\local\uiactions;

use tool_vault\local\helpers\ui;

/**
 * Base class for UI actions
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {

    /**
     * Get handler for the action
     *
     * @return static
     */
    public static function get_handler(): self {
        $section = optional_param('section', '', PARAM_ALPHANUMEXT) ?: 'overview';
        $section = class_exists('tool_vault\local\uiactions\\' . $section) ? $section : 'overview';

        $action = optional_param('action', '', PARAM_ALPHANUMEXT);
        $class2 = 'tool_vault\local\uiactions\\'.$section.'_'.$action;
        $class = (strlen($action) && class_exists($class2)) ? $class2 : 'tool_vault\local\uiactions\\' . $section;
        return new $class();
    }

    /**
     * Process action
     */
    public function process() {
        null;
    }

    /**
     * Display
     *
     * @param \renderer_base $output
     * @return string
     */
    public function display(\renderer_base $output) {
        return '';
    }

    /**
     * Get current action
     *
     * @return string|null
     */
    protected static function get_action(): ?string {
        $parts = preg_split('|\\\\|', static::class);
        $subparts = preg_split('/_/', end($parts), 2);
        return count($subparts) > 1 ? $subparts[1] : '';
    }

    /**
     * Get URL for the current action
     *
     * @param array $params
     */
    public static function url(array $params = []) {
        $parts = preg_split('|\\\\|', static::class);
        $subparts = preg_split('/_/', end($parts), 2);
        if ($action = self::get_action()) {
            $params = ['action' => $subparts[1]] + $params;
        }
        if ($subparts[0] !== 'overview') {
            $params = ['section' => $subparts[0]] + $params;
        }
        return ui::baseurl($params);
    }

    /**
     * URL to return to
     *
     * @return \moodle_url|null
     */
    protected function get_return_url(): ?\moodle_url {
        $returnurl = optional_param('returnurl', null, PARAM_LOCALURL);
        return $returnurl ? new \moodle_url($returnurl) : null;
    }
}
