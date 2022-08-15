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

use tool_vault\local\exceptions\api_exception;
use tool_vault\local\logger;
use tool_vault\local\models\backup_file;
use tool_vault\local\models\remote_backup;

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
        global $DB, $CFG;
        if (isset($CFG->forced_plugin_settings['tool_vault'][$name])) {
            return $CFG->forced_plugin_settings['tool_vault'][$name];
        }
        $record = $DB->get_record('tool_vault_config', ['name' => $name]);
        return $record ? $record->value : null;
    }

    /**
     * Default config on installation
     *
     * @return void
     */
    public static function insert_default_config() {
        if (!(defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
            self::store_config('backupexcludedataroot', 'muc, antivirus_quarantine');
        }
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
     * @param logger|null $logger
     * @param bool $authheader include authentication header
     * @param string|null $apikey override current api key (used in the validation function)
     * @return mixed
     * @throws api_exception
     */
    protected static function api_call(string $endpoint, string $method, array $params = [],
                                    ?logger $logger = null, bool $authheader = true, ?string $apikey = null) {
        global $CFG;
        require_once($CFG->dirroot.'/lib/filelib.php');

        $headers = [];
        if ($authheader) {
            $headers[] = 'X-Api-Key: ' . ($apikey ?? self::get_api_key());
        }
        $headers = array_merge($headers, ['Accept: application/json', 'Expect:']);

        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_TIMEOUT' => constants::REQUEST_API_TIMEOUT,
            'CURLOPT_MAXREDIRS' => 3,
        ];

        $url = self::get_api_url() . '/' . ltrim($endpoint, '/');
        $method = strtolower($method);
        switch ($method) {
            case 'post':
            case 'put':
            case 'patch':
                $headers[] = 'Content-Type: application/json';
                $params = json_encode($params);
                break;
            case 'get':
            case 'delete':
                break;
            default:
                throw new \coding_exception('Unsupported method: '.$method);
        }

        $curl = new \curl();
        $curl->setHeader($headers);
        $rv = $curl->$method($url, $params, $options);

        if ($curl->errno || (($curl->get_info()['http_code'] ?? 0) != 200)) {
            // TODO retry up to REQUEST_API_RETRIES.
            throw self::prepare_api_exception($curl, $rv);
        }

        return json_decode($rv, true);
    }

    /**
     * Prepares an exception from Vault API request curl
     *
     * @param \curl $curl
     * @param string|null $response response from Vault API, usually JSON
     * @return api_exception
     */
    protected static function prepare_api_exception(\curl $curl, ?string $response): api_exception {
        $errno = $curl->get_errno();
        $error = $curl->error;
        $httpcode = (int)($curl->get_info()['http_code'] ?? 0);
        if ($httpcode) {
            // This is an error returned by the server.
            $errormessage = ($httpcode == 401) ?
                "Vault API authentication error" :
                "Vault API error ({$httpcode})";

            $json = @json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json) && count($json) == 1 && key($json) === 'message') {
                // In case of a server error it will most likely return json-encoded message.
                $errormessage .= ": " . s($json['message']);
            } else if (strlen($response)) {
                // Not sure what the server returned be but let's print to the user hoping that it is helpful.
                $errormessage .= ": " . s($response);
            }
            return new api_exception($errormessage, $httpcode);
        } else {
            // This is a connection error.
            // List of errno: https://www.php.net/manual/en/function.curl-errno.php , for example CURLE_OPERATION_TIMEDOUT.
            return new api_exception("Can not connect to Vault API, errno $errno: ". $error, 0);
        }
    }

    /**
     * Prepares an exception from S3 curl request
     *
     * @param \curl $curl
     * @param string|null $response - S3 response (normally XML)
     * @return api_exception
     */
    protected static function prepare_s3_exception(\curl $curl, ?string $response): api_exception {
        $errno = $curl->get_errno();
        $error = $curl->error;
        $httpcode = (int)($curl->get_info()['http_code'] ?? 0);
        if ($httpcode) {
            // This is an error returned by the server.
            $errormessage = "AWS S3 error ({$httpcode})";

            if (strlen($response) && substr($response, 0, 6) === '<?xml ') {
                $xmlarr = xmlize($response);
                $s3errorcode = $xmlarr['Error']['#']['Code'][0]['#'] ?? null;
                $s3errormessage = $xmlarr['Error']['#']['Message'][0]['#'] ?? null;
                if ($s3errorcode || $s3errormessage) {
                    $errormessage .= ': ' . $s3errorcode . ' - ' . $s3errormessage;
                }
            }

            return new api_exception($errormessage, $httpcode);
        } else {
            // This is a connection error.
            return new api_exception("Can not connect to AWS S3, errno $errno: ". $error, 0);
        }
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
        $result = self::api_call('register', 'POST', $params, null, false);
        if (!empty($result['apikey'])) {
            self::set_api_key($result['apikey']);
        } else {
            // This should never happen, if the http response code is not 200 api_call show throw an exception.
            throw new api_exception('Vault API did not return new API key');
        }
    }

    /**
     * Upload a backup file to the cloud
     *
     * @param site_backup $sitebackup
     * @param string $filepath
     * @param backup_file $backupfile
     */
    public static function upload_backup_file(site_backup $sitebackup, string $filepath, backup_file $backupfile) {
        $filename = basename($filepath);
        $backupkey = $sitebackup->get_backup_key();
        $contenttype = 'application/zip';

        $sitebackup->add_to_log('Uploading file '.$filename.' ('.display_size($backupfile->filesize).')...');

        $result = self::api_call("backups/$backupkey/upload/$filename", 'post', ['contenttype' => $contenttype], $sitebackup);
        $s3url = $result['uploadurl'] ?? null;

        // Make sure the returned URL is in fact an AWS S3 pre-signed URL, and we send the encryption key only to AWS.
        if (!preg_match('|^https://[^/]+\\.s3\\.amazonaws\\.com/|', $s3url)) {
            // TODO string?
            throw new \moodle_exception('Vault API did not return a valid upload link '.$filename);
        }

        $passphrase = 'password'; // TODO do not hardcode.
        $options = [
            'CURLOPT_TIMEOUT' => constants::REQUEST_S3_TIMEOUT,
            'CURLOPT_HTTPHEADER' => array_merge(self::prepare_s3_headers($passphrase), ["Content-type: $contenttype"]),
            'CURLOPT_RETURNTRANSFER' => 1,
            'CURLOPT_USERPWD' => '',
        ];

        for ($i = 0; $i < constants::REQUEST_S3_RETRIES; $i++) {
            $curl = new \curl();
            $res = $curl->put($s3url, ['file' => $filepath], $options);
            if ($curl->errno || (($curl->get_info()['http_code'] ?? 0) != 200)) {
                $s3exception = self::prepare_s3_exception($curl, $res);
                $sitebackup->add_to_log("Error uploading file $filename (attempt ".($i + 1)."/".
                    constants::REQUEST_S3_RETRIES."): ".$s3exception->getMessage(), constants::LOGLEVEL_WARNING);
                if ($i == constants::REQUEST_S3_RETRIES - 1) {
                    throw $s3exception;
                }
            } else {
                break;
            }
        }

        self::api_call("backups/$backupkey/uploadcompleted/$filename", 'post',
            ['origsize' => $backupfile->origsize], $sitebackup);
        $sitebackup->add_to_log('...done');
    }

    /**
     * Validate API key
     *
     * @param string $apikey
     * @return bool
     */
    public static function validate_api_key(string $apikey) {
        if ($apikey === 'phpunit' && defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
            return true;
        }
        try {
            $result = self::api_call('backups', 'GET', [], null, true, $apikey);
        } catch (api_exception $e) {
            if ($e->getCode() == 401) {
                return false;
            }
            throw $e;
        }
        return true;
    }

    /**
     * Get a list of all remote backups for this api key
     *
     * @param bool $usecache
     * @return remote_backup[]
     * @throws api_exception
     */
    public static function get_remote_backups(bool $usecache = true): array {
        $conf = $usecache ? self::get_config('cachedremotebackups') : null;
        if ($conf !== null) {
            $records = json_decode($conf, true);
        } else {
            $apiresult = self::api_call('backups', 'GET', []);
            self::store_config('cachedremotebackupstime', time());
            $records = $apiresult['backups'];
            self::store_config('cachedremotebackups', json_encode($apiresult['backups']));
        }
        $backups = [];
        foreach ($records as $record) {
            $backup = new remote_backup($record);
            $backups[$backup->backupkey] = $backup;
        }
        uasort($backups, function($a, $b) {
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
        try {
            $result = self::api_call("backups/{$backupkey}", 'GET');
        } catch (api_exception $t) {
            if ($t->getCode() == 404) {
                throw new api_exception("Backup with the key {$backupkey} is no longer avaialable", 404, $t);
            } else {
                throw $t;
            }
        }
        if (isset($withstatus) && $result['status'] !== $withstatus) {
            if ($result['status'] === constants::STATUS_INPROGRESS) {
                $error = "Backup with the key {$backupkey} has not finished yet";
            } else if ($result['status'] === constants::STATUS_FAILED) {
                $error = "Backup with the key {$backupkey} has failed";
            } else {
                $error = "Backup with the key {$backupkey} has a wrong status";
            }
            throw new api_exception($error, 404);
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
        return "[".userdate($timestamp, get_string('strftimedatetimeshort', 'core_langconfig'))."]";
    }

    /**
     * Download backup file
     *
     * @param string $backupkey
     * @param string $filepath
     * @param logger|null $logger
     * @return void
     */
    public static function download_backup_file(string $backupkey, string $filepath, ?logger $logger = null) {
        $filename = basename($filepath);
        if ($logger) {
            $logger->add_to_log("Downloading file $filename ...");
        }
        $result = self::api_call("backups/$backupkey/download/$filename", 'get', [], $logger);
        $s3url = $result['downloadurl'] ?? null;

        // Make sure the returned URL is in fact an AWS S3 pre-signed URL, and we send the encryption key only to AWS.
        if (!preg_match('|^https://[^/]+\\.s3\\.amazonaws\\.com/|', $s3url)) {
            throw new \moodle_exception('Vault API did not return a valid download link for '.$filename.
                ': '.$s3url);
        }

        $passphrase = 'password'; // TODO do not hardcode.
        $options = [
            'CURLOPT_TIMEOUT' => constants::REQUEST_S3_TIMEOUT,
            'CURLOPT_HTTPHEADER' => self::prepare_s3_headers($passphrase),
            'CURLOPT_RETURNTRANSFER' => 1,
        ];

        for ($i = 0; $i < constants::REQUEST_S3_RETRIES; $i++) {
            $curl = new \curl();
            if ((defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
                // Unfortunately curl::download_one does not process 'file' option correctly if response is mocked.
                $res = file_put_contents($filepath, $curl->get($s3url));
            } else {
                $file = fopen($filepath, 'w');
                $res = $curl->download_one($s3url, [], $options + ['file' => $file]);
                fclose($file);
            }

            if ($curl->errno || (($curl->get_info()['http_code'] ?? 0) != 200)) {
                $s3exception = self::prepare_s3_exception($curl, filesize($filepath) < 5000 ? file_get_contents($filepath) : '');
                if ($logger) {
                    $logger->add_to_log("Error downloading file $filename (attempt ".($i + 1)."/".
                        constants::REQUEST_S3_RETRIES."): ".$s3exception->getMessage(), constants::LOGLEVEL_WARNING);
                }
                unlink($filepath);
                if ($i == constants::REQUEST_S3_RETRIES - 1) {
                    throw $s3exception;
                }
            } else {
                break;
            }
        }

        if ($logger) {
            $logger->add_to_log('...done');
        }
    }

    /**
     * Prepare encryption headers for S3
     *
     * @param string $passphrase
     * @return array
     */
    protected static function prepare_s3_headers(string $passphrase): array {
        $key = hash('sha256', $passphrase, true);
        $encodedkey = base64_encode($key);
        $encodedmd5 = base64_encode(md5($key, true));
        return [
            "x-amz-server-side-encryption-customer-algorithm: AES256",
            "x-amz-server-side-encryption-customer-key: ". $encodedkey,
            "x-amz-server-side-encryption-customer-key-MD5: ". $encodedmd5,
        ];
    }

    /**
     * Request backup key
     *
     * @param array $info
     * @return string
     * @throws \moodle_exception
     */
    public static function request_new_backup_key(array $info): string {
        $res = self::api_call('backups', 'PUT', $info);
        if (empty($res['backupkey'])) {
            throw new \moodle_exception('Server returned no data');
        }
        return $res['backupkey'];
    }

    /**
     * Update backup status and/or add info
     *
     * @param string $backupkey
     * @param array|null $info
     * @param string|null $status
     * @return void
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public static function update_backup(string $backupkey, ?array $info = [], ?string $status = null) {
        $params = ($status ? ['status' => $status] : []) + ($info ? ['info' => $info] : []);
        if (!$params) {
            return;
        }
        self::api_call("backups/{$backupkey}", 'PATCH', $params);
    }
}
