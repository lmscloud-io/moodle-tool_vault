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

namespace tool_vault\form;

use core_form\dynamic_form;
use tool_vault\api;
use tool_vault\local\helpers\ui;
use tool_vault\site_restore;
use tool_vault\site_restore_dryrun;

/**
 * Class start_restore_form
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class start_restore_form extends dynamic_form {

    /**
     * Returns context where this form is used
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    /**
     * Checks if current user has access to this form, otherwise throws exception
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('moodle/site:config', $this->get_context_for_dynamic_submission());
        if (!api::are_restores_allowed()) {
            throw new \moodle_exception('error_restoresnotallowed', 'tool_vault');
        }
        if (api::is_cli_only()) {
            throw new \moodle_exception('error_usecli', 'tool_vault');
        }
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/admin/tool/vault/index.php', ['startrestore' => 1]);
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     *
     * This method can return scalar values or arrays that can be json-encoded, they will be passed to the caller JS.
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $backupkey = $data->backupkey ?? '';
        $passphrase = $data->passphrase ?? '';
        if ($this->is_dry_run()) {
            api::validate_backup($backupkey, $passphrase);
            $op = site_restore_dryrun::schedule(['backupkey' => $backupkey, 'passphrase' => $passphrase]);
            return \tool_vault\local\uiactions\restore_details::url(['id' => $op->get_model()->id])->out(false);
        } else {
            $params = ['backupkey' => $backupkey, 'passphrase' => $passphrase, 'resume' => $this->is_resume()];
            if ($this->is_resume()) {
                $model = \tool_vault\local\models\restore_model::get_restore_to_resume();
                api::validate_backup($model->backupkey, $passphrase);
            } else {
                api::validate_backup($backupkey, $passphrase);
            }
            $restore = site_restore::schedule($params);
            return ui::progressurl(['accesskey' => $restore->get_model()->accesskey])->out(false);
        }
    }

    /**
     * Is this a dry run?
     *
     * @return bool
     */
    protected function is_dry_run(): bool {
        return $this->optional_param('dryrun', 0, PARAM_INT) == 1;
    }

    /**
     * Is this a resume?
     *
     * @return bool
     */
    protected function is_resume(): bool {
        return $this->optional_param('resume', 0, PARAM_INT) == 1;
    }

    /**
     * Is this backup encrypted?
     *
     * @return bool
     */
    protected function get_is_encrypted(): bool {
        return $this->optional_param('encrypted', 0, PARAM_INT) == 1;
    }

    /**
     * Load in existing data as form defaults
     */
    public function set_data_for_dynamic_submission(): void {
        $this->set_data([
            'backupkey' => $this->optional_param('backupkey', '', PARAM_TEXT),
            'encrypted' => (int)$this->get_is_encrypted(),
            'dryrun' => (int)$this->is_dry_run(),
            'resume' => (int)$this->is_resume(),
        ]);
    }

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'backupkey');
        $mform->setType('backupkey', PARAM_TEXT);
        $mform->addElement('hidden', 'encrypted');
        $mform->setType('encrypted', PARAM_INT);
        $mform->addElement('hidden', 'dryrun');
        $mform->setType('dryrun', PARAM_INT);
        $mform->addElement('hidden', 'resume');
        $mform->setType('resume', PARAM_INT);

        if ($this->is_dry_run()) {
            $description = get_string('startrestoreprecheck_desc', 'tool_vault');
        } else if ($this->is_resume()) {
            $description = get_string('resumerestore_desc', 'tool_vault');
        } else {
            $description = get_string('startrestore_desc', 'tool_vault');
        }
        $mform->addElement('html', \html_writer::tag('p', $description));
        if ($this->get_is_encrypted()) {
            $mform->addElement('html', \html_writer::tag('p', get_string('enterpassphrase', 'tool_vault')));
            $mform->addElement('text', 'passphrase', get_string('passphrase', 'tool_vault'));
        }
    }

    /**
     * Validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = [];

        return $errors;
    }
}
