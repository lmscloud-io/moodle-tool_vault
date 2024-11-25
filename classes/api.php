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
use tool_vault\local\helpers\curl;
use tool_vault\local\helpers\dbops;
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
    public static function get_config($name) {
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
    public static function get_setting_array($name) {
        global $DB, $CFG;
        $values = preg_split('/[\\s,]+/',
            trim(strtolower(get_config('tool_vault', $name) ?: '')), -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_unique($values));
    }

    /**
     * Get a value from the special plugin config as a list of unique values
     *
     * @param string $name
     * @return bool
     */
    public static function get_setting_checkbox($name) {
        global $DB, $CFG;
        return (bool)get_config('tool_vault', $name);
    }

    /**
     * Frontend URL
     * @return string
     */
    public static function get_frontend_url() {
        if (((defined('PHPUNIT_TEST') && PHPUNIT_TEST)
                || defined('BEHAT_SITE_RUNNING') || defined('BEHAT_TEST'))
            && (defined('TOOL_VAULT_TEST_FRONTEND_URL') && !empty(TOOL_VAULT_TEST_FRONTEND_URL))) {
            return TOOL_VAULT_TEST_FRONTEND_URL;
        }
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
    public static function get_site_id() {
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
    public static function set_api_key($apikey) {
        if ($apikey !== self::get_api_key()) {
            self::store_config('apikey', $apikey);
            self::store_config('cachedremotebackupstime', null);
            self::store_config('cachedremotebackups', null);
        }
    }

    /**
     * Store a value in the special plugin config (not included in backups)
     *
     * @param string $name
     * @param string|null $value
     * @return void
     */
    public static function store_config($name, $value) {
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
    public static function is_registered() {
        $apikey = self::get_api_key();
        return !empty($apikey);
    }

    /**
     * Are backups available from CLI only
     *
     * @return bool
     */
    public static function is_cli_only() {
        return self::get_setting_checkbox('clionly');
    }

    /**
     * Allow backup plugins code
     *
     * @return int -1 never allow; 0 or 1 - display checkbox in backup form not checked/checked by default
     */
    public static function allow_backup_plugincode() {
        $v = get_config('tool_vault', 'backupplugincode');
        return (int)(isset($v) ? $v : -1);
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
    protected static function api_call($endpoint, $method, array $params = [],
                                    $logger = null, $authheader = true, $apikey = null) {
        global $CFG;
        require_once($CFG->dirroot.'/lib/filelib.php');

        $headers = [];
        if ($authheader) {
            $headers[] = 'X-Api-Key: ' . ($apikey ?: self::get_api_key());
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
            $curl = new \tool_vault\local\helpers\curl();
            $curl->setHeader($headers);
            $rv = $curl->$method($url, $params, $options);
            $httpcode = (int)(isset($curl->get_info()['http_code']) ? $curl->get_info()['http_code'] : 0);
            if (!empty($curl->errno) || $httpcode != 200) {
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
    protected static function prepare_api_exception(\curl $curl, $response, $url) {
        $errno = $curl->get_errno();
        $error = $curl->error;
        $httpcode = (int)(isset($curl->get_info()['http_code']) ? $curl->get_info()['http_code'] : 0);
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
    protected static function prepare_s3_exception(\curl $curl, $response) {
        $errno = $curl->get_errno();
        $error = $curl->error;
        $info = $curl->get_info();
        $httpcode = (int)(isset($info['http_code']) ? $info['http_code'] : 0);
        if ($httpcode) {
            // This is an error returned by the server.
            $errormessage = "AWS S3 error ({$httpcode})";

            if (strlen($response) && substr($response, 0, 6) === '<?xml ') {
                $xmlarr = xmlize($response);
                $s3errorcode = isset($xmlarr['Error']['#']['Code'][0]['#']) ? $xmlarr['Error']['#']['Code'][0]['#'] : null;
                $s3errormessage = isset($xmlarr['Error']['#']['Message'][0]['#']) ? $xmlarr['Error']['#']['Message'][0]['#'] : null;
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
     * Upload a file or a part of a file to a presigned url
     *
     * @param \tool_vault\site_backup $sitebackup
     * @param string $s3url
     * @param array $uploadheaders
     * @param string $filepath
     * @param int $filesize
     * @param int $partno number of the file part being uploaded (0-based)
     * @return string
     */
    protected static function upload_file_to_presigned_url(site_backup $sitebackup,
            $s3url,
            array $uploadheaders,
            $filepath,
            $filesize,
            $partno) {
        $filename = basename($filepath);
        $parts = $filesize >= constants::S3_MULTIPART_UPLOAD_THRESHOLD ?
            ceil($filesize / constants::S3_MULTIPART_UPLOAD_PARTSIZE) : 1;

        if ($parts > 1) {
            $size = ($partno == $parts - 1) ? ($filesize % constants::S3_MULTIPART_UPLOAD_PARTSIZE) :
                constants::S3_MULTIPART_UPLOAD_PARTSIZE;
            $sitebackup->add_to_log('Uploading file '.$filename.' ('.display_size($filesize).
                ') using multipart upload: part '.($partno + 1).' of '.$parts.' ('.
                display_size($size).')...');
        } else {
            $size = $filesize;
            $sitebackup->add_to_log('Uploading file '.$filename.' ('.display_size($filesize).')...');
        }

        $options = [
            'CURLOPT_TIMEOUT' => constants::REQUEST_S3_TIMEOUT,
            'CURLOPT_HTTPHEADER' => $uploadheaders,
        ];

        $etag = '';
        $offset = $partno * constants::S3_MULTIPART_UPLOAD_PARTSIZE;
        for ($i = 0; $i < constants::REQUEST_S3_RETRIES; $i++) {
            $curl = new curl();
            $res = $curl->put_file_part($s3url, $filepath, $offset, $size, $options);
            $resheader = $curl->get_response_headers();

            if ($curl->request_failed()) {
                // On error retry several times.
                $s3exception = self::prepare_s3_exception($curl, $res);
                $sitebackup->add_to_log("Error uploading file $filename (attempt ".($i + 1)."/".
                    constants::REQUEST_S3_RETRIES."): ".$s3exception->getMessage(), constants::LOGLEVEL_WARNING);
                if ($i == constants::REQUEST_S3_RETRIES - 1) {
                    throw $s3exception;
                }
                $sitebackup->add_to_log("Retrying");
            } else {
                // On success extract ETag from the response headers.
                if (preg_match('/\nETag: (.+?)\r?\n/', "\n{$resheader}\n", $match)) {
                    $etag = trim($match[1]);
                } else {
                    $sitebackup->add_to_log("Unable to detect 'ETag' header of the uploaded file $filename",
                        constants::LOGLEVEL_WARNING);
                    $etag = 'Unknown';
                }
                break;
            }
        }
        return $etag;
    }

    /**
     * Sends a command initiate multipart upload through the presigned url
     *
     * @param \tool_vault\site_backup $sitebackup
     * @param string $s3url
     * @param array $uploadheaders
     * @throws \tool_vault\local\exceptions\api_exception
     * @return string uploadid
     */
    protected static function initiate_multipart_upload(site_backup $sitebackup, $s3url, array $uploadheaders) {
        $options = [
            'CURLOPT_HTTPHEADER' => $uploadheaders,
            'CURLOPT_HTTPAUTH' => CURLAUTH_NONE,
        ];

        for ($i = 0; $i < constants::REQUEST_S3_RETRIES; $i++) {
            $curl = new curl();
            $res = $curl->post($s3url, '', $options);

            if ($curl->request_failed()) {
                // On error retry several times.
                $s3exception = self::prepare_s3_exception($curl, $res);
                $sitebackup->add_to_log("Error initiating upload (attempt ".($i + 1)."/".
                    constants::REQUEST_S3_RETRIES."): ".$s3exception->getMessage(), constants::LOGLEVEL_WARNING);
                if ($i == constants::REQUEST_S3_RETRIES - 1) {
                    throw $s3exception;
                }
                $sitebackup->add_to_log("Retrying");
            } else {
                break;
            }
        }

        $xmlarr = xmlize($res);
        $uploadid = isset($xmlarr['InitiateMultipartUploadResult']['#']['UploadId'][0]['#']) ?
            $xmlarr['InitiateMultipartUploadResult']['#']['UploadId'][0]['#'] : null;

        if (!$uploadid) {
            throw new api_exception(get_string('error_failedmultipartupload', 'tool_vault', $res));
        }

        return $uploadid;
    }

    /**
     * Upload a backup file to the cloud
     *
     * @param site_backup $sitebackup
     * @param string $filepath
     * @param backup_file $backupfile
     */
    public static function upload_backup_file(site_backup $sitebackup, $filepath, backup_file $backupfile) {
        $filename = basename($filepath);
        $backupkey = $sitebackup->get_backup_key();
        $contenttype = 'application/zip';
        $uploadid = null;

        $details = $sitebackup->get_model()->get_details() + ['encryptionkey' => ''];
        $encryptionkey = $details['encryptionkey'];
        $encryptionheaders = self::prepare_s3_headers($encryptionkey);

        // For large files, we need to request a multipart upload.
        if ($backupfile->filesize >= constants::S3_MULTIPART_UPLOAD_THRESHOLD) {
            // Determine the number of parts we need to upload.
            $parts = ceil($backupfile->filesize / constants::S3_MULTIPART_UPLOAD_PARTSIZE);

            // Get pre-signed URL to initiate multipart upload.
            $result = self::api_call("backups/$backupkey/upload/$filename", 'post',
                ['contenttype' => $contenttype, 'multipart' => ['parts' => $parts]], $sitebackup);
            $s3url = isset($result['multiplarturl']) ? $result['multiplarturl'] : null;

            // Make sure the returned URL is in fact an AWS S3 pre-signed URL, and we send the encryption key only to AWS.
            if ($encryptionkey && !preg_match('|^https://[^/]+\\.s3\\.amazonaws\\.com/|', $s3url)) {
                throw new \moodle_exception('error_invaliduploadlink', 'tool_vault', '', $filename);
            }

            // Get the list of the upload urls.
            $uploadheaders = array_merge($encryptionheaders, isset($result['uploadheaders']) ? $result['uploadheaders'] : []);
            $uploadid = self::initiate_multipart_upload($sitebackup, $s3url, $uploadheaders);
            $result = self::api_call("backups/$backupkey/upload/$filename", 'post',
                ['contenttype' => $contenttype, 'multipart' => ['parts' => $parts, 'uploadid' => $uploadid]], $sitebackup);
            $s3urls = $result['uploadurls'];
        } else {
            $parts = 1;
            $result = self::api_call("backups/$backupkey/upload/$filename", 'post', ['contenttype' => $contenttype], $sitebackup);
            $s3urls = [$result['uploadurl']];
        }

        $etags = [];
        $uploadheaders = array_merge($encryptionheaders, isset($result['uploadheaders']) ? $result['uploadheaders'] : []);
        foreach ($s3urls as $partno => $s3url) {
            // Make sure the returned URL is in fact an AWS S3 pre-signed URL, and we send the encryption key only to AWS.
            if ($encryptionkey && !preg_match('|^https://[^/]+\\.s3\\.amazonaws\\.com/|', $s3url)) {
                throw new \moodle_exception('error_invaliduploadlink', 'tool_vault', '', $filename);
            }
            // Upload the file or a part of the file to the pre-signed URL.
            $etag = self::upload_file_to_presigned_url($sitebackup, $s3url, $uploadheaders,
                $filepath, $backupfile->filesize, $partno);
            $etags[] = $etag;
        }

        $params = ['origsize' => $backupfile->origsize];
        if ($parts > 1) {
            $params['multipart'] = ['UploadId' => $uploadid, 'ETags' => $etags];
        }
        self::api_call("backups/$backupkey/uploadcompleted/$filename", 'post', $params, $sitebackup);
        $sitebackup->add_to_log('...done');
    }

    /**
     * Validate API key
     *
     * @param string $apikey
     * @return bool
     */
    public static function validate_api_key($apikey) {
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
     * Last time we fetched remote backups
     *
     * @return int
     */
    public static function get_remote_backups_time() {
        return self::get_config('cachedremotebackupstime') ?: 0;
    }

    /**
     * Get information about one backup
     *
     * @param string $backupkey
     * @param string|null $withstatus
     * @return remote_backup
     */
    public static function get_remote_backup($backupkey, $withstatus = null) {
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
    public static function format_date_for_logs($timestamp) {
        return "[".userdate($timestamp, get_string('strftimedatetimeshort', 'core_langconfig'))."]";
    }

    /**
     * Validates that the backup exists, restores allowed and the passphrase is correct
     *
     * @param string $backupkey
     * @param string $passphrase
     * @throws api_exception
     */
    public static function validate_backup($backupkey, $passphrase) {
        $result = self::api_call("backups/$backupkey/validate", 'get',
            ['vaultversion' => get_config('tool_vault', 'version')]);
        $result += ['downloadurl' => null, 'encrypted' => false, 'downloadheaders' => []];
        $s3url = $result['downloadurl'];
        $encrypted = $result['encrypted'];

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
                $result['downloadheaders']),
            'CURLOPT_HTTPAUTH' => CURLAUTH_NONE,
        ];
        $curl = new curl();
        // Perform a 'head' request to the pre-signed S3 url to check if the encryption key is correct.
        $res = $curl->head($s3url, $options);
        $httpcode = $curl->get_info()['http_code'] ?: 0;
        if ($httpcode == 403) {
            throw new api_exception(get_string('error_passphrasewrong', 'tool_vault'));
        }
        if ($curl->errno || ($httpcode != 200)) {
            throw self::prepare_s3_exception($curl, $res);
        }
    }

    /**
     * Prepare encryption key from the passphrase
     *
     * @param string|null $passphrase
     * @return string
     */
    public static function prepare_encryption_key($passphrase) {
        return strlen($passphrase) ? base64_encode(hash('sha256', $passphrase, true)) : '';
    }

    /**
     * Prepare encryption headers for S3
     *
     * @param string $key encryption key generated as base64_encode(hash('sha256', $passphrase, true))
     * @return array
     */
    protected static function prepare_s3_headers($key) {
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
    protected static function extra_details() {
        global $CFG, $DB;
        return [
            'vaultversion' => get_config('tool_vault', 'version'),
            'vaultenv' => ["PHP" => PHP_VERSION,
                "Moodle" => $CFG->release,
                "DB" => $DB->get_dbfamily() . ' ' . $DB->get_server_info()['description'],
                "max_execution_time" => ini_get("max_execution_time"),
                "isvaultcli" => defined('TOOL_VAULT_CLI_SCRIPT') && TOOL_VAULT_CLI_SCRIPT,
                "max_allowed_packet" => dbops::get_max_allowed_packet(),
            ],
        ];
    }

    /**
     * Request backup key
     *
     * @param array $info
     * @return string
     * @throws \moodle_exception
     */
    public static function request_new_backup_key(array $info) {
        $info += self::extra_details();
        $res = self::api_call('backups', 'PUT', $info);
        if (empty($res['backupkey'])) {
            throw new \moodle_exception('error_serverreturnednodata', 'tool_vault');
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
     */
    public static function update_backup($backupkey, $info = [], $status = null) {
        $params = ($status ? ['status' => $status] : []) + ($info ? ['info' => $info] : []);
        if (!$params) {
            return;
        }
        self::api_call("backups/{$backupkey}", 'PATCH', $params);
    }

    /**
     * Report an error in the backup precheck to the server
     *
     * @param \Throwable $t
     * @return void
     */
    public static function report_error(\Throwable $t) {
        try {
            self::api_call("reporterror", 'POST', ['faileddetails' => operation_base::get_error_message_for_server($t)]);
        } catch (\Throwable $tapi) {
            // Ignore connection or other server errors.
            return;
        } catch (\Exception $tapi) {
            // Compatibility with PHP < 7.0.
            return;
        }
    }

    /**
     * Should site be in maintenance mode
     *
     * @return bool
     */
    public static function is_maintenance_mode() {
        try {
            $records = operation_model::get_active_processes(false);
            if ($records) {
                $records = array_filter($records, function(operation_model $record) {
                    return $record->status == constants::STATUS_INPROGRESS;
                });
            }
            return !empty($records);
        } catch (\Throwable $t) {
            // If the error happened because tool_vault is not installed yet and the DB table does not exist - ignore it.
            // Otherwise show debugging message.
            if (get_config('tool_vault', 'version')) {
                debugging($t->getMessage(), DEBUG_DEVELOPER);
            }
            return false;
        } catch (\Exception $t) {
            // Compatibility with PHP < 7.0.
            if (get_config('tool_vault', 'version')) {
                debugging($t->getMessage(), DEBUG_DEVELOPER);
            }
            return false;
        }
    }
}
