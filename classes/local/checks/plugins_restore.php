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

use tool_vault\api;
use tool_vault\constants;
use tool_vault\local\helpers\siteinfo;
use tool_vault\local\models\dryrun_model;
use tool_vault\local\uiactions\settings;

/**
 * Check plugins version on restore
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugins_restore extends check_base {

    /**
     * Evaluate check and store results in model details
     */
    public function perform(): void {
        $excludedplugins = ['tool_vault']; // TODO more from settings.
        $pluginlist = array_diff_key(siteinfo::get_plugins_list_full(true), array_fill_keys($excludedplugins, true));
        $parent = $this->get_parent();
        $backupplugins = $parent->get_metadata()['plugins'];
        $list = $this->consolidate($backupplugins, $pluginlist);
        $this->model->set_details([
            'list' => $list,
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
            $list[$pluginname] = [$backupplugins[$pluginname] ?? [], $plugins[$pluginname] ?? []];
        }
        return $list;

    }

    /**
     * Find all plugins with problems (lower version than in the backup)
     *
     * @return array
     */
    protected function problem_plugins(): array {
        $list = $this->model->get_details()['list'];
        $problem = [];
        foreach ($list as $pluginname => $info) {
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
     * @return array
     */
    protected function plugins_needing_upgrade(): array {
        $list = $this->model->get_details()['list'];
        $problem = [];
        foreach ($list as $pluginname => $info) {
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
     * @return array
     */
    public function extra_plugins(): array {
        return array_filter($this->model->get_details()['list'], function($info) {
            return empty($info[0]) && !empty($info[1]);
        });
    }

    /**
     * Plugins present in the backup and missing on this site
     *
     * @return array
     */
    protected function missing_plugins(): array {
        return array_filter($this->model->get_details()['list'], function($info) {
            return empty($info[1]) && !empty($info[0]);
        });
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
        $problemplugins = $this->problem_plugins();
        return empty($problemplugins);
    }


    /**
     * Status message text
     *
     * @return string
     */
    public function get_status_message(): string {
        if ($this->success()) {
            if ($this->extra_plugins() || $this->plugins_needing_upgrade()) {
                return 'Some plugins will need to be upgraded after restore';
            } else if ($this->missing_plugins()) {
                return 'Some plugins are missing but the restore is possible';
            } else {
                return 'Plugins versions in the backup and on this site match';
            }
        } else {
            return 'Some plugins have lower version than the same plugins in the backup';
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
            $r[] = "Plugins have lower version than the same plugins in the backup: ".join(', ', array_keys($p));
        }
        if ($p = $this->extra_plugins()) {
            $r[] = "Plugins found on this site but not present in the backup: ".join(', ', array_keys($p));
        }
        if ($p = $this->plugins_needing_upgrade()) {
            $r[] = "Plugins have higher version than the same plugins in the backup: ".join(', ', array_keys($p));
        }
        if ($p = $this->missing_plugins()) {
            $r[] = "Missing plugins: ".join(', ', array_keys($p));
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
        return $this->problem_plugins() || $this->missing_plugins()
            || $this->extra_plugins() || $this->plugins_needing_upgrade();
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
     * Prepare a list of plugins for template export
     *
     * @param array $list
     * @return array
     */
    protected function prepare_for_template(array $list) {
        $plugins = [];
        $showparents = false;
        foreach ($list as $pluginname => $info) {
            $parent = $info[1]['parent'] ?? $info[0]['parent'] ?? null;
            $showparents = $showparents || !empty($parent);
            $plugins[] = $this->plugin_with_name($pluginname) + [
                'versionbackup' => $info[0]['version'] ?? '',
                'versionlocal' => $info[1]['version'] ?? '',
                'parent' => $parent ? $this->plugin_with_name($parent) : [],
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
            $r['problemplugins'] = $this->prepare_for_template($p);
        }
        if ($p = $this->extra_plugins()) {
            $r['hasextra'] = true;
            $r['extraplugins'] = $this->prepare_for_template($p);
        }
        if ($p = $this->plugins_needing_upgrade()) {
            $r['hastobeupgraded'] = true;
            $r['tobeupgraded'] = $this->prepare_for_template($p);
        }
        if ($p = $this->missing_plugins()) {
            $r['hasmissing'] = true;
            $r['missingplugins'] = $this->prepare_for_template($p);
        }

        $r['removemissing'] = (int)(bool)api::get_config('removemissing');
        $r['settingsurl'] = settings::url()->out(false);
        return $renderer->render_from_template('tool_vault/checks/plugins_restore_details', $r);
    }

    /**
     * Display name of this check
     *
     * @return string
     */
    public static function get_display_name(): string {
        return "Plugins versions";
    }
}
