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

namespace tool_vault\output;
use tool_vault\api;
use tool_vault\local\cli_helper;

/**
 * Class start_backup
 *
 * @package    tool_vault
 * @copyright  2024 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class start_backup_popup implements \templatable {
    /** @var array */
    protected $precheckresults;

    /**
     * Constructor
     *
     * @param array $precheckresults results of the API call to check if backup is allowed. May contain the
     *     following fields:
     *      - expiredays (int) - number of days until backup will expire (preset for free accounts or a user setting)
     *      - canchangeexpiration (bool) - can user change backup expiration or should it be locked
     *      - buckets (array: id,label,isdefault,encryption,type) - available storage options
     *      - limit (int) - maximum size of backup allowed, in bytes, archived
     */
    public function __construct(array $precheckresults) {
        $this->precheckresults = $precheckresults;
    }

    /**
     * Function to export the renderer data in a format that is suitable for a mustache template
     *
     * @param \renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return array|\stdClass
     */
    public function export_for_template(\renderer_base $output) {
        global $CFG, $USER;

        $result = $this->precheckresults;

        $description = get_string('defaultbackupdescription', 'tool_vault',
            (object)[
                'site' => $CFG->wwwroot,
                'name' => fullname($USER, true),
            ]);
        $data = ['description' => $description];

        if (!empty($result['buckets']) && is_array($result['buckets'])) {
            $data['buckets'] = [];
            $hasext = false;
            foreach ($result['buckets'] as $bucket) {
                $data['buckets'][] = [
                    'id' => $bucket['id'],
                    'label' => $bucket['label'],
                    'isdefault' => !empty($bucket['isdefault']),
                ];
                $default = (empty($bucket['isdefault']) && !empty($default)) ? $default : $bucket;
                $hasext = $hasext || (($bucket['type'] ?? '') === 'ext');
            }
            $data['withencryption'] = !empty($default['encryption']);
            $data['showbucketselect'] = (count($data['buckets']) > 1) || $hasext;
        }

        $data['expiredays'] = (int)($result['expiredays'] ?? 0);
        $data['canchangeexpiration'] = !empty($result['canchangeexpiration']);

        $limit = (int)($result['limit'] ?? 0);
        if ($limit) {
            $data['limit'] = display_size($limit);
        }

        $data['showupgrademessage'] = empty($result['canchangeexpiration']) || $limit > 0;
        $data['vaulturl'] = api::get_frontend_url();

        $backupplugincode = get_config('tool_vault', 'backupplugincode');
        if ($backupplugincode >= 0) {
            $data['allowbackupplugincode'] = 1;
            $data['backupplugincode'] = (bool)$backupplugincode;
            $data['backupplugincodehelp'] =
                (new \help_icon('settings_backupplugincode', 'tool_vault'))->export_for_template($output);
        }

        return $data;
    }

    /**
     * Display all backup precheck results for the CLI
     *
     * @param cli_helper $clihelper
     * @return void
     */
    public function display_in_cli(cli_helper $clihelper) {
        $precheckresults = $this->precheckresults;

        $expiredays = (int)($precheckresults['expiredays'] ?? 0);
        if (empty($precheckresults['canchangeexpiration'])) {
            $clihelper->cli_writeln('Default backup expiration time: ' .
                ($expiredays ? "After $expiredays days" : 'Never'));
            $clihelper->cli_writeln('You can not change the expiration time, option --expiredays will be ignored.');
        }
        if ($limit = (int)($precheckresults['limit'] ?? 0)) {
            $clihelper->cli_writeln(get_string('startbackup_limit_desc', 'tool_vault', display_size($limit)));
        }

        $buckets = $precheckresults['buckets'] ?? [];
        $cntwithenc = 0;
        foreach ($buckets as $bucket) {
            $cntwithenc += empty($bucket['encryption']) ? 0 : 1;
        }
        $hasdifferentenc = count($buckets) != $cntwithenc && $cntwithenc;

        if (count($buckets) > 1 || (count($buckets) == 1 && ($buckets[0]['type'] ?? '') == 'ext')) {
            $clihelper->cli_writeln('Available backup storage (--storage option):');
            $tabledata = [];
            foreach ($buckets as $bucket) {
                $tabledata['- ' . $bucket['id'].(!empty($bucket['isdefault']) ? ' (default)' : '')] =
                    $bucket['label'] . ($hasdifferentenc ?
                    (!empty($bucket['encryption']) ? "\nPassphrase supported" : "\nPassphrase not supported") : '');
            }
            echo $clihelper->print_table($tabledata);
        }
        if (!$hasdifferentenc && count($buckets)) {
            if ($cntwithenc) {
                $clihelper->cli_writeln(get_string('startbackup_enc_desc', 'tool_vault'));
            } else {
                $clihelper->cli_writeln(get_string('startbackup_noenc_desc', 'tool_vault'));
            }
        }
    }
}
