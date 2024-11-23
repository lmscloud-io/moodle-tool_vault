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

namespace tool_vault\local\restoreactions\upgrade_311\helpers;

use stdClass;

/**
 * Class general_helper
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class general_helper {

    /**
      * Deletes file records which have their repository deleted.
      *
      */
      public static function upgrade_delete_orphaned_file_records() {
        global $DB;

        $sql = "SELECT f.id, f.contextid, f.component, f.filearea, f.itemid, fr.id AS referencefileid
                FROM {files} f
                JOIN {files_reference} fr ON f.referencefileid = fr.id
            LEFT JOIN {repository_instances} ri ON fr.repositoryid = ri.id
                WHERE ri.id IS NULL";

        $deletedfiles = $DB->get_recordset_sql($sql);

        $deletedfileids = [];

        $fs = get_file_storage();
        foreach ($deletedfiles as $deletedfile) {
            $fs->delete_area_files($deletedfile->contextid, $deletedfile->component, $deletedfile->filearea, $deletedfile->itemid);
            $deletedfileids[] = $deletedfile->referencefileid;
        }
        $deletedfiles->close();

        $DB->delete_records_list('files_reference', 'id', $deletedfileids);
    }

    /**
     * Detects if the site may need to get the calendar events fixed or no. With optional output.
     *
     * @param bool $output true if the function must output information, false if not.
     * @return bool true if the site needs to run the fixes, false if not.
     */
    public static function upgrade_calendar_site_status(bool $output = true): bool {
        global $DB;

        // List of upgrade steps where the bug happened.
        $badsteps = [
            '3.9.5'   => '2020061504.08',
            '3.10.2'  => '2020110901.09',
            '3.11dev' => '2021022600.02',
            '4.0dev'  => '2021052500.65',
        ];

        // List of upgrade steps that ran the fixer.
        $fixsteps = [
            '3.9.6+'  => '2020061506.05',
            '3.10.3+' => '2020110903.05',
            '3.11dev' => '2021042100.02',
            '4.0dev'  => '2021052500.85',
        ];

        $targetsteps = array_merge(array_values($badsteps), array_values( $fixsteps));
        list($insql, $inparams) = $DB->get_in_or_equal($targetsteps);
        $foundsteps = $DB->get_fieldset_sql("
            SELECT DISTINCT version
            FROM {upgrade_log}
            WHERE plugin = 'core'
            AND version " . $insql . "
        ORDER BY version", $inparams);

        // Analyse the found steps, to decide if the site needs upgrading or no.
        $badfound = false;
        $fixfound = false;
        foreach ($foundsteps as $foundstep) {
            $badfound = $badfound ?: array_search($foundstep, $badsteps, true);
            $fixfound = $fixfound ?: array_search($foundstep, $fixsteps, true);
        }
        $needsfix = $badfound && !$fixfound;

        // Let's output some textual information if required to.
        if ($output) {
            mtrace("");
            if ($badfound) {
                mtrace("This site has executed the problematic upgrade step {$badsteps[$badfound]} present in {$badfound}.");
            } else {
                mtrace("Problematic upgrade steps were NOT found, site should be safe.");
            }
            if ($fixfound) {
                mtrace("This site has executed the fix upgrade step {$fixsteps[$fixfound]} present in {$fixfound}.");
            } else {
                mtrace("Fix upgrade steps were NOT found.");
            }
            mtrace("");
            if ($needsfix) {
                mtrace("This site NEEDS to run the calendar events fix!");
                mtrace('');
                mtrace("You can use this CLI tool or upgrade to a version of Moodle that includes");
                mtrace("the fix and will be executed as part of the normal upgrade procedure.");
                mtrace("The following versions or up are known candidates to upgrade to:");
                foreach ($fixsteps as $key => $value) {
                    mtrace("  - {$key}: {$value}");
                }
                mtrace("");
            }
        }
        return $needsfix;
    }

    /**
     * Called on install or upgrade to create default list of backpacks a user can connect to.
     * Don't use the global defines from badgeslib because this is for install/upgrade.
     *
     * @return void
     */
    public static function badges_install_default_backpacks() {
        global $DB;

        $record = new stdClass();
        $record->backpackapiurl = 'https://api.badgr.io/v2';
        $record->backpackweburl = 'https://badgr.io';
        $record->apiversion = 2;
        $record->sortorder = 1;
        $record->password = '';

        $bp = $DB->get_record('badge_external_backpack', ['backpackapiurl' => $record->backpackapiurl]);
        if ($bp) {
            $bpid = $bp->id;
        } else {
            $bpid = $DB->insert_record('badge_external_backpack', $record);
        }

        // Set external backpack to v2.
        $DB->set_field('badge_backpack', 'externalbackpackid', $bpid);
    }

    /**
     * Updates the existing prediction actions in the database according to the new suggested actions.
     * @return null
     */
    public static function upgrade_rename_prediction_actions_useful_incorrectly_flagged() {
        global $DB;

        // The update depends on the analyser class used by each model so we need to iterate through the models in the system.
        $modelids = $DB->get_records_sql("SELECT DISTINCT am.id, am.target
                                            FROM {analytics_models} am
                                            JOIN {analytics_predictions} ap ON ap.modelid = am.id
                                            JOIN {analytics_prediction_actions} apa ON ap.id = apa.predictionid");
        foreach ($modelids as $model) {
            $targetname = $model->target;
            if (!class_exists($targetname)) {
                // The plugin may not be available.
                continue;
            }
            $target = new $targetname();

            $analyserclass = $target->get_analyser_class();
            if (!class_exists($analyserclass)) {
                // The plugin may not be available.
                continue;
            }

            if ($analyserclass::one_sample_per_analysable()) {
                // From 'fixed' to 'useful'.
                $params = ['oldaction' => 'fixed', 'newaction' => 'useful'];
            } else {
                // From 'notuseful' to 'incorrectlyflagged'.
                $params = ['oldaction' => 'notuseful', 'newaction' => 'incorrectlyflagged'];
            }

            $subsql = "SELECT id FROM {analytics_predictions} WHERE modelid = :modelid";
            $updatesql = "UPDATE {analytics_prediction_actions}
                            SET actionname = :newaction
                        WHERE predictionid IN ($subsql) AND actionname = :oldaction";

            $DB->execute($updatesql, $params + ['modelid' => $model->id]);
        }
    }

    /**
     * Convert the site settings for the 'hub' component in the config_plugins table.
     *
     * @param stdClass $hubconfig Settings loaded for the 'hub' component.
     * @param string $huburl The URL of the hub to use as the valid one in case of conflict.
     * @return stdClass List of new settings to be applied (including null values to be unset).
     */
    public static function upgrade_convert_hub_config_site_param_names(stdClass $hubconfig, string $huburl): stdClass {

        $cleanhuburl = clean_param($huburl, PARAM_ALPHANUMEXT);
        $converted = [];

        foreach ($hubconfig as $oldname => $value) {
            if (preg_match('/^site_([a-z]+)([A-Za-z0-9_-]*)/', $oldname, $matches)) {
                $newname = 'site_'.$matches[1];

                if ($oldname === $newname) {
                    // There is an existing value with the new naming convention already.
                    $converted[$newname] = $value;

                } else if (!array_key_exists($newname, $converted)) {
                    // Add the value under a new name and mark the original to be unset.
                    $converted[$newname] = $value;
                    $converted[$oldname] = null;

                } else if ($matches[2] === '_'.$cleanhuburl) {
                    // The new name already exists, overwrite only if coming from the valid hub.
                    $converted[$newname] = $value;
                    $converted[$oldname] = null;

                } else {
                    // Just unset the old value.
                    $converted[$oldname] = null;
                }

            } else {
                // Not a hub-specific site setting, just keep it.
                $converted[$oldname] = $value;
            }
        }

        return (object) $converted;
    }

    /**
     * Fix the incorrect default values inserted into analytics contextids field.
     */
    public static function upgrade_analytics_fix_contextids_defaults() {
        global $DB;

        $select = $DB->sql_compare_text('contextids') . ' = :zero OR ' . $DB->sql_compare_text('contextids') . ' = :null';
        $params = ['zero' => '0', 'null' => 'null'];
        $DB->execute("UPDATE {analytics_models} set contextids = null WHERE " . $select, $params);
    }

    /**
     * Upgrade core licenses shipped with Moodle.
     */
    public static function upgrade_core_licenses() {
        global $CFG, $DB;

        $corelicenses = [];

        $license = new stdClass();
        $license->shortname = 'unknown';
        $license->fullname = 'Licence not specified';
        $license->source = '';
        $license->enabled = 1;
        $license->version = '2010033100';
        $license->custom = 0;
        $corelicenses[] = $license;

        $license = new stdClass();
        $license->shortname = 'allrightsreserved';
        $license->fullname = 'All rights reserved';
        $license->source = 'https://en.wikipedia.org/wiki/All_rights_reserved';
        $license->enabled = 1;
        $license->version = '2010033100';
        $license->custom = 0;
        $corelicenses[] = $license;

        $license = new stdClass();
        $license->shortname = 'public';
        $license->fullname = 'Public domain';
        $license->source = 'https://en.wikipedia.org/wiki/Public_domain';
        $license->enabled = 1;
        $license->version = '2010033100';
        $license->custom = 0;
        $corelicenses[] = $license;

        $license = new stdClass();
        $license->shortname = 'cc';
        $license->fullname = 'Creative Commons';
        $license->source = 'https://creativecommons.org/licenses/by/3.0/';
        $license->enabled = 1;
        $license->version = '2010033100';
        $license->custom = 0;
        $corelicenses[] = $license;

        $license = new stdClass();
        $license->shortname = 'cc-nd';
        $license->fullname = 'Creative Commons - NoDerivs';
        $license->source = 'https://creativecommons.org/licenses/by-nd/3.0/';
        $license->enabled = 1;
        $license->version = '2010033100';
        $license->custom = 0;
        $corelicenses[] = $license;

        $license = new stdClass();
        $license->shortname = 'cc-nc-nd';
        $license->fullname = 'Creative Commons - No Commercial NoDerivs';
        $license->source = 'https://creativecommons.org/licenses/by-nc-nd/3.0/';
        $license->enabled = 1;
        $license->version = '2010033100';
        $license->custom = 0;
        $corelicenses[] = $license;

        $license = new stdClass();
        $license->shortname = 'cc-nc';
        $license->fullname = 'Creative Commons - No Commercial';
        $license->source = 'https://creativecommons.org/licenses/by-nc/3.0/';
        $license->enabled = 1;
        $license->version = '2010033100';
        $license->custom = 0;
        $corelicenses[] = $license;

        $license = new stdClass();
        $license->shortname = 'cc-nc-sa';
        $license->fullname = 'Creative Commons - No Commercial ShareAlike';
        $license->source = 'https://creativecommons.org/licenses/by-nc-sa/3.0/';
        $license->enabled = 1;
        $license->version = '2010033100';
        $license->custom = 0;
        $corelicenses[] = $license;

        $license = new stdClass();
        $license->shortname = 'cc-sa';
        $license->fullname = 'Creative Commons - ShareAlike';
        $license->source = 'https://creativecommons.org/licenses/by-sa/3.0/';
        $license->enabled = 1;
        $license->version = '2010033100';
        $license->custom = 0;
        $corelicenses[] = $license;

        foreach ($corelicenses as $corelicense) {
            // Check for current license to maintain idempotence.
            $currentlicense = $DB->get_record('license', ['shortname' => $corelicense->shortname]);
            if (!empty($currentlicense)) {
                $corelicense->id = $currentlicense->id;
                // Remember if the license was enabled before upgrade.
                $corelicense->enabled = $currentlicense->enabled;
                $DB->update_record('license', $corelicense);
            } else if (!isset($CFG->upgraderunning) || during_initial_install()) {
                // Only install missing core licenses if not upgrading or during initial install.
                $DB->insert_record('license', $corelicense);
            }
        }

        // Add sortorder to all licenses.
        $licenses = $DB->get_records('license');
        $sortorder = 1;
        foreach ($licenses as $license) {
            $license->sortorder = $sortorder++;
            $DB->update_record('license', $license);
        }

        // Set the license config values, used by file repository for rendering licenses at front end.
        $activelicenses = $DB->get_records_menu('license', ['enabled' => 1], 'id', 'id, shortname');
        set_config('licenses', implode(',', $activelicenses));

        $sitedefaultlicense = get_config('', 'sitedefaultlicense');
        if (empty($sitedefaultlicense) || !in_array($sitedefaultlicense, $activelicenses)) {
            set_config('sitedefaultlicense', reset($activelicenses));
        }
    }
}
