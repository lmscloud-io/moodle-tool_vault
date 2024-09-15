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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_vault;

use tool_vault\local\exceptions\api_exception;
use tool_vault\local\helpers\tempfiles;
use tool_vault\local\logger;
use tool_vault\local\models\backup_file;
use tool_vault\local\models\operation_model;
use tool_vault\local\models\remote_backup;
use tool_vault\local\operations\operation_base;

/**
 * Main api
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /** @var string */
    const FRONTENDURL = 'https://lmsvault.io';

    /**
     * Get a value from the special plugin config
     *
     * @param string $name
     * @return string|null
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
     * Get a value from the special plugin config as a list of unique values
     *
     * @param string $name
     * @return string[]
     */
    public static function get_setting_array(string $name): array {
        global $DB, $CFG;
        $values = preg_split('/[\\s,]+/',
            trim(strtolower(get_config('tool_vault', $name) ?? '')), -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_unique($values));
    }

    /**
     * Get a value from the special plugin config as a list of unique values
     *
     * @param string $name
     * @return bool
     */
    public static function get_setting_checkbox(string $name): bool {
        global $DB, $CFG;
        return (bool)get_config('tool_vault', $name);
    }

    /**
     * Frontend URL
     * @return string
     */
    public static function get_frontend_url() {
        return self::get_config('frontendurl') ?: self::FRONTENDURL;
    }

    /**
     * API URL
     * @return string
     */
    public static function get_api_url() {
        if (((defined('PHPUNIT_TEST') && PHPUNIT_TEST)
                || defined('BEHAT_SITE_RUNNING') || defined('BEHAT_TEST'))
            && (defined('TOOL_VAULT_TEST_API_URL') && !empty(TOOL_VAULT_TEST_API_URL))) {
            return TOOL_VAULT_TEST_API_URL;
        }
        return self::get_config('apiurl') ?: preg_replace('|^(https?://)|', '\1v1.api.', self::FRONTENDURL);
    }

    /**
     * Get currently stored API key
     *
     * @return string|null
     */
    public static function get_api_key() {
        return self::get_config('apikey');
    }

    /**
     * Returns site id, generate if not present
     *
     * @return string
     */
    public static function get_site_id(): string {
        $siteid = self::get_config('siteid');
        if (!$siteid) {
            $siteid = random_string(32);
            self::store_config('siteid', $siteid);
        }
        return $siteid;
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
        return self::get_setting_checkbox('allowrestore');
    }

    /**
     * Store a value in the special plugin config (not included in backups)
     *
     * @param string $name
     * @param string|null $value
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
     * Are backup/restores available from CLI only
     *
     * @return bool
     */
    public static function is_cli_only(): bool {
        return self::get_setting_checkbox('clionly');
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
            $headers[] = 'X-Vault-Siteid: ' . self::get_site_id();
            $headers[] = 'X-Vault-Siteurl: ' . $CFG->wwwroot;
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

        for ($i = 0; $i < constants::REQUEST_API_RETRIES; $i++) {
            $curl = new \curl();
            $curl->setHeader($headers);
            $rv = $curl->$method($url, $params, $options);
            $httpcode = (int)($curl->get_info()['http_code'] ?? 0);
            if ($curl->errno || $httpcode != 200) {
                $apiexception = self::prepare_api_exception($curl, $rv, strtoupper($method) . ' ' . $url);
                if ($httpcode || $i == constants::REQUEST_API_RETRIES - 1) {
                    // Non-zero httpcode means that there was an actual error coming from server (Unauthorized, Forbidden, etc).
                    // In case of zero httpcode we will retry the request up to certain number of times.
                    throw $apiexception;
                }
                if ($logger) {
                    $logger->add_to_log($apiexception->getMessage() . " (attempt ".($i + 1)."/".
                        constants::REQUEST_API_RETRIES.")", constants::LOGLEVEL_WARNING);
                }
            } else {
                break;
            }
        }

        return json_decode($rv, true);
    }

    /**
     * Prepares an exception from Vault API request curl
     *
     * @param \curl $curl
     * @param string|null $response response from Vault API, usually JSON
     * @param string $url URL of the request (used in the error message)
     * @return api_exception
     */
    protected static function prepare_api_exception(\curl $curl, ?string $response, string $url): api_exception {
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
            // Also, sometimes randomly Moodle reports that URL is blocked.
            return new api_exception("Can not connect to Vault API while requesting $url, errno $errno: ". $error, 0);
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
        $encryptionkey = $sitebackup->get_model()->get_details()['encryptionkey'] ?? '';

        // Make sure the returned URL is in fact an AWS S3 pre-signed URL, and we send the encryption key only to AWS.
        if ($encryptionkey && !preg_match('|^https://[^/]+\\.s3\\.amazonaws\\.com/|', $s3url)) {
            throw new \moodle_exception('error_invaliduploadlink', 'tool_vault', '', $filename);
        }

        $options = [
            'CURLOPT_TIMEOUT' => constants::REQUEST_S3_TIMEOUT,
            'CURLOPT_HTTPHEADER' => array_merge(self::prepare_s3_headers($encryptionkey),
                $result['uploadheaders'] ?? []),
            'CURLOPT_RETURNTRANSFER' => 1,
            'CURLOPT_HTTPAUTH' => CURLAUTH_NONE,
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
    public static function validate_api_key(string $apikey): bool {
        if ($apikey === 'phpunit' && defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
            return true;
        }
        try {
            self::api_call('validateapikey', 'GET', [], null, true, $apikey);
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
            if ($backup->status === constants::STATUS_FINISHED) {
                $backups[$backup->backupkey] = $backup;
            }
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
        return self::get_config('cachedremotebackupstime') ?? 0;
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
                throw new api_exception(get_string('error_backupnotavailable', 'tool_vault', $backupkey), 404, $t);
            } else {
                throw $t;
            }
        }
        if (isset($withstatus) && $result['status'] !== $withstatus) {
            if ($result['status'] === constants::STATUS_INPROGRESS) {
                $error = get_string('error_backupnotfinished', 'tool_vault', $backupkey);
            } else if ($result['status'] === constants::STATUS_FAILED) {
                $error = get_string('error_backupfailed', 'tool_vault', $backupkey);
            } else {
                $error = get_string('error_backuphaswrongstatus', 'tool_vault', $backupkey);
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
     * Validates that the backup exists, restores allowed and the passphrase is correct
     *
     * @param string $backupkey
     * @param string $passphrase
     * @throws api_exception
     */
    public static function validate_backup(string $backupkey, string $passphrase) {
        $result = self::api_call("backups/$backupkey/validate", 'get',
            ['vaultversion' => get_config('tool_vault', 'version')]);
        $s3url = $result['downloadurl'] ?? null;
        $encrypted = $result['encrypted'] ?? false;

        if (!$encrypted) {
            // No need to validate the passphrase.
            return;
        }

        // Make sure the returned URL is in fact an AWS S3 pre-signed URL, and we send the encryption key only to AWS.
        if (!preg_match('|^https://[^/]+\\.s3\\.amazonaws\\.com/|', $s3url)) {
            throw new \moodle_exception('error_notavalidlink', 'tool_vault', '', s($s3url));
        }

        $encryptionkey = self::prepare_encryption_key($passphrase);
        $options = [
            'CURLOPT_TIMEOUT' => constants::REQUEST_API_TIMEOUT, // Smaller timeout here.
            'CURLOPT_HTTPHEADER' => array_merge(self::prepare_s3_headers($encryptionkey),
                $result['downloadheaders'] ?? []),
            'CURLOPT_HTTPAUTH' => CURLAUTH_NONE,
        ];
        $curl = new \curl();
        // Perform a 'head' request to the pre-signed S3 url to check if the encryption key is correct.
        $res = $curl->head($s3url, $options);
        $httpcode = $curl->get_info()['http_code'] ?? 0;
        if ($httpcode == 403) {
            throw new api_exception(get_string('error_passphrasewrong', 'tool_vault'));
        }
        if ($curl->errno || ($httpcode != 200)) {
            throw self::prepare_s3_exception($curl, $res);
        }
    }

    /**
     * Download backup file
     *
     * @param operation_model $model
     * @param string $filepath
     * @param logger|null $logger
     * @return void
     */
    public static function download_backup_file(operation_model $model, string $filepath, ?logger $logger = null) {
        $backupkey = $model->backupkey;
        $restorekey = $model->get_details()['restorekey'] ?? '';
        $filename = basename($filepath);
        if ($logger) {
            $logger->add_to_log("Downloading file $filename ...");
        }
        $result = self::api_call("restores/$restorekey/download/$filename", 'get', [], $logger);
        $s3url = $result['downloadurl'] ?? null;
        $encrypted = $result['encrypted'] ?? false;

        // Make sure the returned URL is in fact an AWS S3 pre-signed URL, and we send the encryption key only to AWS.
        if ($encrypted && !preg_match('|^https://[^/]+\\.s3\\.amazonaws\\.com/|', $s3url)) {
            throw new \moodle_exception('error_invaliddownloadlink', 'tool_vault', '',
                (object)['filename' => $filename, 'url' => $s3url]);
        }

        $encryptionkey = $encrypted ? ($model->get_details()['encryptionkey'] ?? '') : '';
        $options = [
            'CURLOPT_TIMEOUT' => constants::REQUEST_S3_TIMEOUT,
            'CURLOPT_HTTPHEADER' => array_merge(self::prepare_s3_headers($encryptionkey),
                $result['downloadheaders'] ?? []),
            'CURLOPT_RETURNTRANSFER' => 1,
            'CURLOPT_HTTPAUTH' => CURLAUTH_NONE,
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
                $s3exception = self::prepare_s3_exception($curl, filesize($filepath) < 10000 ? file_get_contents($filepath) : '');
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
     * Prepare encryption key from the passphrase
     *
     * @param string|null $passphrase
     * @return string
     */
    public static function prepare_encryption_key(?string $passphrase): string {
        return strlen($passphrase) ? base64_encode(hash('sha256', $passphrase, true)) : '';
    }

    /**
     * Prepare encryption headers for S3
     *
     * @param string $key encryption key generated as base64_encode(hash('sha256', $passphrase, true))
     * @return array
     */
    protected static function prepare_s3_headers(string $key): array {
        if (!strlen($key)) {
            return [];
        }
        $encodedmd5 = base64_encode(md5(base64_decode($key), true));
        return [
            "x-amz-server-side-encryption-customer-algorithm: AES256",
            "x-amz-server-side-encryption-customer-key: ". $key,
            "x-amz-server-side-encryption-customer-key-MD5: ". $encodedmd5,
            "Authorization: ",
        ];
    }

    /**
     * When scheduling new backup check if server allows it
     *
     * @return mixed
     * @throws \moodle_exception
     */
    public static function precheck_backup_allowed() {
        $info = ['precheckonly' => 1, 'vaultversion' => get_config('tool_vault', 'version')];
        return self::api_call('backups', 'PUT', $info);
    }

    /**
     * Additional details to pass to server when starting backup or restore
     *
     * @return array
     */
    protected static function extra_details(): array {
        global $CFG, $DB;
        return [
            'vaultversion' => get_config('tool_vault', 'version'),
            'vaultenv' => "PHP ".PHP_VERSION." / Moodle {$CFG->release} / DB ".$DB->get_dbfamily()
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
        $info += self::extra_details();
        $res = self::api_call('backups', 'PUT', $info);
        if (empty($res['backupkey'])) {
            throw new \moodle_exception('error_serverreturnednodata', 'tool_vault');
        }
        return $res['backupkey'];
    }

    /**
     * Request restore key
     *
     * @param array $info
     * @return string
     * @throws \moodle_exception
     */
    public static function request_new_restore_key(array $info): string {
        $info += self::extra_details();
        $res = self::api_call('restores', 'PUT', $info);
        if (empty($res['restorekey'])) {
            throw new \moodle_exception('error_serverreturnednodata', 'tool_vault');
        }
        return $res['restorekey'];
    }

    /**
     * Update backup status and/or add info
     *
     * @param string $backupkey
     * @param array|null $info
     * @param string|null $status
     * @return void
     */
    public static function update_backup(string $backupkey, ?array $info = [], ?string $status = null) {
        $params = ($status ? ['status' => $status] : []) + ($info ? ['info' => $info] : []);
        if (!$params) {
            return;
        }
        self::api_call("backups/{$backupkey}", 'PATCH', $params);
    }

    /**
     * Update restore status and/or add info
     *
     * @param string $restorekey
     * @param array|null $info
     * @param string|null $status
     * @return void
     */
    public static function update_restore(string $restorekey, ?array $info = [], ?string $status = null) {
        $params = ($status ? ['status' => $status] : []) + ($info ? ['info' => $info] : []);
        if (!$params) {
            return;
        }
        self::api_call("restores/{$restorekey}", 'PATCH', $params);
    }

    /**
     * Updates restore on the server but never throws any errors
     *
     * This function is often called in the end of the restore process and the failure to update remote restore
     * should not raise an exception and fail the local restore.
     *
     * @param string $restorekey
     * @param array|null $info
     * @param string|null $status
     * @return bool
     */
    public static function update_restore_ignoring_errors(string $restorekey, ?array $info = [], ?string $status = null): bool {
        // One of the reason for the failed backup - impossible to communicate with the API,
        // in which case this request will also fail.
        try {
            self::update_restore($restorekey, $info, $status);
        } catch (\Throwable $tapi) {
            // If for some reason we could not mark remote restore as finished.
            return false;
        }
        return true;
    }

    /**
     * Report an error in the backup precheck to the server
     *
     * @param \Throwable $t
     * @return void
     */
    public static function report_error(\Throwable $t): void {
        try {
            self::api_call("reporterror", 'POST', ['faileddetails' => operation_base::get_error_message_for_server($t)]);
        } catch (\Throwable $tapi) {
            // Ignore connection or other server errors.
            return;
        }
    }

    /**
     * Should site be in maintenance mode
     *
     * @return bool
     */
    public static function is_maintenance_mode(): bool {
        try {
            $records = operation_model::get_active_processes(false);
            if ($records) {
                $records = array_filter($records, function(operation_model $record) {
                    return $record->status == constants::STATUS_INPROGRESS;
                });
            }
            return !empty($records);
        } catch (\Throwable $t) {
            if (get_config('tool_vault', 'version')) {
                debugging($t->getMessage(), DEBUG_DEVELOPER);
            }
            return false;
        }
    }
}
