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

namespace tool_vault\local\restoreactions\upgrade_27;

use tool_vault\api;
use tool_vault\constants;
use tool_vault\site_restore;

/**
 * Upgrade core and standard plugins to 2.7.20 (last release in 2.7 branch)
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upgrade_27 {
    /**
     * Upgrade the restored site to 2.7.20
     *
     * @param site_restore $logger
     * @return void
     */
    public static function upgrade(site_restore $logger) {
        self::upgrade_core($logger);
        self::upgrade_plugins($logger);
        set_config('upgraderunning', 0);
    }

    /**
     * Upgrade core to 2.7.20
     *
     * @param site_restore $logger
     * @return void
     */
    protected static function upgrade_core(site_restore $logger) {
        global $CFG;
        require_once(__DIR__ ."/core.php");

        try {
            tool_vault_27_core_upgrade($CFG->version);
        } catch (\Throwable $t) {
            $logger->add_to_log("Exception executing core upgrade script: ".
               $t->getMessage(), constants::LOGLEVEL_WARNING);
            api::report_error($t);
        }

        set_config('version', 2016052318.00);
        set_config('release', '2.7.20');
        set_config('branch', '27');
    }

    /**
     * Upgrade all standard plugins to 2.7.20
     *
     * @param site_restore $logger
     * @return void
     */
    protected static function upgrade_plugins(site_restore $logger) {
        global $DB;
        $allcurversions = $DB->get_records_menu('config_plugins', ['name' => 'version'], '', 'plugin, value');
        foreach (self::plugin_versions() as $plugin => $version) {
            if (empty($allcurversions[$plugin])) {
                // Standard plugin {$plugin} not found. It will be installed during the full upgrade.
                continue;
            }
            if (file_exists(__DIR__ ."/". $plugin .".php")) {
                require_once(__DIR__ ."/". $plugin .".php");
                $pluginshort = preg_replace("/^mod_/", "", $plugin);
                $funcname = "tool_vault_27_xmldb_{$pluginshort}_upgrade";
                try {
                    $funcname($allcurversions[$plugin]);
                } catch (\Throwable $t) {
                    $logger->add_to_log("Exception executing upgrade script for plugin {$plugin}: ".
                        $t->getMessage(), constants::LOGLEVEL_WARNING);
                    api::report_error($t);
                }
            }
            set_config('version', $version, $plugin);
        }
    }

    /**
     * List of standard plugins in 2.7.20 and their exact versions
     *
     * @return array
     */
    protected static function plugin_versions() {
        return [
            "mod_assign" => 2014051202,
            "mod_assignment" => 2014051200,
            "mod_book" => 2014051200,
            "mod_chat" => 2014051200,
            "mod_choice" => 2014051200,
            "mod_data" => 2014051200,
            "mod_feedback" => 2014051200,
            "mod_folder" => 2014051200,
            "mod_forum" => 2014051204,
            "mod_glossary" => 2014051200,
            "mod_imscp" => 2014051200,
            "mod_label" => 2014051200,
            "mod_lesson" => 2014051202,
            "mod_lti" => 2014051200,
            "mod_page" => 2014051200,
            "mod_quiz" => 2014051201,
            "mod_resource" => 2014051200,
            "mod_scorm" => 2014051202,
            "mod_survey" => 2014051200,
            "mod_url" => 2014051200,
            "mod_wiki" => 2014051200,
            "mod_workshop" => 2014051201,
            "assignsubmission_comments" => 2014051200,
            "assignsubmission_file" => 2014051200,
            "assignsubmission_onlinetext" => 2014051200,
            "assignfeedback_comments" => 2014051200,
            "assignfeedback_editpdf" => 2014051200,
            "assignfeedback_file" => 2014051200,
            "assignfeedback_offline" => 2014051200,
            "assignment_offline" => 2014051200,
            "assignment_online" => 2014051200,
            "assignment_upload" => 2014051200,
            "assignment_uploadsingle" => 2014051200,
            "booktool_exportimscp" => 2014051200,
            "booktool_importhtml" => 2014051200,
            "booktool_print" => 2014051200,
            "datafield_checkbox" => 2014051200,
            "datafield_date" => 2014051200,
            "datafield_file" => 2014051200,
            "datafield_latlong" => 2014051200,
            "datafield_menu" => 2014051200,
            "datafield_multimenu" => 2014051200,
            "datafield_number" => 2014051200,
            "datafield_picture" => 2014051200,
            "datafield_radiobutton" => 2014051200,
            "datafield_text" => 2014051200,
            "datafield_textarea" => 2014051200,
            "datafield_url" => 2014051200,
            "datapreset_imagegallery" => 2014051200,
            "quiz_grading" => 2014051200,
            "quiz_overview" => 2014051200,
            "quiz_responses" => 2014051200,
            "quiz_statistics" => 2014051200,
            "quizaccess_delaybetweenattempts" => 2014051200,
            "quizaccess_ipaddress" => 2014051200,
            "quizaccess_numattempts" => 2014051200,
            "quizaccess_openclosedate" => 2014051200,
            "quizaccess_password" => 2014051200,
            "quizaccess_safebrowser" => 2014051200,
            "quizaccess_securewindow" => 2014051200,
            "quizaccess_timelimit" => 2014051200,
            "scormreport_basic" => 2014051200,
            "scormreport_graphs" => 2014051200,
            "scormreport_interactions" => 2014051200,
            "scormreport_objectives" => 2014051200,
            "workshopform_accumulative" => 2014051200,
            "workshopform_comments" => 2014051200,
            "workshopform_numerrors" => 2014051200,
            "workshopform_rubric" => 2014051200,
            "workshopallocation_manual" => 2014051200,
            "workshopallocation_random" => 2014051200,
            "workshopallocation_scheduled" => 2014051200,
            "workshopeval_best" => 2014051200,
            "block_activity_modules" => 2014051200,
            "block_admin_bookmarks" => 2014051200,
            "block_badges" => 2014051200,
            "block_blog_menu" => 2014051200,
            "block_blog_recent" => 2014051200,
            "block_blog_tags" => 2014051200,
            "block_calendar_month" => 2014051200,
            "block_calendar_upcoming" => 2014051200,
            "block_comments" => 2014051200,
            "block_community" => 2014051200,
            "block_completionstatus" => 2014051200,
            "block_course_list" => 2014051200,
            "block_course_overview" => 2014051200,
            "block_course_summary" => 2014051200,
            "block_feedback" => 2014051200,
            "block_glossary_random" => 2014051200,
            "block_html" => 2014051200,
            "block_login" => 2014051200,
            "block_mentees" => 2014051200,
            "block_messages" => 2014051200,
            "block_mnet_hosts" => 2014051200,
            "block_myprofile" => 2014051200,
            "block_navigation" => 2014051200,
            "block_news_items" => 2014051200,
            "block_online_users" => 2014051200,
            "block_participants" => 2014051200,
            "block_private_files" => 2014051200,
            "block_quiz_results" => 2014051200,
            "block_recent_activity" => 2014051200,
            "block_rss_client" => 2014051200,
            "block_search_forums" => 2014051200,
            "block_section_links" => 2014051200,
            "block_selfcompletion" => 2014051200,
            "block_settings" => 2014051200,
            "block_site_main_menu" => 2014051200,
            "block_social_activities" => 2014051200,
            "block_tag_flickr" => 2014051200,
            "block_tag_youtube" => 2014051200,
            "block_tags" => 2014051200,
            "qtype_calculated" => 2014051200,
            "qtype_calculatedmulti" => 2014051200,
            "qtype_calculatedsimple" => 2014051200,
            "qtype_description" => 2014051200,
            "qtype_essay" => 2014051200,
            "qtype_match" => 2014051200,
            "qtype_missingtype" => 2014051200,
            "qtype_multianswer" => 2014051200,
            "qtype_multichoice" => 2014051200,
            "qtype_numerical" => 2014051200,
            "qtype_random" => 2014051201,
            "qtype_randomsamatch" => 2014051200,
            "qtype_shortanswer" => 2014051200,
            "qtype_truefalse" => 2014051200,
            "qbehaviour_adaptive" => 2014051200,
            "qbehaviour_adaptivenopenalty" => 2014051200,
            "qbehaviour_deferredcbm" => 2014051200,
            "qbehaviour_deferredfeedback" => 2014051200,
            "qbehaviour_immediatecbm" => 2014051200,
            "qbehaviour_immediatefeedback" => 2014051200,
            "qbehaviour_informationitem" => 2014051200,
            "qbehaviour_interactive" => 2014051200,
            "qbehaviour_interactivecountback" => 2014051200,
            "qbehaviour_manualgraded" => 2014051200,
            "qbehaviour_missing" => 2014051200,
            "qformat_aiken" => 2014051200,
            "qformat_blackboard_six" => 2014051200,
            "qformat_examview" => 2014051200,
            "qformat_gift" => 2014051200,
            "qformat_learnwise" => 2014051200,
            "qformat_missingword" => 2014051200,
            "qformat_multianswer" => 2014051200,
            "qformat_webct" => 2014051200,
            "qformat_xhtml" => 2014051200,
            "qformat_xml" => 2014051200,
            "filter_activitynames" => 2014051200,
            "filter_algebra" => 2014051200,
            "filter_censor" => 2014051200,
            "filter_data" => 2014051200,
            "filter_emailprotect" => 2014051200,
            "filter_emoticon" => 2014051200,
            "filter_glossary" => 2014051200,
            "filter_mathjaxloader" => 2014051202,
            "filter_mediaplugin" => 2014051200,
            "filter_multilang" => 2014051200,
            "filter_tex" => 2014051200,
            "filter_tidy" => 2014051200,
            "filter_urltolink" => 2014051200,
            "editor_atto" => 2014051200,
            "editor_textarea" => 2014051200,
            "editor_tinymce" => 2014051202,
            "atto_accessibilitychecker" => 2014051200,
            "atto_accessibilityhelper" => 2014051200,
            "atto_align" => 2014051200,
            "atto_backcolor" => 2014051200,
            "atto_bold" => 2014051200,
            "atto_charmap" => 2014051200,
            "atto_clear" => 2014051200,
            "atto_collapse" => 2014051200,
            "atto_emoticon" => 2014051200,
            "atto_equation" => 2014051200,
            "atto_fontcolor" => 2014051200,
            "atto_html" => 2014051200,
            "atto_image" => 2014051200,
            "atto_indent" => 2014051200,
            "atto_italic" => 2014051200,
            "atto_link" => 2014051200,
            "atto_managefiles" => 2014051200,
            "atto_media" => 2014051200,
            "atto_noautolink" => 2014051200,
            "atto_orderedlist" => 2014051200,
            "atto_rtl" => 2014051200,
            "atto_strike" => 2014051200,
            "atto_subscript" => 2014051200,
            "atto_superscript" => 2014051200,
            "atto_table" => 2014051200,
            "atto_title" => 2014051200,
            "atto_underline" => 2014051200,
            "atto_undo" => 2014051200,
            "atto_unorderedlist" => 2014051200,
            "tinymce_ctrlhelp" => 2014051200,
            "tinymce_dragmath" => 2014051200,
            "tinymce_managefiles" => 2014051200,
            "tinymce_moodleemoticon" => 2014051200,
            "tinymce_moodleimage" => 2014051200,
            "tinymce_moodlemedia" => 2014051200,
            "tinymce_moodlenolink" => 2014051200,
            "tinymce_pdw" => 2014051200,
            "tinymce_spellchecker" => 2014051200,
            "tinymce_wrap" => 2014051200,
            "enrol_category" => 2014051200,
            "enrol_cohort" => 2014051200,
            "enrol_database" => 2014051200,
            "enrol_flatfile" => 2014051200,
            "enrol_guest" => 2014051200,
            "enrol_imsenterprise" => 2014051200,
            "enrol_ldap" => 2014051200,
            "enrol_manual" => 2014051200,
            "enrol_meta" => 2014051200,
            "enrol_mnet" => 2014051200,
            "enrol_paypal" => 2014051200,
            "enrol_self" => 2014051200,
            "auth_cas" => 2014051200,
            "auth_db" => 2014051200,
            "auth_email" => 2014051200,
            "auth_fc" => 2014051200,
            "auth_imap" => 2014051200,
            "auth_ldap" => 2014051200,
            "auth_manual" => 2014051200,
            "auth_mnet" => 2014051200,
            "auth_nntp" => 2014051200,
            "auth_nologin" => 2014051200,
            "auth_none" => 2014051200,
            "auth_pam" => 2014051200,
            "auth_pop3" => 2014051200,
            "auth_radius" => 2014051200,
            "auth_shibboleth" => 2014051200,
            "auth_webservice" => 2014051200,
            "tool_assignmentupgrade" => 2014051200,
            "tool_availabilityconditions" => 2014051200,
            "tool_behat" => 2014051200,
            "tool_capability" => 2014051200,
            "tool_customlang" => 2014051200,
            "tool_dbtransfer" => 2014051200,
            "tool_generator" => 2014051200,
            "tool_health" => 2014051200,
            "tool_innodb" => 2014051200,
            "tool_installaddon" => 2014051200,
            "tool_langimport" => 2014051200,
            "tool_log" => 2014051200,
            "tool_multilangupgrade" => 2014051200,
            "tool_phpunit" => 2014051200,
            "tool_profiling" => 2014051200,
            "tool_replace" => 2014051200,
            "tool_spamcleaner" => 2014051200,
            "tool_task" => 2014051200,
            "tool_timezoneimport" => 2014051200,
            "tool_unsuproles" => 2014051200,
            "tool_uploadcourse" => 2014051200,
            "tool_uploaduser" => 2014051200,
            "tool_xmldb" => 2014051200,
            "logstore_database" => 2014051200,
            "logstore_legacy" => 2014051200,
            "logstore_standard" => 2014051200,
            "availability_completion" => 2014051200,
            "availability_date" => 2014051200,
            "availability_grade" => 2014051200,
            "availability_group" => 2014051200,
            "availability_grouping" => 2014051200,
            "availability_profile" => 2014051200,
            "calendartype_gregorian" => 2014051200,
            "message_airnotifier" => 2014051200,
            "message_email" => 2014051200,
            "message_jabber" => 2014051200,
            "message_popup" => 2014051200,
            "format_singleactivity" => 2014051200,
            "format_social" => 2014051200,
            "format_topics" => 2014051200,
            "format_weeks" => 2014051200,
            "profilefield_checkbox" => 2014051200,
            "profilefield_datetime" => 2014051200,
            "profilefield_menu" => 2014051200,
            "profilefield_text" => 2014051200,
            "profilefield_textarea" => 2014051200,
            "report_backups" => 2014051200,
            "report_completion" => 2014051200,
            "report_configlog" => 2014051200,
            "report_courseoverview" => 2014051200,
            "report_eventlist" => 2014051200,
            "report_log" => 2014051200,
            "report_loglive" => 2014051200,
            "report_outline" => 2014051200,
            "report_participation" => 2014051200,
            "report_performance" => 2014051200,
            "report_progress" => 2014051200,
            "report_questioninstances" => 2014051200,
            "report_security" => 2014051200,
            "report_stats" => 2014051200,
            "gradeexport_ods" => 2014051200,
            "gradeexport_txt" => 2014051200,
            "gradeexport_xls" => 2014051200,
            "gradeexport_xml" => 2014051200,
            "gradeimport_csv" => 2014051200,
            "gradeimport_xml" => 2014051200,
            "gradereport_grader" => 2014051200,
            "gradereport_outcomes" => 2014051200,
            "gradereport_overview" => 2014051200,
            "gradereport_user" => 2014051200,
            "gradingform_guide" => 2014051200,
            "gradingform_rubric" => 2014051200,
            "mnetservice_enrol" => 2014051200,
            "webservice_amf" => 2014051200,
            "webservice_rest" => 2014051200,
            "webservice_soap" => 2014051200,
            "webservice_xmlrpc" => 2014051200,
            "repository_alfresco" => 2014051200,
            "repository_areafiles" => 2014051200,
            "repository_boxnet" => 2014051200,
            "repository_coursefiles" => 2014051200,
            "repository_dropbox" => 2014051200,
            "repository_equella" => 2014051200,
            "repository_filesystem" => 2014051200,
            "repository_flickr" => 2014051200,
            "repository_flickr_public" => 2014051200,
            "repository_googledocs" => 2014051200,
            "repository_local" => 2014051200,
            "repository_merlot" => 2014051200,
            "repository_picasa" => 2014051200,
            "repository_recent" => 2014051200,
            "repository_s3" => 2014051200,
            "repository_skydrive" => 2014051200,
            "repository_upload" => 2014051200,
            "repository_url" => 2014051200,
            "repository_user" => 2014051200,
            "repository_webdav" => 2014051200,
            "repository_wikimedia" => 2014051200,
            "repository_youtube" => 2014051200,
            "portfolio_boxnet" => 2014051200,
            "portfolio_download" => 2014051200,
            "portfolio_flickr" => 2014051200,
            "portfolio_googledocs" => 2014051200,
            "portfolio_mahara" => 2014051200,
            "portfolio_picasa" => 2014051200,
            "cachestore_file" => 2014051200,
            "cachestore_memcache" => 2014051200,
            "cachestore_memcached" => 2014051200,
            "cachestore_mongodb" => 2014051200,
            "cachestore_session" => 2014051200,
            "cachestore_static" => 2014051200,
            "cachelock_file" => 2014051200,
            "theme_base" => 2014051200,
            "theme_bootstrapbase" => 2014051200,
            "theme_canvas" => 2014051200,
            "theme_clean" => 2014051200,
            "theme_more" => 2014051200,
        ];
    }
}
