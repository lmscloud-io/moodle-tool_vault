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

namespace tool_vault\local\restoreactions\upgrade_36\helpers;

use core_php_time_limit;
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
     * Delete orphaned records in block_positions
     */
    public static function upgrade_block_positions() {
        global $DB;
        $id = 'id';
        if ($DB->get_dbfamily() !== 'mysql') {
            // Field block_positions.subpage has type 'char', it can not be compared to int in db engines except for mysql.
            $id = $DB->sql_concat('?', 'id');
        }
        $sql = "DELETE FROM {block_positions}
        WHERE pagetype IN ('my-index', 'user-profile') AND subpage NOT IN (SELECT $id FROM {my_pages})";
        $DB->execute($sql, ['']);
    }

    /**
     * Search for a given theme in any of the parent themes of a given theme.
     *
     * @param string $needle The name of the theme you want to search for
     * @param string $themename The name of the theme you want to search for
     * @param string $checkedthemeforparents The name of all the themes already checked
     * @return bool True if found, false if not.
     */
    public static function upgrade_theme_is_from_family($needle, $themename, $checkedthemeforparents = []) {
        global $CFG;

        // Once we've started checking a theme, don't start checking it again. Prevent recursion.
        if (!empty($checkedthemeforparents[$themename])) {
            return false;
        }
        $checkedthemeforparents[$themename] = true;

        if ($themename == $needle) {
            return true;
        }

        if ($themedir = self::upgrade_find_theme_location($themename)) {
            $THEME = new stdClass();
            require($themedir . '/config.php');
            $theme = $THEME;
        } else {
            return false;
        }

        if (empty($theme->parents)) {
            return false;
        }

        // Recursively search through each parent theme.
        foreach ($theme->parents as $parent) {
            if (self::upgrade_theme_is_from_family($needle, $parent, $checkedthemeforparents)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Finds the theme location and verifies the theme has all needed files.
     *
     * @param string $themename The name of the theme you want to search for
     * @return string full dir path or null if not found
     * @see \theme_config::find_theme_location()
     */
    public static function upgrade_find_theme_location($themename) {
        global $CFG;

        if (file_exists("$CFG->dirroot/theme/$themename/config.php")) {
            $dir = "$CFG->dirroot/theme/$themename";
        } else if (!empty($CFG->themedir) and file_exists("$CFG->themedir/$themename/config.php")) {
            $dir = "$CFG->themedir/$themename";
        } else {
            return null;
        }

        return $dir;
    }

    /**
     * Fix configdata in block instances that are using the old object class that has been removed (deprecated).
     */
    public static function upgrade_fix_block_instance_configuration() {
        global $DB;

        $sql = "SELECT *
                FROM {block_instances}
                WHERE " . $DB->sql_isnotempty('block_instances', 'configdata', true, true);
        $blockinstances = $DB->get_recordset_sql($sql);
        foreach ($blockinstances as $blockinstance) {
            $configdata = base64_decode($blockinstance->configdata);
            list($updated, $configdata) = self::upgrade_fix_serialized_objects($configdata);
            if ($updated) {
                $blockinstance->configdata = base64_encode($configdata);
                $DB->update_record('block_instances', $blockinstance);
            }
        }
        $blockinstances->close();
    }

    /**
     * Provides a way to check and update a serialized string that uses the deprecated object class.
     *
     * @param  string $serializeddata Serialized string which may contain the now deprecated object.
     * @return array Returns an array where the first variable is a bool with a status of whether the initial data was changed
     * or not. The second variable is the said data.
     */
    public static function upgrade_fix_serialized_objects($serializeddata) {
        $updated = false;
        if (strpos($serializeddata, ":6:\"object") !== false) {
            $serializeddata = str_replace(":6:\"object", ":8:\"stdClass", $serializeddata);
            $updated = true;
        }
        return [$updated, $serializeddata];
    }

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

}
