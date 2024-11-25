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

namespace tool_vault\local\helpers;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/lib/filelib.php');

/**
 * Additional functionality for the core \curl class needed in tool_vault when sending requests to storage providers
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class curl extends \curl {

    /** @var string */
    protected $returnheaders = '';

    /**
     * Constructor
     *
     * When sending presigned requests to the storage provider, do not verify blocked urls/ports
     *
     * @param mixed $settings
     */
    public function __construct($settings = []) {
        $settings['ignoresecurity'] = true;
        parent::__construct($settings);
    }

    /**
     * Http PUT request to upload a file or a part of the file
     *
     * @param string $url
     * @param string $file
     * @param int $offset
     * @param int $size
     * @param array $options
     * @return mixed
     */
    public function put_file_part($url, $file, $offset, $size, array $options = []) {
        $fp = fopen($file, 'r');
        fseek($fp, $offset);
        $options['CURLOPT_PUT'] = 1;
        $options['CURLOPT_INFILESIZE'] = $size;
        $options['CURLOPT_INFILE'] = $fp;
        $options['CURLOPT_RETURNTRANSFER'] = 1;
        $options['CURLOPT_HEADER'] = 1;
        $options['CURLOPT_HTTPAUTH'] = CURLAUTH_NONE;

        $ret = $this->request($url, $options);
        fclose($fp);
        return $this->extract_headers($ret);
    }

    /**
     * Extracts headers from the response and stores them in the protected property
     *
     * @param string|null $ret
     * @return string
     */
    protected function extract_headers($ret) {
        $headersize = isset($this->get_info()['header_size']) ? $this->get_info()['header_size'] : 0;
        $this->returnheaders = substr((string)$ret, 0, $headersize);
        return substr((string)$ret, $headersize);
    }

    /**
     * Returns response headers
     *
     * @return string
     */
    public function get_response_headers() {
        return $this->returnheaders;
    }

    /**
     * Performs POST request
     *
     * @param string $url
     * @param mixed $params
     * @param array $options
     * @return string
     */
    public function post($url, $params = '', $options = []) {
        $options += ['CURLOPT_RETURNTRANSFER' => 1, 'CURLOPT_HEADER' => 1, 'CURLOPT_HTTPAUTH' => CURLAUTH_NONE];
        $res = parent::post($url, $params, $options);
        return $this->extract_headers($res);
    }

    /**
     * Did the last request return error or wrong http_code
     *
     * @return bool
     */
    public function request_failed() {
        $info = $this->get_info() + ['http_code' => 0];
        return !empty($this->errno) || ($info['http_code'] != 200);
    }

    /**
     * Downloads one file
     *
     * @param string $url
     * @param mixed $params
     * @param array $options
     * @return mixed
     */
    public function download_one($url, $params, $options = []) {
        if ((defined('PHPUNIT_TEST') && PHPUNIT_TEST) && !empty($options['file'])) {
            // Parent method curl::download_one does not process 'file' option correctly if response is mocked.
            fwrite($options['file'], $this->get($url, $params, $options));
            return true;
        } else {
            return parent::download_one($url, $params, $options);
        }
    }

    /**
     * HTTP PUT method
     *
     * @param string $url
     * @param array $params
     * @param array $options
     * @return ?string
     */
    public function put($url, $params = [], $options = []) {
        $file = '';
        $fp = false;
        if (isset($params['file'])) {
            $file = $params['file'];
            if (is_file($file)) {
                $fp   = fopen($file, 'r');
                $size = filesize($file);
                $options['CURLOPT_PUT']        = 1;
                $options['CURLOPT_INFILESIZE'] = $size;
                $options['CURLOPT_INFILE']     = $fp;
            } else {
                return null;
            }
            if (!isset($this->options['CURLOPT_USERPWD'])) {
                $this->setopt(array('CURLOPT_USERPWD' => 'anonymous: noreply@moodle.org'));
            }
        } else {
            $options['CURLOPT_CUSTOMREQUEST'] = 'PUT';
            $options['CURLOPT_POSTFIELDS'] = $params;
        }

        $ret = $this->request($url, $options);
        if ($fp !== false) {
            fclose($fp);
        }
        return $ret;
    }

    /**
     * HTTP PATCH method
     *
     * @param string $url
     * @param array|string $params
     * @param array $options
     * @return string
     */
    public function patch($url, $params = '', $options = array()) {
        $options['CURLOPT_CUSTOMREQUEST'] = 'PATCH';
        if (is_array($params)) {
            $_tmp_file_post_params = array();
            foreach ($params as $key => $value) {
                if ($value instanceof stored_file) {
                    $value->add_to_curl_request($this, $key);
                } else {
                    $_tmp_file_post_params[$key] = $value;
                }
            }
            $options['CURLOPT_POSTFIELDS'] = $_tmp_file_post_params;
            unset($_tmp_file_post_params);
        } else {
            // The variable $params is the raw post data.
            $options['CURLOPT_POSTFIELDS'] = $params;
        }
        return $this->request($url, $options);
    }
}
