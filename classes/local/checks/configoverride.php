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

namespace tool_vault\local\checks;

use tool_vault\constants;

/**
 * Check config overrides
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class configoverride extends check_base {

    // TODO. Recommend not to include settings that contain paths to executables.

    /** @var string[] */
    protected $alwaysexcluded = [
        'dbtype', 'dblibrary', 'dbhost', 'dbname', 'dbuser', 'dbpass', 'prefix', 'dboptions',
        'wwwroot', 'dataroot',
        'dirroot', 'libdir',
        'yui2version', 'yui3version', 'yuipatchlevel', 'yuipatchedmodules',
        'phpunit_dataroot', 'phpunit_prefix',
        'behat_wwwroot', 'behat_prefix', 'behat_dataroot', 'behat_dbname', 'behat_dbuser', 'behat_dbpass', 'behat_dbhost',
    ];
    /** @var string[] */
    protected $usuallyexcluded = [
        'supportuserid', 'sessioncookiepath',
    ];
    /** @var string[] */
    protected $usuallyincluded = [
        'supportemail',
    ];

    /**
     * Evaluate check and store results in model details
     */
    public function perform(): void {
        global $CFG;
        list($included, $notincluded) = $this->get_config_overrides_core();
        list($pincluded, $pnotincluded) = $this->get_config_overrides_plugins();
        $this->model->set_details([
            'config_php_settings_included' => $included,
            'config_php_settings_notincluded' => $notincluded,
            'forced_plugin_settings_included' => $pincluded,
            'forced_plugin_settings_notincluded' => $pnotincluded,
        ])->save();
    }

    /**
     * Config overrides
     *
     * @return array[]
     */
    protected function get_config_overrides_core() {
        global $CFG;
        $included = $notincluded = [];
        $defaults = $this->core_config_defaults();
        foreach ($CFG->config_php_settings as $key => $value) {
            if (in_array($key, $this->alwaysexcluded)) {
                continue;
            }
            if (array_key_exists($key, $defaults) && $defaults[$key] === $value) {
                continue;
            }
            if (in_array($key, $this->usuallyincluded)) {
                $included[$key] = $value;
            } else if (in_array($key, $this->usuallyexcluded)) {
                $notincluded[$key] = $value;
            } else if ($this->setting_in_admin_tree($key)) {
                $included[$key] = $value;
            } else {
                $notincluded[$key] = $value;
            }
        }
        return [$included, $notincluded];
    }

    /**
     * Check if setting is in admin tree
     *
     * @param string $key
     * @param string|null $plugin
     * @return bool
     */
    protected function setting_in_admin_tree(string $key, ?string $plugin = null): bool {
        global $CFG, $DB;
        // TODO actually check admin tree, not database.
        if ($plugin) {
            return $DB->record_exists('config_plugins', ['name' => $key, 'plugin' => $plugin]);
        } else {
            return $DB->record_exists('config', ['name' => $key]);
        }
    }

    /**
     * Plugins config overrides
     *
     * @return array[][]
     */
    protected function get_config_overrides_plugins() {
        global $CFG;
        $included = $notincluded = [];
        foreach ($CFG->forced_plugin_settings as $plugin => $settings) {
            foreach ($settings as $key => $value) {
                if ($this->setting_in_admin_tree($key, $plugin)) {
                    $included += [$plugin => []];
                    $included[$plugin][$key] = $value;
                } else {
                    $notincluded += [$plugin => []];
                    $notincluded[$plugin][$key] = $value;
                }
            }
        }
        return [$included, $notincluded];
    }

    /**
     * Defaults for core config that can be ignored
     *
     * @return array
     */
    protected function core_config_defaults() {
        global $CFG;
        return [
            'admin' => 'admin',
            'tempdir' => "$CFG->dataroot/temp",
            'backuptempdir' => "$CFG->tempdir/backup",
            'cachedir' => "$CFG->dataroot/cache",
            'localcachedir' => "$CFG->dataroot/localcache",
            'localrequestdir' => sys_get_temp_dir() . '/requestdir',
            'langotherroot' => $CFG->dataroot.'/lang',
            'langlocalroot' => $CFG->dataroot.'/lang',
            'directorypermissions' => 02777,
            'filepermissions' => ($CFG->directorypermissions & 0666),
            'umaskpermissions' => (($CFG->directorypermissions & 0777) ^ 0777),
        ];
    }

    /**
     * Can backup be performed
     *
     * @return bool
     */
    public function success(): bool {
        return true;
    }

    /**
     * Get summary of the past check
     *
     * @return string
     */
    public function summary(): string {
        if (($details = $this->get_report()) === null) {
            return '';
        }
        return
            '<ul>'.
            '<li>Settings from config.php that will be included in backup: '.
            count($details['config_php_settings_included']).'</li>'.
            '<li>Settings from config.php that will NOT be included in backup: '.
            count($details['config_php_settings_notincluded']).'</li>'.
            '<li>Plugin settings from config.php that will be included in backup: '.
            count($details['forced_plugin_settings_included']).'</li>'.
            '<li>Plugin settings from config.php that will NOT be included in backup: '.
            count($details['forced_plugin_settings_notincluded']).'</li>'.
            '</ul>';
    }

    /**
     * Report
     *
     * @return array|null
     */
    protected function get_report(): ?array {
        if ($this->get_model()->status !== constants::STATUS_FINISHED) {
            return null;
        }
        return $this->get_model()->get_details();
    }

    /**
     * Format setting value for details
     *
     * @param string $name
     * @param string|null $plugin
     * @param mixed $value
     * @param bool $included
     * @return array
     */
    protected function format_setting_value_for_details(string $name, ?string $plugin, $value, bool $included): array {
        if (!$included) {
            $value = '<em>Redacted</em>';
        } else {
            $value = is_array($value) ? 'Array' : s((string)$value);
        }
        return ['name' => $name, 'value' => $value, 'plugin' => $plugin];
    }

    /**
     * Data for the template
     *
     * @return array
     */
    protected function get_template_data(): array {
        $data = [
            'includedsettings' => [],
            'notincludedsettings' => [],
        ];
        if (($report = $this->get_report()) === null) {
            return $data;
        }
        foreach (($report['config_php_settings_included'] ?? []) as $key => $value) {
            $data['includedsettings'][] = $this->format_setting_value_for_details($key, null, $value, true);
        }
        foreach (($report['forced_plugin_settings_included'] ?? []) as $plugin => $settings) {
            foreach ($settings as $key => $value) {
                $data['includedsettings'][] = $this->format_setting_value_for_details($key, $plugin, $value, true);
            }
        }
        foreach (($report['config_php_settings_notincluded'] ?? []) as $key => $value) {
            $data['notincludedsettings'][] = $this->format_setting_value_for_details($key, null, $value, false);
        }
        foreach (($report['forced_plugin_settings_notincluded'] ?? []) as $plugin => $settings) {
            foreach ($settings as $key => $value) {
                $data['notincludedsettings'][] = $this->format_setting_value_for_details($key, $plugin, $value, false);
            }
        }
        $data['hasincludedsettings'] = !empty($data['includedsettings']);
        $data['hasnotincludedsettings'] = !empty($data['notincludedsettings']);
        return $data;
    }

    /**
     * Does this past check have details (to display a link "Show details")
     *
     * @return bool
     */
    public function has_details(): bool {
        $report = $this->get_report();
        return ($report !== null && array_filter($report));
    }

    /**
     * Get detailed report of the past check
     *
     * @return string
     */
    public function detailed_report(): string {
        global $OUTPUT;
        $report = $this->get_report();
        return $report !== null ?
            $OUTPUT->render_from_template('tool_vault/check_configoverride_details', $this->get_template_data()) :
            '';
    }

    /**
     * Display name of this check
     *
     * @return string
     */
    public static function get_display_name(): string {
        return "Config overrides";
    }

    /**
     * Get list of overrides that has to be included in the backup
     *
     * @return array
     */
    public function get_config_overrides_for_backup(): array {
        $rv = [];
        if ($report = $this->get_report()) {
            foreach (($report['config_php_settings_included'] ?? []) as $key => $value) {
                $rv[] = ['name' => $key, 'value' => (string)$value, 'plugin' => null];
            }
            foreach (($report['forced_plugin_settings_included'] ?? []) as $plugin => $settings) {
                foreach ($settings as $key => $value) {
                    $rv[] = ['name' => $key, 'value' => (string)$value, 'plugin' => $plugin];
                }
            }
        }
        return $rv;
    }
}
