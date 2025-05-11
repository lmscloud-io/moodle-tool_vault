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

use tool_vault\local\cli_helper;

/**
 * Prepare information for the start backup popup or start backup CLI
 *
 * @package    tool_vault
 * @copyright  2024 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class start_backup_popup {
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
     * List of available storage buckets for this API key
     *
     * @return array where each element is an array with the following keys:
     *    - id (string) - bucket ID (internal name)
     *    - label (string) - bucket label (display name)
     *    - isdefault (bool) - true if this bucket is default
     *    - encryption (bool) - true if this bucket supports encryption
     */
    protected function get_buckets(): array {
        $result = $this->precheckresults;
        if (!empty($result['buckets']) && is_array($result['buckets'])) {
            return $result['buckets'];
        }
        return [];
    }

    /**
     * Returns the options for the storage select element (default bucket first)
     *
     * @return array name=>label
     */
    public function get_buckets_select(): array {
        $buckets = [];
        $hasext = false;
        foreach ($this->get_buckets() as $bucket) {
            $b = [$bucket['id'] => $bucket['label']];
            if (!empty($bucket['isdefault'])) {
                $buckets = $b + $buckets;
            } else {
                $buckets = $buckets + $b;
            }
            $hasext = $hasext || (($bucket['type'] ?? '') === 'ext');
        }
        $showbucketselect = (count($buckets) > 1) || $hasext;
        return $showbucketselect ? $buckets : [];
    }

    /**
     * Does any bucket support passphrase encryption
     *
     * @return bool
     */
    public function get_with_encryption(): bool {
        $bucketswithenc = array_filter($this->get_buckets(), fn($bucket) => !empty($bucket['encryption']));
        return !empty($bucketswithenc);
    }

    /**
     * Default expiration time in days (0 means never)
     *
     * @return int
     */
    public function get_expiration_days(): int {
        return (int)($this->precheckresults['expiredays'] ?? 0);
    }

    /**
     * Can user change expiration time
     *
     * @return bool
     */
    public function get_can_change_expiration(): bool {
        return !empty($this->precheckresults['canchangeexpiration']);
    }

    /**
     * Maximum size of backup allowed, in bytes, archived
     *
     * @return int
     */
    public function get_limit(): int {
        return (int)($this->precheckresults['limit'] ?? 0);
    }

    /**
     * Display all backup precheck results for the CLI
     *
     * @param cli_helper $clihelper
     * @return void
     */
    public function display_in_cli(cli_helper $clihelper) {

        $expiredays = $this->get_expiration_days();
        if (!$this->get_can_change_expiration()) {
            $clihelper->cli_writeln('Default backup expiration time: ' .
                ($expiredays ? "After $expiredays days" : 'Never'));
            $clihelper->cli_writeln('You can not change the expiration time, option --expiredays will be ignored.');
        }
        if ($limit = $this->get_limit()) {
            $clihelper->cli_writeln(get_string('startbackup_limit_desc', 'tool_vault', display_size($limit)));
        }

        $buckets = $this->get_buckets();
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
