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
use tool_vault\form\apikey_form_legacy;
use tool_vault\local\models\operation_model;
use tool_vault\local\models\restore_model;
use tool_vault\output\last_operation;

/**
 * Class main
 *
 * @package    tool_vault
 * @copyright  2023 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class main extends base {

    /**
     * Process action
     */
    public function process() {
        parent::process();
        // TODO remove legacy code
        if (!class_exists('\\core_form\\dynamic_form')) {
            $form = new apikey_form_legacy(self::url());
            if ($formdata = $form->get_data()) {
                api::set_api_key($formdata->apikey);
                $returnurl = $formdata->returnurl;
                redirect($returnurl ?: self::url());
            }
        }
    }

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

        /** @var restore_model $lastoperation */
        $lastoperation = operation_model::get_last_of([restore_model::class]);

        $rv = $output->render_from_template('tool_vault/main', [
            'pixbaseurl' => $CFG->wwwroot . '/admin/tool/vault/pix',
            'mainurl' => $CFG->wwwroot . '/admin/tool/vault/index.php',
            'allowrestore' => api::are_restores_allowed() && !api::is_cli_only(),
            'settingsurl' => (new moodle_url('/admin/settings.php', ['section' => 'tool_vault']))->out(false),
            'registrationform' => $this->registration_form($output),
            'isregistered' => api::is_registered(),
            'registrationinfo' => $registrationinfo ?? null,
            'lastoperation' => $lastoperation && $lastoperation->can_resume() ?
                (new last_operation($lastoperation))->export_for_template($output) : null,
        ]);

        return $rv;
    }
}
