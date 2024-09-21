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

// phpcs:ignoreFile
// Mdlcode-disable incorrect-package-name.

namespace tool_vault\local\restoreactions\upgrade_401\helpers;

/**
 * Admin presets helper class.
 *
 * @package    core_adminpresets
 * @copyright  2021 Sara Arjona (sara@moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class adminpresets_helper {

    /** @var int Non-core preset */
    public const NONCORE_PRESET = 0;

    /** @var int Starter preset */
    public const STARTER_PRESET = 1;

    /** @var int Full preset */
    public const FULL_PRESET = 2;

    /**
     * Create an empty preset.
     *
     * @param array $data Preset data. Supported values:
     *   - name. To define the preset name.
     *   - comments. To change the comments field.
     *   - author. To update the author field.
     *   - iscore. Whether the preset is a core preset or not. Valid values on \core_adminpresets\manager class.
     * @return int The identifier of the preset created.
     */
    public static function create_preset(array $data): int {
        global $CFG, $USER, $DB;

        $name = array_key_exists('name', $data) ? $data['name'] : '';
        $comments = array_key_exists('comments', $data) ? $data['comments'] : '';
        $author = array_key_exists('author', $data) ? $data['author'] : fullname($USER);
        $iscore = array_key_exists('iscore', $data) ? $data['iscore'] : self::NONCORE_PRESET;

        // Validate iscore value.
        $allowed = [self::NONCORE_PRESET, self::STARTER_PRESET, self::FULL_PRESET];
        if (!in_array($iscore, $allowed)) {
            $iscore = self::NONCORE_PRESET;
        }

        $preset = [
            'userid' => $USER->id,
            'name' => $name,
            'comments' => $comments,
            'site' => $CFG->wwwroot,
            'author' => $author,
            'moodleversion' => $CFG->version,
            'moodlerelease' => $CFG->release,
            'iscore' => $iscore,
            'timecreated' => time(),
            'timeimported' => 0,
        ];

        $presetid = $DB->insert_record('adminpresets', $preset);
        return $presetid;
    }

    /**
     * Helper method to add a setting item to a preset.
     *
     * @param int $presetid Preset identifier where the item will belong.
     * @param string $name Item name.
     * @param string $value Item value.
     * @param string|null $plugin Item plugin.
     * @param string|null $advname If the item is an advanced setting, the name of the advanced setting should be specified here.
     * @param string|null $advvalue If the item is an advanced setting, the value of the advanced setting should be specified here.
     * @return int The item identificator.
     */
    public static function add_item(int $presetid, string $name, string $value, ?string $plugin = 'none',
            ?string $advname = null, ?string $advvalue = null): int {
        global $DB;

        $presetitem = [
            'adminpresetid' => $presetid,
            'plugin' => $plugin,
            'name' => $name,
            'value' => $value,
        ];
        $itemid = $DB->insert_record('adminpresets_it', $presetitem);

        if (!empty($advname)) {
            $presetadv = [
                'itemid' => $itemid,
                'name' => $advname,
                'value' => $advvalue,
            ];
            $DB->insert_record('adminpresets_it_a', $presetadv);
        }

        return $itemid;
    }

    /**
     * Helper method to add a plugin to a preset.
     *
     * @param int $presetid Preset identifier where the item will belong.
     * @param string $plugin Plugin type.
     * @param string $name Plugin name.
     * @param int $enabled Whether the plugin will be enabled or not.
     * @return int The plugin identificator.
     */
    public static function add_plugin(int $presetid, string $plugin, string $name, int $enabled): int {
        global $DB;

        $pluginentry = [
            'adminpresetid' => $presetid,
            'plugin' => $plugin,
            'name' => $name,
            'enabled' => $enabled,
        ];
        $pluginid = $DB->insert_record('adminpresets_plug', $pluginentry);

        return $pluginid;
    }

    /**
     * Helper method to create default site admin presets and initialize them.
     */
    public static function create_default_presets(): void {
        // Create the "Starter" site admin preset.
        $data = [
            'name' => get_string('starterpreset', 'core_adminpresets'),
            'comments' => get_string('starterpresetdescription', 'core_adminpresets'),
            'iscore' => self::STARTER_PRESET,
        ];
        $presetid = static::create_preset($data);

        // Add settings to the "Starter" site admin preset.
        static::add_item($presetid, 'usecomments', '0');
        static::add_item($presetid, 'usetags', '0');
        static::add_item($presetid, 'enablenotes', '0');
        static::add_item($presetid, 'enableblogs', '0');
        static::add_item($presetid, 'enablebadges', '0');
        static::add_item($presetid, 'enableanalytics', '0');
        static::add_item($presetid, 'enabled', '0', 'core_competency');
        static::add_item($presetid, 'pushcourseratingstouserplans', '0', 'core_competency');
        static::add_item($presetid, 'showdataretentionsummary', '0', 'tool_dataprivacy');
        static::add_item($presetid, 'forum_maxattachments', '3');
        static::add_item($presetid, 'guestloginbutton', '0');
        // Set Activity chooser tabs to "Starred, All, Recommended".
        static::add_item($presetid, 'activitychoosertabmode', '1');

        // Modules: Hide chat, database, external tool (lti), IMS content package (imscp), lesson, SCORM, survey, wiki, workshop.
        static::add_plugin($presetid, 'mod', 'chat', false);
        static::add_plugin($presetid, 'mod', 'data', false);
        static::add_plugin($presetid, 'mod', 'lti', false);
        static::add_plugin($presetid, 'mod', 'imscp', false);
        static::add_plugin($presetid, 'mod', 'lesson', false);
        static::add_plugin($presetid, 'mod', 'scorm', false);
        static::add_plugin($presetid, 'mod', 'survey', false);
        static::add_plugin($presetid, 'mod', 'wiki', false);
        static::add_plugin($presetid, 'mod', 'workshop', false);

        // Availability restrictions: Hide Grouping, User profile.
        static::add_plugin($presetid, 'availability', 'grouping', false);
        static::add_plugin($presetid, 'availability', 'profile', false);

        // Blocks: Hide Activities, Blog menu, Blog tags, Comments, Course completion status, Courses, Flickr,
        // Global search, Latest badges, Learning plans, Logged in user, Login, Main menu, Mentees, Network servers, Online users,
        // Private files, Recent blog entries, Recently accessed courses, Search forums, Section links, Social activities,
        // Starred courses, Tags, YouTube.
        // Hidden by default: Course/site summary, RSS feeds, Self completion, Feedback.
        static::add_plugin($presetid, 'block', 'activity_modules', false);
        static::add_plugin($presetid, 'block', 'blog_menu', false);
        static::add_plugin($presetid, 'block', 'blog_tags', false);
        static::add_plugin($presetid, 'block', 'comments', false);
        static::add_plugin($presetid, 'block', 'completionstatus', false);
        static::add_plugin($presetid, 'block', 'course_summary', false);
        static::add_plugin($presetid, 'block', 'course_list', false);
        static::add_plugin($presetid, 'block', 'tag_flickr', false);
        static::add_plugin($presetid, 'block', 'globalsearch', false);
        static::add_plugin($presetid, 'block', 'badges', false);
        static::add_plugin($presetid, 'block', 'lp', false);
        static::add_plugin($presetid, 'block', 'myprofile', false);
        static::add_plugin($presetid, 'block', 'login', false);
        static::add_plugin($presetid, 'block', 'site_main_menu', false);
        static::add_plugin($presetid, 'block', 'mentees', false);
        static::add_plugin($presetid, 'block', 'mnet_hosts', false);
        static::add_plugin($presetid, 'block', 'private_files', false);
        static::add_plugin($presetid, 'block', 'blog_recent', false);
        static::add_plugin($presetid, 'block', 'rss_client', false);
        static::add_plugin($presetid, 'block', 'search_forums', false);
        static::add_plugin($presetid, 'block', 'section_links', false);
        static::add_plugin($presetid, 'block', 'selfcompletion', false);
        static::add_plugin($presetid, 'block', 'social_activities', false);
        static::add_plugin($presetid, 'block', 'tags', false);
        static::add_plugin($presetid, 'block', 'tag_youtube', false);
        static::add_plugin($presetid, 'block', 'feedback', false);
        static::add_plugin($presetid, 'block', 'online_users', false);
        static::add_plugin($presetid, 'block', 'recentlyaccessedcourses', false);
        static::add_plugin($presetid, 'block', 'starredcourses', false);

        // Course formats: Disable Social format.
        static::add_plugin($presetid, 'format', 'social', false);

        // Data formats: Disable Javascript Object Notation (.json).
        static::add_plugin($presetid, 'dataformat', 'json', false);

        // Enrolments: Disable Cohort sync, Guest access.
        static::add_plugin($presetid, 'enrol', 'cohort', false);
        static::add_plugin($presetid, 'enrol', 'guest', false);

        // Filter: Disable MathJax, Activity names auto-linking.
        static::add_plugin($presetid, 'filter', 'mathjaxloader', TEXTFILTER_DISABLED);
        static::add_plugin($presetid, 'filter', 'activitynames', TEXTFILTER_DISABLED);

        // Question behaviours: Disable Adaptive mode (no penalties), Deferred feedback with CBM, Immediate feedback with CBM.
        static::add_plugin($presetid, 'qbehaviour', 'adaptivenopenalty', false);
        static::add_plugin($presetid, 'qbehaviour', 'deferredcbm', false);
        static::add_plugin($presetid, 'qbehaviour', 'immediatecbm', false);

        // Question types: Disable Calculated, Calculated multichoice, Calculated simple, Drag and drop markers,
        // Drag and drop onto image, Embedded answers (Cloze), Numerical, Random short-answer matching.
        static::add_plugin($presetid, 'qtype', 'calculated', false);
        static::add_plugin($presetid, 'qtype', 'calculatedmulti', false);
        static::add_plugin($presetid, 'qtype', 'calculatedsimple', false);
        static::add_plugin($presetid, 'qtype', 'ddmarker', false);
        static::add_plugin($presetid, 'qtype', 'ddimageortext', false);
        static::add_plugin($presetid, 'qtype', 'multianswer', false);
        static::add_plugin($presetid, 'qtype', 'numerical', false);
        static::add_plugin($presetid, 'qtype', 'randomsamatch', false);

        // Repositories: Disable Server files, URL downloader, Wikimedia.
        static::add_plugin($presetid, 'repository', 'local', false);
        static::add_plugin($presetid, 'repository', 'url', false);
        static::add_plugin($presetid, 'repository', 'wikimedia', false);

        // Text editors: Disable TinyMCE HTML editor.
        static::add_plugin($presetid, 'editor', 'tinymce', false);

        // Create the "Full" site admin preset.
        $data = [
            'name' => get_string('fullpreset', 'core_adminpresets'),
            'comments' => get_string('fullpresetdescription', 'core_adminpresets'),
            'iscore' => self::FULL_PRESET,
        ];
        $presetid = static::create_preset($data);

        // Add settings to the "Full" site admin preset.
        static::add_item($presetid, 'usecomments', '1');
        static::add_item($presetid, 'usetags', '1');
        static::add_item($presetid, 'enablenotes', '1');
        static::add_item($presetid, 'enableblogs', '1');
        static::add_item($presetid, 'enablebadges', '1');
        static::add_item($presetid, 'enableanalytics', '1');
        static::add_item($presetid, 'enabled', '1', 'core_competency');
        static::add_item($presetid, 'pushcourseratingstouserplans', '1', 'core_competency');
        static::add_item($presetid, 'showdataretentionsummary', '1', 'tool_dataprivacy');
        static::add_item($presetid, 'forum_maxattachments', '9');
        static::add_item($presetid, 'guestloginbutton', '1');
        // Set Activity chooser tabs to the default value ("Starred, All, Activities, Resources, Recommended").
        static::add_item($presetid, 'activitychoosertabmode', '0');

        // Modules: Enable chat, database, external tool (lti), IMS content package (imscp), lesson, SCORM, survey, wiki, workshop.
        static::add_plugin($presetid, 'mod', 'chat', true);
        static::add_plugin($presetid, 'mod', 'data', true);
        static::add_plugin($presetid, 'mod', 'lti', true);
        static::add_plugin($presetid, 'mod', 'imscp', true);
        static::add_plugin($presetid, 'mod', 'lesson', true);
        static::add_plugin($presetid, 'mod', 'scorm', true);
        static::add_plugin($presetid, 'mod', 'survey', true);
        static::add_plugin($presetid, 'mod', 'wiki', true);
        static::add_plugin($presetid, 'mod', 'workshop', true);

        // Availability restrictions: Enable Grouping, User profile.
        static::add_plugin($presetid, 'availability', 'grouping', true);
        static::add_plugin($presetid, 'availability', 'profile', true);

        // Blocks: Enable Activities, Blog menu, Blog tags, Comments, Course completion status, Courses, Flickr,
        // Global search, Latest badges, Learning plans, Logged in user, Login, Main menu, Mentees, Network servers, Online users,
        // Private files, Recent blog entries, Recently accessed courses, Search forums, Section links, Social activities,
        // Starred courses, Tags, YouTube.
        // Hidden by default: Course/site summary, RSS feeds, Self completion, Feedback.
        static::add_plugin($presetid, 'block', 'activity_modules', true);
        static::add_plugin($presetid, 'block', 'blog_menu', true);
        static::add_plugin($presetid, 'block', 'blog_tags', true);
        static::add_plugin($presetid, 'block', 'comments', true);
        static::add_plugin($presetid, 'block', 'completionstatus', true);
        static::add_plugin($presetid, 'block', 'course_list', true);
        static::add_plugin($presetid, 'block', 'tag_flickr', true);
        static::add_plugin($presetid, 'block', 'globalsearch', true);
        static::add_plugin($presetid, 'block', 'badges', true);
        static::add_plugin($presetid, 'block', 'lp', true);
        static::add_plugin($presetid, 'block', 'myprofile', true);
        static::add_plugin($presetid, 'block', 'login', true);
        static::add_plugin($presetid, 'block', 'site_main_menu', true);
        static::add_plugin($presetid, 'block', 'mentees', true);
        static::add_plugin($presetid, 'block', 'mnet_hosts', true);
        static::add_plugin($presetid, 'block', 'private_files', true);
        static::add_plugin($presetid, 'block', 'blog_recent', true);
        static::add_plugin($presetid, 'block', 'search_forums', true);
        static::add_plugin($presetid, 'block', 'section_links', true);
        static::add_plugin($presetid, 'block', 'social_activities', true);
        static::add_plugin($presetid, 'block', 'tags', true);
        static::add_plugin($presetid, 'block', 'online_users', true);
        static::add_plugin($presetid, 'block', 'recentlyaccessedcourses', true);
        static::add_plugin($presetid, 'block', 'starredcourses', true);

        // Course formats: Enable Social format.
        static::add_plugin($presetid, 'format', 'social', true);

        // Data formats: Enable Javascript Object Notation (.json).
        static::add_plugin($presetid, 'dataformat', 'json', true);

        // Enrolments: Enable Cohort sync, Guest access.
        static::add_plugin($presetid, 'enrol', 'cohort', true);
        static::add_plugin($presetid, 'enrol', 'guest', true);

        // Filter: Enable MathJax, Activity names auto-linking.
        static::add_plugin($presetid, 'filter', 'mathjaxloader', TEXTFILTER_ON);
        static::add_plugin($presetid, 'filter', 'activitynames', TEXTFILTER_ON);

        // Question behaviours: Enable Adaptive mode (no penalties), Deferred feedback with CBM, Immediate feedback with CBM.
        static::add_plugin($presetid, 'qbehaviour', 'adaptivenopenalty', true);
        static::add_plugin($presetid, 'qbehaviour', 'deferredcbm', true);
        static::add_plugin($presetid, 'qbehaviour', 'immediatecbm', true);

        // Question types: Enable Calculated, Calculated multichoice, Calculated simple, Drag and drop markers,
        // Drag and drop onto image, Embedded answers (Cloze), Numerical, Random short-answer matching.
        static::add_plugin($presetid, 'qtype', 'calculated', true);
        static::add_plugin($presetid, 'qtype', 'calculatedmulti', true);
        static::add_plugin($presetid, 'qtype', 'calculatedsimple', true);
        static::add_plugin($presetid, 'qtype', 'ddmarker', true);
        static::add_plugin($presetid, 'qtype', 'ddimageortext', true);
        static::add_plugin($presetid, 'qtype', 'multianswer', true);
        static::add_plugin($presetid, 'qtype', 'numerical', true);
        static::add_plugin($presetid, 'qtype', 'randomsamatch', true);

        // Repositories: Enable Server files, URL downloader, Wikimedia.
        static::add_plugin($presetid, 'repository', 'local', true);
        static::add_plugin($presetid, 'repository', 'url', true);
        static::add_plugin($presetid, 'repository', 'wikimedia', true);

        // Text editors: Enable TinyMCE HTML editor.
        static::add_plugin($presetid, 'editor', 'tinymce', true);
    }
}
