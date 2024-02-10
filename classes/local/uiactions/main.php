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

namespace tool_vault\local\uiactions;

use moodle_url;
use tool_vault\api;

/**
 * Class main
 *
 * @package    tool_vault
 * @copyright  2023 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class main extends base {

    /**
     * Display
     *
     * @param \renderer_base $output
     * @return string
     */
    public function display(\renderer_base $output) {
        global $CFG;
        $rv = '';

        if (api::is_registered()) {
            $registrationinfo = [
                'vaulturl' => api::get_frontend_url(),
                'apikey' => substr(api::get_api_key(), 0, 8) . '...',
                'forgeturl' => main_forgetapikey::url()->out(false),
            ];
        }

        $rv = $output->render_from_template('tool_vault/main', [
            'pixbaseurl' => $CFG->wwwroot . '/admin/tool/vault/pix',
            'mainurl' => $CFG->wwwroot . '/admin/tool/vault/index.php',
            'allowrestore' => api::are_restores_allowed(),
            'settingsurl' => (new moodle_url('/admin/settings.php', ['section' => 'tool_vault']))->out(false),
            'registrationform' => $this->registration_form($output),
            'isregistered' => api::is_registered(),
            'registrationinfo' => $registrationinfo ?? null,
        ]);

        return $rv;
    }
}
