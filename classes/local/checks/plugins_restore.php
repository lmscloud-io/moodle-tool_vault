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

namespace tool_vault\local\checks;

use core_form\dynamic_form;
use core_plugin_manager;
use moodle_url;
use tool_vault\api;
use tool_vault\constants;
use tool_vault\local\helpers\plugincode;
use tool_vault\local\helpers\siteinfo;
use tool_vault\local\models\dryrun_model;

/**
 * Check plugins version on restore
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugins_restore extends check_base_restore {

    /**
     * Evaluate check and store results in model details
     */
    public function perform(): void {
        global $CFG;
        /** @var dryrun_model $parent */
        $parent = $this->get_parent();
        $backupbranch = $parent->get_metadata()['branch'];
        if ($backupbranch > $CFG->branch) {
            // Skip this check if backup has a higher major moodle version, or it will be unreadable
            // and full of confusing errors.
            $this->model->set_details([
                'list' => [],
                'standardplugins' => [],
                'skipped' => true,
            ])->save();
            return;
        }

        $excludedplugins = ['tool_vault']; // TODO more from settings.
        $pluginlist = array_diff_key(siteinfo::get_plugins_list_full(true), array_fill_keys($excludedplugins, true));
        $parent = $this->get_parent();
        $backupplugins = $parent->get_metadata()['plugins'];
        $list = $this->consolidate($backupplugins, $pluginlist);
        $standardplugins = array_filter(array_keys($list), function($pluginname) {
            return $this->is_standard_plugin($pluginname, true);
        });
        $this->model->set_details([
            'list' => $list,
            'standardplugins' => $standardplugins,
        ])->save();
    }

    /**
     * Build one array from backup plugins and local plugins
     *
     * @param array $backupplugins
     * @param array $plugins
     * @return array
     */
    protected function consolidate(array $backupplugins, array $plugins): array {
        $list = [];
        $allplugins = array_keys($backupplugins + $plugins);
        foreach ($allplugins as $pluginname) {
            $info = [
                $backupplugins[$pluginname] ?? [],
                $plugins[$pluginname] ?? [],
                ['isstandard' => $this->is_standard_plugin($pluginname, true)],
            ];
            $v1 = $info[0]['version'] ?? null;
            $v2 = $info[1]['version'] ?? null;
            if ($info[0]) {
                // Plugins that is present in the backup.
                $info[2]['isproblem'] = $v2 && $v1 > $v2; // Version of the plugin in the backup is higher than on this site.
                $info[2]['ismissing'] = !$v2; // Plugin is present in the backup but absent on this site.
                $parent = !empty($info[0]['parent']) ? $backupplugins[$info[0]['parent']] ?? null : null;
                $info[2]['parentismissing'] = $parent && !empty($parent['isaddon']) &&
                    empty($plugins[$info[0]['parent']]['version']);
            }
            if ($v1 && !$info[2]['isstandard'] && (!empty($info[2]['isproblem']) || !empty($info[2]['ismissing']))) {
                // Non-standard plugins with a problem or missing - try to find them in the plugins directory.
                if (empty($info[2]['parentismissing'])) {
                    // Do not do anything if the parent is also missing, it will be reported under the missing parent.
                    try {
                        $info[2]['latest'] = plugincode::check_on_moodle_org($pluginname);
                    } catch (\Throwable $e) {
                        $info[2]['latest'] = ['error' => $e->getMessage()];
                    }
                    $latestversion = $info[2]['latest']['version'] ?? null;
                    if ("{$latestversion}" !== "{$v1}") {
                        try {
                            $info[2]['exact'] = plugincode::check_on_moodle_org($pluginname . '@' . $v1);
                        } catch (\Throwable $e) {
                            $info[2]['exact'] = ['error' => $e->getMessage()];
                        }
                    }
                }
            }
            $list[$pluginname] = $info;
        }

        return $list;
    }

    /**
     * Find all plugins with problems (lower version than in the backup)
     *
     * @param bool $includestandard include standard plugins
     * @return array
     */
    protected function problem_plugins(bool $includestandard = true): array {
        $list = $this->model->get_details()['list'];
        $problem = [];
        foreach ($list as $pluginname => $info) {
            if (!$includestandard && $this->is_standard_plugin($pluginname)) {
                continue;
            }
            $v1 = $info[0]['version'] ?? null;
            $v2 = $info[1]['version'] ?? null;
            if ($v1 && $v2 && $v1 > $v2) {
                $problem[$pluginname] = $info;
            }
        }
        return $problem;
    }

    /**
     * Find all plugins needing upgrade (higher version than in the backup)
     *
     * @param bool $includestandard include standard plugins
     * @return array pluginname=>[['version'=>...], ['version'=>...]]
     */
    public function plugins_needing_upgrade(bool $includestandard = true): array {
        $list = $this->model->get_details()['list'];
        $problem = [];
        foreach ($list as $pluginname => $info) {
            if (!$includestandard && $this->is_standard_plugin($pluginname)) {
                continue;
            }
            $v1 = $info[0]['version'] ?? null;
            $v2 = $info[1]['version'] ?? null;
            if ($v1 && $v2 && $v1 < $v2) {
                $problem[$pluginname] = $info;
            }
        }
        return $problem;
    }

    /**
     * Plugins present on this site but not present in the backup
     *
     * @param bool $includestandard include standard plugins
     * @return array pluginname=>[null, ['version'=>...]]
     */
    public function extra_plugins(bool $includestandard = true): array {
        return array_filter($this->model->get_details()['list'], function($info, $pluginname) use ($includestandard) {
            return empty($info[0]) && !empty($info[1])
                && ($includestandard || !$this->is_standard_plugin($pluginname));
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Plugins present in the backup and missing on this site
     *
     * @param bool $includestandard include standard plugins
     * @return array
     */
    protected function missing_plugins(bool $includestandard = true): array {
        $plugins = array_filter($this->model->get_details()['list'], function($info, $pluginname) use ($includestandard)  {
            return empty($info[1]) && !empty($info[0])
                && ($includestandard || !$this->is_standard_plugin($pluginname));
        }, ARRAY_FILTER_USE_BOTH);

        // Do not return subplugins of missing plugins as individual entries.
        foreach (array_keys($plugins) as $pluginname) {
            if (!empty($plugins[$pluginname][2]['parentismissing'])) {
                $parent = $plugins[$pluginname][0]['parent'];
                if (array_key_exists($parent, $plugins)) {
                    $plugins[$parent][0]['subplugins'] = $plugins[$parent][0]['subplugins'] ?? [];
                    $plugins[$parent][0]['subplugins'][$pluginname] = $plugins[$pluginname][0];
                    unset($plugins[$pluginname]);
                }
            }
        }

        return $plugins;
    }

    /**
     * Can backup be performed
     *
     * @return bool
     */
    public function success(): bool {
        if ($this->model->status !== constants::STATUS_FINISHED) {
            return false;
        }
        if (!empty($this->model->get_details()['skipped'])) {
            return false;
        }
        if ($this->problem_plugins()) {
            return false;
        }
        if (!api::get_setting_checkbox('allowrestorewithmissing') && $this->missing_plugins(false)) {
            return false;
        }
        return true;
    }


    /**
     * Status message text
     *
     * @return string
     */
    public function get_status_message(): string {
        if ($this->success()) {
            if ($this->extra_plugins(false) || $this->plugins_needing_upgrade(false)) {
                return get_string('addonplugins_success_needsupgrade', 'tool_vault');
            } else if ($this->missing_plugins(false)) {
                return get_string('addonplugins_success_withmissing', 'tool_vault');
            } else {
                return get_string('addonplugins_success', 'tool_vault');
            }
        } else {
            if (!empty($this->get_model()->get_details()['skipped'])) {
                return get_string('addonplugins_fail_skipped', 'tool_vault');
            } else if ($this->problem_plugins()) {
                return get_string('addonplugins_fail', 'tool_vault');
            } else {
                return get_string('addonplugins_fail_missing', 'tool_vault');
            }
        }
    }

    /**
     * Get summary of the past check
     *
     * @return string
     */
    public function summary(): string {
        if ($this->model->status !== constants::STATUS_FINISHED) {
            return '';
        }
        $r = [];
        if ($p = $this->problem_plugins()) {
            $paddon = $this->problem_plugins(false);
            if (count($p) > count($paddon)) {
                $paddon[get_string('containsstandardplugins', 'tool_vault', count($p) - count($paddon))] = true;
            }
            $r[] = $this->badge_error() . get_string('addonplugins_withlowerversion', 'tool_vault') . ": " .
                join(', ', array_keys($paddon));
        }
        if ($p = $this->missing_plugins(false)) {
            $badge = api::get_setting_checkbox('allowrestorewithmissing') ? $this->badge_warning() : $this->badge_error();
            $r[] = $badge . get_string('addonplugins_missing', 'tool_vault') . ": " .
                join(', ', array_keys($p));
        }
        if ($p = $this->extra_plugins(false)) {
            $r[] = get_string('addonplugins_notpresent', 'tool_vault') . ": " .
                join(', ', array_keys($p));
        }
        if ($p = $this->plugins_needing_upgrade(false)) {
            $r[] = get_string('addonplugins_withhigherversion', 'tool_vault') . ": " .
                join(', ', array_keys($p));
        }
        return
            $this->display_status_message($this->get_status_message(), !empty($r)).
            ($r ? ('<ul><li>'. join('</li><li>', $r).'</li></ul>') : '');
    }

    /**
     * Does this past check have details (to display a link "Show details")
     *
     * @return bool
     */
    public function has_details(): bool {
        return $this->problem_plugins() || $this->missing_plugins(false)
            || $this->extra_plugins(false) || $this->plugins_needing_upgrade(false);
    }

    /**
     * Exporter for plugin name
     *
     * @param string $pluginname
     * @return array
     */
    protected function plugin_with_name(string $pluginname) {
        $info = $this->model->get_details()['list'][$pluginname];
        $name = $info[1]['name'] ?? $info[0]['name'] ?? null;
        return [
            'name' => $name,
            'hasname' => !empty($name),
            'pluginname' => $pluginname,
        ];
    }

    /**
     * Additional check if the plugin was a standard plugin but is now removed
     *
     * Fix for the core function core_plugin_manager::is_deleted_standard_plugin
     *
     * @param string $type
     * @param string $name
     * @return bool
     */
    protected function is_deleted_standard_plugin_fix(string $type, string $name): bool {
        global $CFG;
        // For Moodle 4.0 and 4.1 there was a mistake in the function is_deleted_standard_plugin().
        // It was fixed in https://tracker.moodle.org/browse/MDL-80868 and may still be present in some 4.2 (<4.2.7)
        // and 4.3 (<4.3.4) versions.
        if (in_array("{$CFG->branch}", ['400', '401', '402', '403'])) {
            if ($type === 'qformat' && in_array($name, ['blackboard', 'learnwise', 'examview'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks if a plugin is standard or deleted standard plugin
     *
     * @param string $pluginname
     * @param bool $realtime run in real-time (check with the core classes), rather than take from the check results
     * @return bool
     */
    protected function is_standard_plugin(string $pluginname, bool $realtime = false): bool {
        $standardplugins = $realtime ? null : ($this->model->get_details()['standardplugins'] ?? null);
        if (!isset($standardplugins) || !is_array($standardplugins)) {
            list($type, $name) = \core_component::normalize_component($pluginname);
            $allplugins = core_plugin_manager::standard_plugins_list($type) ?: [];
            return in_array($name, $allplugins) || core_plugin_manager::is_deleted_standard_plugin($type, $name)
                || $this->is_deleted_standard_plugin_fix($type, $name);
        } else {
            return in_array($pluginname, $standardplugins);
        }
    }

    /**
     * Is tool_vault allowed to install and write to the codebase
     *
     * Due to implementation limitation we use dynamic_form and can only allow to write in Moodle 3.11 and above
     * where dynamic_form clas is available
     *
     * @return bool
     */
    protected static function allow_vault_to_install(): bool {
        return class_exists(dynamic_form::class);
    }

    /**
     * Prepare download information about a single version of a plugin
     *
     * @param string $pluginname
     * @param array $minfo
     * @param string $versionpostfix
     * @return string
     */
    protected function prepare_link_to_install(string $pluginname, array $minfo, string $versionpostfix = ''): string {
        $currentversion = moodle_major_version();

        if (!empty($minfo['error'])) {
            // TODO process/display nicer?
            return $minfo['error'];
        }

        $s = 'Version '.$minfo['version'];
        $s .= $versionpostfix;
        $s .= ' for Moodle '.join(', ', $minfo['supportedmoodles']);
        $s .= ' is available in the plugins directory. ';
        if (!in_array($currentversion, $minfo['supportedmoodles'])) {
            $s .= ' Current Moodle version '.$currentversion.' is not supported!';
        }
        $s .= ' '.
            'You can <a href="'.$minfo['downloadurl'].'">download it as zip</a> and unpack in '.
            plugincode::guess_plugin_path_relative($pluginname).' in your Moodle codebase';
        if (plugincode::can_write_to_plugin_dir($pluginname) && self::allow_vault_to_install()) {
            $params = ['data-id' => $this->get_model()->id,
                'data-action' => 'installaddon',
                'data-source' => 'moodleorg',
                'data-pluginname' => $pluginname,
                'data-version' => $minfo['version'],
                'data-downloadurl' => $minfo['downloadurl'],
            ];
            $s .= ', or '.\html_writer::link(new moodle_url('#'), 'install', $params).' now.';
        }
        return $s;
    }

    /**
     * Prepare details of the missing or problem plugin (download information)
     *
     * @param string $pluginname
     * @param array $info
     * @return string
     */
    protected function prepare_details_for_template(string $pluginname, array $info): string {
        $ismissing = !empty($info[2]['ismissing']);
        if ($ismissing) {
            $res = [];
            $hasboth = !empty($info[2]['latest']) && !empty($info[2]['exact']);
            if (!empty($info[2]['latest'])) {
                $res[] = $this->prepare_link_to_install($pluginname, $info[2]['latest'],
                    $hasboth ? ' (latest)' : '');
            }
            if (!empty($info[2]['exact'])) {
                $res[] = $this->prepare_link_to_install($pluginname, $info[2]['exact'],
                    $hasboth ? ' (same as in backup)' : '');
            }
            if ($res) {
                $res = '<ul><li>'.join('</li><li>', $res).'</li></ul>';
                if (!plugincode::can_write_to_plugin_dir($pluginname) && self::allow_vault_to_install()) {
                    $res .= '<p>Tool vault does not have permission to write to the codebase and can not install it for you.</p>';
                }
                return $res;
            }
            return '<p>This plugin is not available in the plugins directory.</p>';
        }
        return "<a href=\"https://moodle.org/plugins/{$pluginname}\" target=\"_blank\">".
                get_string('addonplugins_pluginsdirectory', 'tool_vault')."</a>";
    }

    /**
     * Prepare a list of plugins for template export
     *
     * @param array $list
     * @param bool $withdetails
     * @return array
     */
    protected function prepare_for_template(array $list, bool $withdetails = false): array {
        $plugins = [];
        $showparents = false;
        foreach ($list as $pluginname => $info) {
            $parent = $info[1]['parent'] ?? $info[0]['parent'] ?? null;
            $showparents = $showparents || !empty($parent);
            $plugins[] = $this->plugin_with_name($pluginname) + [
                'versionbackup' => $info[0]['version'] ?? '',
                'versionlocal' => $info[1]['version'] ?? '',
                'parent' => $parent ? $this->plugin_with_name($parent) : [],
                'details' => $withdetails ? $this->prepare_details_for_template($pluginname, $info) : '',
            ];
        }
        return ['plugins' => $plugins, 'showparents' => $showparents];
    }

    /**
     * Get detailed report of the past check
     *
     * @return string
     */
    public function detailed_report(): string {
        global $PAGE;
        $renderer = $PAGE->get_renderer('tool_vault');
        $r = [];
        if ($p = $this->problem_plugins()) {
            $r['hasproblems'] = true;
            $r['problemplugins'] = $this->prepare_for_template($p, true);
        }
        if ($p = $this->extra_plugins(false)) {
            $r['hasextra'] = true;
            $r['extraplugins'] = $this->prepare_for_template($p);
        }
        if ($p = $this->plugins_needing_upgrade(false)) {
            $r['hastobeupgraded'] = true;
            $r['tobeupgraded'] = $this->prepare_for_template($p);
        }
        if ($p = $this->missing_plugins(false)) {
            $r['hasmissing'] = true;
            $r['missingplugins'] = $this->prepare_for_template($p, true) +
                ['hideversionlocal' => true];
        }

        $r['allowrestorewithmissing'] = (int)api::get_setting_checkbox('allowrestorewithmissing');
        $r['restoreremovemissing'] = (int)api::get_setting_checkbox('restoreremovemissing');
        $r['upgradeafterrestore'] = (int)api::get_setting_checkbox('upgradeafterrestore');
        $r['settingsurl'] = (new moodle_url('/admin/settings.php', ['section' => 'tool_vault']))->out(false);
        if (self::allow_vault_to_install()) {
            $PAGE->requires->js_call_amd('tool_vault/install_addon', 'init');
        }
        return $renderer->render_from_template('tool_vault/checks/plugins_restore_details', $r);
    }

    /**
     * Display name of this check
     *
     * @return string
     */
    public static function get_display_name(): string {
        return get_string('addonplugins', 'tool_vault');
    }
}
