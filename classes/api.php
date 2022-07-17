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

namespace tool_vault;

use tool_vault\local\models\remote_backup;
use tool_vault\task\backup_task;

/**
 * Main api
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {

    /**
     * Get API URL
     *
     * @return string
     */
    protected static function get_api_url() {
        global $CFG;
        // TODO: replace URL.
        return $CFG->tool_vault_api ?? 'https://todo-future-vault-api.example.com';
    }

    /**
     * Get a value from the special plugin config (not included in backups)
     *
     * @param string $name
     * @return null
     */
    public static function get_config(string $name) {
        global $DB;
        $record = $DB->get_record('tool_vault_config', ['name' => $name]);
        return $record ? $record->value : null;
    }

    /**
     * Get currently stored API key
     *
     * @return null
     */
    public static function get_api_key() {
        return self::get_config('apikey');
    }

    /**
     * Set or forget API key
     *
     * @param string|null $apikey
     * @return void
     */
    public static function set_api_key(?string $apikey) {
        if ($apikey !== self::get_api_key()) {
            self::store_config('apikey', $apikey);
            self::store_config('cachedremotebackupstime', null);
            self::store_config('cachedremotebackups', null);
        }
    }

    /**
     * Are restores allowed on this site
     *
     * @return bool
     */
    public static function are_restores_allowed(): bool {
        return (bool)self::get_config('allowrestore');
    }

    /**
     * Store a value in the special plugin config (not included in backups)
     *
     * @param string $name
     * @param string $value
     * @return void
     */
    public static function store_config(string $name, ?string $value) {
        global $DB;
        if ($record = $DB->get_record('tool_vault_config', ['name' => $name])) {
            $DB->update_record('tool_vault_config', ['id' => $record->id, 'value' => $value]);
        } else {
            $DB->insert_record('tool_vault_config', ['name' => $name, 'value' => $value]);
        }
    }

    /**
     * Is there an active API key
     *
     * @return bool
     */
    public static function is_registered(): bool {
        $apikey = self::get_api_key();
        return !empty($apikey);
    }

    /**
     * Perform a call to vault API
     *
     * @param string $endpoint
     * @param string $method
     * @param array $params
     * @param bool $authheader include authentication header
     * @param string|null $apikey override current api key (used in the validation function)
     * @return mixed
     */
    public static function api_call(string $endpoint, string $method, array $params = [],
                                    bool $authheader = true, ?string $apikey = null) {
        $curl = new \curl();
        if ($authheader) {
            $curl->setHeader(['X-Api-Key: ' . ($apikey ?? self::get_api_key())]);
        }
        $curl->setHeader(['Accept: application/json', 'Expect:']);

        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_MAXREDIRS' => 3,
        ];

        $url = self::get_api_url() . '/' . ltrim($endpoint, '/');
        $method = strtolower($method);
        switch ($method) {
            case 'post':
            case 'put':
            case 'patch':
                $curl->setHeader(['Content-Type: application/json']);
                $rv = $curl->$method($url, json_encode($params), $options);
                break;
            case 'get':
                $rv = $curl->get($url, $params, $options);
                break;
            case 'delete':
                $rv = $curl->delete($url, $params, $options);
                break;
            default:
                throw new \coding_exception('Unsupported method: '.$method);
        }

        $info = $curl->get_info();
        $error = $curl->error;
        $errno = $curl->get_errno();
        if ($errno || !is_array($info) || $info['http_code'] != 200) {
            // TODO string, display error, etc.
            // @codingStandardsIgnoreLine
            throw new \moodle_exception("Can not connect to API, errno $errno, error '$error': ". $rv."\n". print_r($info, true));
        }
        return json_decode($rv, true);
    }

    /**
     * Register (TODO temporary)
     *
     * @return void
     */
    public static function register() {
        global $USER, $CFG;
        $params = [
            'secret' => $CFG->tool_vault_secret ?? '',
            'email' => $USER->email,
            'name' => fullname($USER),
        ];
        $result = self::api_call('register', 'POST', $params, false);
        if (!empty($result['apikey'])) {
            self::set_api_key($result['apikey']);
        } else {
            // TODO string? special treatment?
            // @codingStandardsIgnoreLine
            throw new \moodle_exception('Could not register: '.print_r($result, true));
        }
    }

    /**
     * Upload a backup file to the cloud
     *
     * @param string $backupkey
     * @param string $filepath
     * @param string $contenttype
     * @return void
     */
    public static function upload_backup_file(string $backupkey, string $filepath, string $contenttype) {
        $filename = basename($filepath);
        $result = self::api_call("backups/$backupkey/upload/$filename", 'post', ['contenttype' => $contenttype]);
        if (empty($result['uploadurl'])) {
            // TODO string?
            throw new \moodle_exception('Could not upload file to backup');
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        curl_setopt($ch, CURLOPT_URL, $result['uploadurl']);
        curl_setopt($ch, CURLOPT_PUT, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-type: $contenttype"]);

        $fh = fopen($filepath, 'r');

        curl_setopt($ch, CURLOPT_INFILE, $fh);
        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($filepath));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec ($ch);

        $info  = curl_getinfo($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);

        fclose($fh);

        if ($errno || !is_array($info) || $info['http_code'] != 200) {
            // TODO process error properly.
            // @codingStandardsIgnoreLine
            throw new \moodle_exception('Could not upload file to backup: '.$res."\n".print_r($info, true));
        }
    }

    /**
     * Validate API key
     *
     * @param string $apikey
     * @return bool
     */
    public static function validate_api_key(string $apikey) {
        try {
            $result = self::api_call('backups', 'GET', [], true, $apikey);
        } catch (\moodle_exception $e) {
            return false;
        }
        return true;
    }

    /**
     * Get a list of all remote backups for this api key
     *
     * @return remote_backup[]
     */
    public static function get_remote_backups(): array {
        $conf = self::get_config('cachedremotebackups');
        if ($conf !== null) {
            $records = json_decode($conf, true);
        } else {
            $apiresult = self::api_call('backups', 'GET', []);
            self::store_config('cachedremotebackupstime', time());
            $records = $apiresult['backups'];
            self::store_config('cachedremotebackups', json_encode($apiresult['backups']));
        }
        $backups = array_map(function($b) {
            return new remote_backup($b);
        }, $records);
        usort($backups, function($a, $b) {
            return - $a->timecreated + $b->timecreated;
        });
        return $backups;
    }

    /**
     * Last time we fetched remote backups
     *
     * @return int
     */
    public static function get_remote_backups_time(): int {
        return self::get_config('cachedremotebackupstime');
    }

    /**
     * Get information about one backup
     *
     * @param string $backupkey
     * @param string|null $withstatus
     * @return remote_backup
     */
    public static function get_remote_backup(string $backupkey, ?string $withstatus = null): remote_backup {
        $result = self::api_call("backups/{$backupkey}", 'GET');
        if (isset($withstatus) && $result['status'] !== $withstatus) {
            throw new \moodle_exception('Backup has a wrong status');
        }
        $result['backupkey'] = $backupkey;
        return new remote_backup($result);
    }

    /**
     * Helper function to format the dates for the backup/restore logs
     *
     * @param int $timestamp
     * @return string
     */
    public static function format_date_for_logs(int $timestamp) {
        return "[".userdate($timestamp, get_string('strftimedatetimeaccurate', 'core_langconfig'))."]";
    }

    /**
     * Download backup file
     *
     * @param string $backupkey
     * @param string $filepath
     * @return void
     */
    public static function download_backup_file(string $backupkey, string $filepath) {
        $filename = basename($filepath);
        $result = self::api_call("backups/$backupkey/download/$filename", 'get', []);
        if (empty($result['downloadurl'])) {
            // TODO string?
            throw new \moodle_exception('Unable to download backup file');
        }
        $curl = new \curl();
        $result = $curl->download_one($result['downloadurl'], [], ['filepath' => $filepath]);
        if (!$result) {
            throw new \moodle_exception('Unable to download backup file');
        }
    }
}
