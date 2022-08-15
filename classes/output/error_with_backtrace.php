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

namespace tool_vault\output;

use renderer_base;
use tool_vault\local\checks\check_base;
use tool_vault\local\models\operation_model;
use tool_vault\site_backup;

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
     * Constructor
     *
     * @param operation_model $model
     */
    public function __construct(operation_model $model) {
        $this->error = ($model->get_details()['error'] ?? null);
        $this->backtrace = debugging() ? ($model->get_details()['errorbacktrace'] ?? null) : null;
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
            'backtrace' => $this->backtrace,
        ];
    }
}
