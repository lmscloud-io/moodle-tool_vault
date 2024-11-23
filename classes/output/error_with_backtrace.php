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

namespace tool_vault\output;

use renderer_base;
use tool_vault\local\models\operation_model;

/**
 * Display error with backtrace
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class error_with_backtrace implements \templatable {

    /** @var string */
    protected $error;
    /** @var string */
    protected $backtrace;

    /**
     * Create from model
     *
     * @param operation_model $model
     * @return static
     */
    public static function create_from_model(operation_model $model) {
        $s = new self();
        $details = $model->get_details() + ['error' => null, 'errorbacktrace' => null];
        $s->error = $details['error'];
        $s->backtrace = $details['errorbacktrace'];
        return $s;
    }

    /**
     * Create from exception
     *
     * @param \Throwable|\Exception $t
     * @return static
     */
    public static function create_from_exception($t) {
        $e = new self();
        $e->error = $t->getMessage();
        $e->backtrace = $t->getTraceAsString();
        return $e;
    }

    /**
     * Export for template
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $message = $this->error;
        if (!$message) {
            return [];
        }
        return [
            'uniqueid' => 'errormessage'.random_string(),
            'error' => $this->error,
            'backtrace' => debugging() ? $this->backtrace : null,
        ];
    }
}
