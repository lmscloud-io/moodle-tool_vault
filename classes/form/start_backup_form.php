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
use tool_vault\output\start_backup_popup;

/**
 * Class start_backup_form
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class start_backup_form extends dynamic_form {

    /** @var start_backup_popup|null */
    protected $startbackuppopup = null;

    /**
     * Summary of get_info
     *
     * @return start_backup_popup
     */
    protected function get_info(): start_backup_popup {
        if ($this->startbackuppopup === null) {
            $result = api::precheck_backup_allowed();
            $this->startbackuppopup = new start_backup_popup($result);
        }
        return $this->startbackuppopup;
    }

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
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/admin/tool/vault/index.php', ['startbackup' => 1]);
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     *
     * This method can return scalar values or arrays that can be json-encoded, they will be passed to the caller JS.
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $backup = \tool_vault\site_backup::schedule([
            'passphrase' => $data->passphrase ?? null,
            'description' => $data->description ?? null,
            'bucket' => $data->bucket ?? '',
            'expiredays' => $data->expiredays ?? 0,
        ]);
        $url = \tool_vault\local\helpers\ui::progressurl(['accesskey' => $backup->get_model()->accesskey]);
        return $url->out(false);
    }

    /**
     * Load in existing data as form defaults
     */
    public function set_data_for_dynamic_submission(): void {
        global $USER, $CFG;
        $description = get_string('defaultbackupdescription', 'tool_vault',
            (object)[
                'site' => $CFG->wwwroot,
                'name' => fullname($USER, true),
            ]);
        $this->set_data([
            'description' => $description,
            'expiredays' => $this->get_info()->get_expiration_days() ?: '',
        ]);
    }

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('html', \html_writer::tag('p', get_string('startbackup_desc', 'tool_vault')));
        if ($buckets = $this->get_info()->get_buckets_select()) {
            $mform->addElement('select', 'bucket', get_string('startbackup_bucket', 'tool_vault'), $buckets);
        }
        if ($this->get_info()->get_with_encryption()) {
            $mform->addElement('html', \html_writer::tag('p', get_string('startbackup_enc_desc', 'tool_vault')));
            $mform->addElement('text', 'passphrase', get_string('passphrase', 'tool_vault'));
            $mform->setType('passphrase', PARAM_RAW_TRIMMED);
        } else {
            $mform->addElement('html', get_string('startbackup_noenc_desc', 'tool_vault'));
        }
        $mform->addElement('text', 'description', get_string('backupdescription', 'tool_vault'),
            ['style' => 'width:100%', 'maxlength' => 255]);
        $mform->addRule('description', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->setType('description', PARAM_NOTAGS);

        $limit = $this->get_info()->get_limit();
        $showupgrademessage = !$this->get_info()->get_can_change_expiration() || $limit > 0;

        $group = [];
        $group[] = $textel = $mform->createElement('text', 'expiredays',
            get_string('startbackup_expiredays_prefix', 'tool_vault'), ['style' => 'width:50px']);
        $group[] = $mform->createElement('static', 's2', '', get_string('startbackup_expiredays_suffix', 'tool_vault'));
        $mform->addGroup($group, 'expiredaysgroup', get_string('startbackup_expiredays_prefix', 'tool_vault'), null, false);
        $mform->setType('expiredays', PARAM_INT);
        $mform->setDefault('expiredays', $this->get_info()->get_expiration_days() ?: '');
        if (!$this->get_info()->get_can_change_expiration()) {
            $textel->freeze();
        }
        if ($limit) {
            $mform->addElement('html', \html_writer::tag('p',
                get_string('startbackup_limit_desc', 'tool_vault', display_size($limit))));
        }
        if ($showupgrademessage) {
            $mform->addElement('html', \html_writer::tag('p', get_string('startbackup_cta', 'tool_vault', [
                'href' => api::get_frontend_url(),
                'link' => api::get_frontend_url(),
            ])));
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
