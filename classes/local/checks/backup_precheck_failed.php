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

namespace tool_vault\local\checks;

/**
 * Class backup_precheck_failed
 *
 * @package    tool_vault
 * @copyright  2024 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_precheck_failed extends \moodle_exception {
    /** @var string */
    protected $extrainfo;

    /**
     * Constructor
     *
     * @param check_base $chk
     */
    public function __construct(check_base $chk) {
        $message = html_to_text($chk->summary());
        if ($chk->has_details()) {
            $message .= "\n".get_string('seefullreport', 'tool_vault').": ".
                $chk->get_fullreport_url()->out(false)."\n";
        }
        $a = (object)[
            'name' => $chk->get_display_name(),
            'message' => $message,
        ];
        parent::__construct('error_backupprecheckfailed', 'tool_vault', null, $a);
        $this->extrainfo = $chk->failure_details();
    }

    /**
     * Return extra information to send to the server
     *
     * @return string
     */
    public function extra_info(): string {
        return $this->extrainfo;
    }
}
