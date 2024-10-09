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

/**
 * Class plugincode
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugincode {

    /**
     * Get list of directories with add-on plugins (do not list the same dir twice for the subplugins)
     *
     * @return array
     */
    public static function get_addon_directories_list() {
        $excludedplugins = siteinfo::get_excluded_plugins_backup();
        $pluginlistfull = siteinfo::get_plugins_list_full();
        $pluginlist = array_diff_key($pluginlistfull, array_fill_keys($excludedplugins, true));
        unset($pluginlist['tool_vault']);

        $paths = [];
        foreach ($pluginlist as $pluginname => $plugininfo) {
            if (!empty($plugininfo['isaddon'])) {
                $parent = empty($plugininfo['parent']) ? null : $pluginlistfull[$plugininfo['parent']];
                if (!$parent || empty($parent['isaddon'])) {
                    $paths[] = $plugininfo['path'];
                }
            }
        }

        return $paths;
    }

    /**
     * Get number of add-on plugins (except tool_vault)
     *
     * @return int
     */
    public static function get_addon_plugins_count(): int {
        $excludedplugins = siteinfo::get_excluded_plugins_backup();
        $pluginlistfull = siteinfo::get_plugins_list_full();
        $pluginlist = array_diff_key($pluginlistfull, array_fill_keys($excludedplugins, true));
        unset($pluginlist['tool_vault']);

        $count = 0;
        foreach ($pluginlist as $pluginname => $plugininfo) {
            if (!empty($plugininfo['isaddon'])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get size of a single directory
     *
     * @param string $path
     * @return int
     */
    public static function get_directory_size(string $path): int {
        $bytestotal = 0;
        $path = realpath($path);
        if ($path !== false && $path != '' && file_exists($path)) {
            foreach (new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $object) {
                // TODO check that $object->getPath() does not contain /.git/ etc. Or find a way to exclude them from iterator.
                try {
                    $bytestotal += $object->getSize();
                // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
                } catch (\Throwable $e) {
                    // Means we will not be able to copy this file anyway.
                }
            }
        }
        return $bytestotal;
    }

    /**
     * Get total size of all add-on plugins code
     *
     * @return int
     */
    public static function get_total_addon_size(): int {
        global $CFG;
        $paths = self::get_addon_directories_list();
        $total = 0;
        foreach ($paths as $path) {
            $total += self::get_directory_size($CFG->dirroot . '/' . $path);
        }
        return $total;
    }

    /**
     * What should be an absolute (file system) path to the plugin
     *
     * @param string $pluginname
     * @return string
     */
    public static function guess_plugin_path(string $pluginname): string {
        $dir = \core_component::get_component_directory($pluginname);
        if ($dir) {
            return $dir;
        }
        [$ptype, $pname] = \core_component::normalize_component($pluginname);
        return \core_component::get_plugin_types()[$ptype] . '/' . $pname;
    }

    /**
     * What should be a relative path to the plugin
     *
     * @param string $pluginname
     * @return array|string|null
     */
    public static function guess_plugin_path_relative(string $pluginname): string {
        global $CFG;
        $dir = self::guess_plugin_path($pluginname);
        return preg_replace('/^'.preg_quote("{$CFG->dirroot}/", '/').'/', "", $dir);
    }

    /**
     * Can tool_vault create/override plugin folder
     *
     * @param string $pluginname
     * @return bool
     */
    public static function can_write_to_plugin_dir(string $pluginname): bool {
        $dir = self::guess_plugin_path($pluginname);
        if (file_exists($dir)) {
            if (!is_writable($dir)) {
                return false;
            }
            // TODO check if we can delete every file and subfolder from this directory.
            // see lib/classes/plugin_manager.php is_directory_removable() .
            return true;
        } else {
            return is_writable(dirname($dir));
        }
    }

    /**
     * Check moodle.org response for errors, return json-decoded response
     *
     * @param \curl $curl
     * @param string|null $response
     * @throws \moodle_exception
     * @return array
     */
    protected static function prepare_moodle_org_response(\curl $curl, ?string $response): array {
        $curlerrno = $curl->get_errno();
        if (!empty($curlerrno)) {
            $error = get_string('err_response_curl', 'core_plugin') . ' ' .
                $curlerrno . ': ' . $curl->error;
            throw new \moodle_exception($error);
        }
        $curlinfo = $curl->get_info();
        if ($curlinfo['http_code'] != 200) {
            $error = get_string('err_response_http_code', 'core_plugin') . $curlinfo['http_code'];
            throw new \moodle_exception($error);
        }

        $response = json_decode($response, true);

        if (empty($response['status']) || $response['status'] !== 'OK') {
            throw new \moodle_exception('Unrecognised response');
        }

        return $response ?? [];
    }

    /**
     * Check if plugin is available on moodle.org
     *
     * @param string $pluginname either only plugin name or "pluginname @ version" (without spaces)
     * @throws \moodle_exception
     * @return array
     */
    public static function check_on_moodle_org(string $pluginname): array {
        global $CFG;
        require_once($CFG->libdir.'/filelib.php');

        $url = 'https://download.moodle.org/api/1.3/pluginfo.php';
        $params = ['plugin' => $pluginname, 'format' => 'json', 'minversion' => 0, 'branch' => moodle_major_version()];
        $options = ['CURLOPT_SSL_VERIFYHOST' => 2, 'CURLOPT_SSL_VERIFYPEER' => true];

        $curl = new \curl(['proxy' => true]);
        $response = $curl->get($url, $params, $options);
        $response = self::prepare_moodle_org_response($curl, $response);

        if (empty($response['pluginfo']['version']['version'])) {
            throw new \moodle_exception('No available version');
        }
        $shortinfo = [
            'version' => $response['pluginfo']['version']['version'],
            'release' => $response['pluginfo']['version']['release'],
            'downloadurl' => $response['pluginfo']['version']['downloadurl'],
            'supportedmoodles' => array_column($response['pluginfo']['version']['supportedmoodles'], 'release'),
            // Also available: name, source, doc, bugs, discussion...
            // Also available under 'version': maturity, downloadmd5, vscsystem...
        ];
        return $shortinfo;
    }

    /**
     * Detect the root folder in the zip
     *
     * @param array $filelist list of files from the zip
     * @throws \moodle_exception
     * @return string
     */
    protected static function find_root_folder(array &$filelist): string {
        $found = null;
        foreach ($filelist as $file => $unused) {
            $parts = explode('/', $file);
            if (count($parts) > 1) {
                if ($found === null || $found === $parts[0]) {
                    $found = $parts[0];
                    continue;
                }
            }
            throw new \moodle_exception('validationmsg_onedir', 'core_plugin');
        }
        return $found;
    }

    /**
     * Copy plugin files from one folder to another
     *
     * @param string $sourcepath path where zip was extracted to
     * @param string $pluginpath path where plugin needs to be installed
     * @param array $files list of files - content of the zip file
     * @param string|null $rootfolder rootfolder to strip from the filenames in $files
     * @return void
     */
    protected static function copy_plugin_files(string $sourcepath, string $pluginpath,
            array $files, ?string $rootfolder = null): void {
        global $CFG;
        $dirpermissions = file_exists($pluginpath) ? fileperms($pluginpath) : fileperms(dirname($pluginpath));
        $filepermissions = $dirpermissions & 0666;
        // First try to remove evreything from the target dir.
        remove_dir($pluginpath, true);
        $rootfolder = $rootfolder ?? basename($pluginpath);

        // Copy files one by one and create subdirectories when needed.
        foreach ($files as $file => $status) {
            if ($status) {
                $source = $sourcepath.'/'.$file;
                $target = $pluginpath.'/'.substr($file, strlen($rootfolder) + 1);

                if (!is_dir($source)) {
                    if (!is_dir(dirname($target))) {
                        mkdir(dirname($target), $dirpermissions, true);
                    }
                    rename($source, $target);
                    @chmod($target, $filepermissions);
                }
            }
        }
    }

    /**
     * Install a plugin from moodle.org/plugins
     *
     * @param string $url
     * @param string $pluginname either just the name or 'name @ version' (without spaces)
     * @param bool $dryrun
     * @return void
     */
    public static function install_addon_from_moodleorg(string $url, string $pluginname, bool $dryrun = false) {
        global $CFG;
        // Download zip.
        $tempdir = make_request_directory();
        $zipfile = $tempdir.'/plugin.zip';
        $headers = null;
        $postdata = null;
        $fullresponse = false;
        $timeout = 300;
        $connecttimeout = 20;
        $skipcertverify = false;
        $tofile = $zipfile;
        $calctimeout = false;

        download_file_content($url, $headers, $postdata, $fullresponse, $timeout,
            $connecttimeout, $skipcertverify, $tofile, $calctimeout);

        // Extract zip into temp directory.
        $tmp = make_request_directory();

        $fp = get_file_packer('application/zip');
        $files = $fp->extract_to_pathname($zipfile, $tmp);

        // Check version.php for consistency - that it has component that matches $pluginname, version, etc.
        try {
            $validator = plugin_validator::validate($tmp, $files, ['component' => $pluginname]);
        } catch (\moodle_exception $e) {
            mtrace("ERROR. ".$e->getMessage());
            return;
        }

        $pluginpath = self::guess_plugin_path($pluginname);
        $pluginpathrel = self::guess_plugin_path_relative($pluginname);
        $component = $validator->get_component();
        $version = $validator->get_version();
        if (!$dryrun) {
            self::copy_plugin_files($tmp, $pluginpath, $files);
            mtrace("Plugin $component with version $version was installed into $pluginpathrel");
        } else {
            mtrace("Plugin $component with version $version can be installed into $pluginpathrel");
        }
    }
}
