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

        // $form = new general_settings_form(false);
        // $registrationform = $form->render();
        $registerurl = new moodle_url(api::get_frontend_url() . '/getapikey',
            ['siteid' => api::get_site_id(), 'siteurl' => $CFG->wwwroot, 'sesskey' => sesskey()]);
        $registrationform = $output->render_from_template('tool_vault/getapikey', [
            'loginsrc' => $registerurl->out(false)
        ]);

        $rv = $output->render_from_template('tool_vault/main', [
            'pixbaseurl' => $CFG->wwwroot . '/admin/tool/vault/pix',
            'mainurl' => $CFG->wwwroot . '/admin/tool/vault/index.php',
            'allowrestore' => api::are_restores_allowed(),
            'settingsurl' => (new moodle_url('/admin/settings.php', ['section'=>'tool_vault']))->out(false),
            'registrationform' => $registrationform ?? null,
            'isregistered' => api::is_registered(),
        ]);

        return $rv;
    }
}
