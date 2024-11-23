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

namespace tool_vault\local\restoreactions\upgrade_31;

use tool_vault\api;
use tool_vault\constants;
use tool_vault\site_restore;

/**
 * Upgrade core and standard plugins to 3.1.18 (last release in 3.1 branch)
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upgrade_31 {
    /**
     * Upgrade the restored site to 3.1.18
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
     * Upgrade core to 3.1.18
     *
     * @param site_restore $logger
     * @return void
     */
    protected static function upgrade_core(site_restore $logger) {
        global $CFG;
        require_once(__DIR__ ."/core.php");

        try {
            tool_vault_31_core_upgrade($CFG->version);
        } catch (\Throwable $t) {
            $logger->add_to_log("Exception executing core upgrade script: ".
               $t->getMessage(), constants::LOGLEVEL_WARNING);
            api::report_error($t);
        }

        set_config('version', 2016052318.00);
        set_config('release', '3.1.18');
        set_config('branch', '31');
    }

    /**
     * Upgrade all standard plugins to 3.1.18
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
                $funcname = "tool_vault_31_xmldb_{$pluginshort}_upgrade";
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
     * List of standard plugins in 3.1.18 and their exact versions
     *
     * @return array
     */
    protected static function plugin_versions() {
        return [
            "mod_assign" => 2016052301,
            "mod_assignment" => 2016052300,
            "mod_book" => 2016052300,
            "mod_chat" => 2016052300,
            "mod_choice" => 2016052300,
            "mod_data" => 2016052300,
            "mod_feedback" => 2016052301,
            "mod_folder" => 2016052300,
            "mod_forum" => 2016052300,
            "mod_glossary" => 2016052300,
            "mod_imscp" => 2016052300,
            "mod_label" => 2016052300,
            "mod_lesson" => 2016052301,
            "mod_lti" => 2016052300,
            "mod_page" => 2016052300,
            "mod_quiz" => 2016052301,
            "mod_resource" => 2016052300,
            "mod_scorm" => 2016052301,
            "mod_survey" => 2016052300,
            "mod_url" => 2016052300,
            "mod_wiki" => 2016052300,
            "mod_workshop" => 2016052300,
            "assignsubmission_comments" => 2016052300,
            "assignsubmission_file" => 2016052300,
            "assignsubmission_onlinetext" => 2016052300,
            "assignfeedback_comments" => 2016052300,
            "assignfeedback_editpdf" => 2016052301,
            "assignfeedback_file" => 2016052300,
            "assignfeedback_offline" => 2016052300,
            "assignment_offline" => 2016052300,
            "assignment_online" => 2016052300,
            "assignment_upload" => 2016052300,
            "assignment_uploadsingle" => 2016052300,
            "booktool_exportimscp" => 2016052300,
            "booktool_importhtml" => 2016052300,
            "booktool_print" => 2016052300,
            "datafield_checkbox" => 2016052300,
            "datafield_date" => 2016052300,
            "datafield_file" => 2016052300,
            "datafield_latlong" => 2016052300,
            "datafield_menu" => 2016052300,
            "datafield_multimenu" => 2016052300,
            "datafield_number" => 2016052300,
            "datafield_picture" => 2016052300,
            "datafield_radiobutton" => 2016052300,
            "datafield_text" => 2016052300,
            "datafield_textarea" => 2016052300,
            "datafield_url" => 2016052300,
            "datapreset_imagegallery" => 2016052300,
            "ltiservice_memberships" => 2016052300,
            "ltiservice_profile" => 2016052300,
            "ltiservice_toolproxy" => 2016052300,
            "ltiservice_toolsettings" => 2016052300,
            "quiz_grading" => 2016052300,
            "quiz_overview" => 2016052300,
            "quiz_responses" => 2016052300,
            "quiz_statistics" => 2016052300,
            "quizaccess_delaybetweenattempts" => 2016052300,
            "quizaccess_ipaddress" => 2016052300,
            "quizaccess_numattempts" => 2016052300,
            "quizaccess_openclosedate" => 2016052300,
            "quizaccess_password" => 2016052300,
            "quizaccess_safebrowser" => 2016052300,
            "quizaccess_securewindow" => 2016052300,
            "quizaccess_timelimit" => 2016052300,
            "scormreport_basic" => 2016052300,
            "scormreport_graphs" => 2016052300,
            "scormreport_interactions" => 2016052300,
            "scormreport_objectives" => 2016052300,
            "workshopform_accumulative" => 2016052300,
            "workshopform_comments" => 2016052300,
            "workshopform_numerrors" => 2016052300,
            "workshopform_rubric" => 2016052300,
            "workshopallocation_manual" => 2016052300,
            "workshopallocation_random" => 2016052300,
            "workshopallocation_scheduled" => 2016052300,
            "workshopeval_best" => 2016052300,
            "block_activity_modules" => 2016052300,
            "block_activity_results" => 2016052300,
            "block_admin_bookmarks" => 2016052300,
            "block_badges" => 2016052300,
            "block_blog_menu" => 2016052300,
            "block_blog_recent" => 2016052300,
            "block_blog_tags" => 2016052300,
            "block_calendar_month" => 2016052300,
            "block_calendar_upcoming" => 2016052300,
            "block_comments" => 2016052300,
            "block_community" => 2016052300,
            "block_completionstatus" => 2016052300,
            "block_course_list" => 2016052300,
            "block_course_overview" => 2016052300,
            "block_course_summary" => 2016052300,
            "block_feedback" => 2016052300,
            "block_globalsearch" => 2016052300,
            "block_glossary_random" => 2016052300,
            "block_html" => 2016052300,
            "block_login" => 2016052300,
            "block_lp" => 2016052300,
            "block_mentees" => 2016052300,
            "block_messages" => 2016052300,
            "block_mnet_hosts" => 2016052300,
            "block_myprofile" => 2016052300,
            "block_navigation" => 2016052300,
            "block_news_items" => 2016052300,
            "block_online_users" => 2016052300,
            "block_participants" => 2016052300,
            "block_private_files" => 2016052300,
            "block_quiz_results" => 2016052300,
            "block_recent_activity" => 2016052300,
            "block_rss_client" => 2016052300,
            "block_search_forums" => 2016052300,
            "block_section_links" => 2016052300,
            "block_selfcompletion" => 2016052300,
            "block_settings" => 2016052300,
            "block_site_main_menu" => 2016052300,
            "block_social_activities" => 2016052300,
            "block_tag_flickr" => 2016052300,
            "block_tag_youtube" => 2016052300,
            "block_tags" => 2016052300,
            "qtype_calculated" => 2016052300,
            "qtype_calculatedmulti" => 2016052300,
            "qtype_calculatedsimple" => 2016052300,
            "qtype_ddimageortext" => 2016052300,
            "qtype_ddmarker" => 2016052300,
            "qtype_ddwtos" => 2016052300,
            "qtype_description" => 2016052300,
            "qtype_essay" => 2016052300,
            "qtype_gapselect" => 2016052300,
            "qtype_match" => 2016052300,
            "qtype_missingtype" => 2016052300,
            "qtype_multianswer" => 2016052300,
            "qtype_multichoice" => 2016052300,
            "qtype_numerical" => 2016052300,
            "qtype_random" => 2016052300,
            "qtype_randomsamatch" => 2016052300,
            "qtype_shortanswer" => 2016052300,
            "qtype_truefalse" => 2016052300,
            "qbehaviour_adaptive" => 2016052300,
            "qbehaviour_adaptivenopenalty" => 2016052300,
            "qbehaviour_deferredcbm" => 2016052300,
            "qbehaviour_deferredfeedback" => 2016052300,
            "qbehaviour_immediatecbm" => 2016052300,
            "qbehaviour_immediatefeedback" => 2016052300,
            "qbehaviour_informationitem" => 2016052300,
            "qbehaviour_interactive" => 2016052300,
            "qbehaviour_interactivecountback" => 2016052300,
            "qbehaviour_manualgraded" => 2016052300,
            "qbehaviour_missing" => 2016052300,
            "qformat_aiken" => 2016052300,
            "qformat_blackboard_six" => 2016052300,
            "qformat_examview" => 2016052300,
            "qformat_gift" => 2016052300,
            "qformat_missingword" => 2016052300,
            "qformat_multianswer" => 2016052300,
            "qformat_webct" => 2016052300,
            "qformat_xhtml" => 2016052300,
            "qformat_xml" => 2016052300,
            "filter_activitynames" => 2016052300,
            "filter_algebra" => 2016052300,
            "filter_censor" => 2016052300,
            "filter_data" => 2016052300,
            "filter_emailprotect" => 2016052300,
            "filter_emoticon" => 2016052300,
            "filter_glossary" => 2016052300,
            "filter_mathjaxloader" => 2016052302,
            "filter_mediaplugin" => 2016052300,
            "filter_multilang" => 2016052300,
            "filter_tex" => 2016052300,
            "filter_tidy" => 2016052300,
            "filter_urltolink" => 2016052300,
            "editor_atto" => 2016052300,
            "editor_textarea" => 2016052300,
            "editor_tinymce" => 2016052300,
            "atto_accessibilitychecker" => 2016052300,
            "atto_accessibilityhelper" => 2016052300,
            "atto_align" => 2016052300,
            "atto_backcolor" => 2016052300,
            "atto_bold" => 2016052300,
            "atto_charmap" => 2016052300,
            "atto_clear" => 2016052300,
            "atto_collapse" => 2016052300,
            "atto_emoticon" => 2016052300,
            "atto_equation" => 2016052300,
            "atto_fontcolor" => 2016052300,
            "atto_html" => 2016052300,
            "atto_image" => 2016052300,
            "atto_indent" => 2016052300,
            "atto_italic" => 2016052300,
            "atto_link" => 2016052300,
            "atto_managefiles" => 2016052300,
            "atto_media" => 2016052300,
            "atto_noautolink" => 2016052300,
            "atto_orderedlist" => 2016052300,
            "atto_rtl" => 2016052300,
            "atto_strike" => 2016052300,
            "atto_subscript" => 2016052300,
            "atto_superscript" => 2016052300,
            "atto_table" => 2016052300,
            "atto_title" => 2016052300,
            "atto_underline" => 2016052300,
            "atto_undo" => 2016052300,
            "atto_unorderedlist" => 2016052300,
            "tinymce_ctrlhelp" => 2016052300,
            "tinymce_managefiles" => 2016052300,
            "tinymce_moodleemoticon" => 2016052300,
            "tinymce_moodleimage" => 2016052300,
            "tinymce_moodlemedia" => 2016052300,
            "tinymce_moodlenolink" => 2016052300,
            "tinymce_pdw" => 2016052300,
            "tinymce_spellchecker" => 2016052300,
            "tinymce_wrap" => 2016052300,
            "enrol_category" => 2016052300,
            "enrol_cohort" => 2016052300,
            "enrol_database" => 2016052300,
            "enrol_flatfile" => 2016052300,
            "enrol_guest" => 2016052300,
            "enrol_imsenterprise" => 2016052300,
            "enrol_ldap" => 2016052300,
            "enrol_lti" => 2016052300,
            "enrol_manual" => 2016052300,
            "enrol_meta" => 2016052300,
            "enrol_mnet" => 2016052300,
            "enrol_paypal" => 2016052300,
            "enrol_self" => 2016052301,
            "auth_cas" => 2016052300,
            "auth_db" => 2016052300,
            "auth_email" => 2016052300,
            "auth_fc" => 2016052300,
            "auth_imap" => 2016052300,
            "auth_ldap" => 2016052300,
            "auth_lti" => 2016052300,
            "auth_manual" => 2016052300,
            "auth_mnet" => 2016052300,
            "auth_nntp" => 2016052300,
            "auth_nologin" => 2016052300,
            "auth_none" => 2016052300,
            "auth_pam" => 2016052300,
            "auth_pop3" => 2016052300,
            "auth_radius" => 2016052300,
            "auth_shibboleth" => 2016052300,
            "auth_webservice" => 2016052300,
            "tool_assignmentupgrade" => 2016052300,
            "tool_availabilityconditions" => 2016052300,
            "tool_behat" => 2016052300,
            "tool_capability" => 2016052300,
            "tool_cohortroles" => 2016052300,
            "tool_customlang" => 2016052300,
            "tool_dbtransfer" => 2016052300,
            "tool_filetypes" => 2016052300,
            "tool_generator" => 2016052300,
            "tool_health" => 2016052300,
            "tool_innodb" => 2016052300,
            "tool_installaddon" => 2016052300,
            "tool_langimport" => 2016052300,
            "tool_log" => 2016052300,
            "tool_lp" => 2016052300,
            "tool_lpmigrate" => 2016052300,
            "tool_messageinbound" => 2016052300,
            "tool_mobile" => 2016052300,
            "tool_monitor" => 2016052306,
            "tool_multilangupgrade" => 2016052300,
            "tool_phpunit" => 2016052300,
            "tool_profiling" => 2016052300,
            "tool_recyclebin" => 2016052300,
            "tool_replace" => 2016052300,
            "tool_spamcleaner" => 2016052300,
            "tool_task" => 2016052300,
            "tool_templatelibrary" => 2016052300,
            "tool_unsuproles" => 2016052300,
            "tool_uploadcourse" => 2016052300,
            "tool_uploaduser" => 2016052300,
            "tool_xmldb" => 2016052300,
            "logstore_database" => 2016052300,
            "logstore_legacy" => 2016052300,
            "logstore_standard" => 2016052300,
            "antivirus_clamav" => 2016052300,
            "availability_completion" => 2016052300,
            "availability_date" => 2016052300,
            "availability_grade" => 2016052300,
            "availability_group" => 2016052300,
            "availability_grouping" => 2016052300,
            "availability_profile" => 2016052300,
            "calendartype_gregorian" => 2016052300,
            "message_airnotifier" => 2016052300,
            "message_email" => 2016052300,
            "message_jabber" => 2016052300,
            "message_popup" => 2016052300,
            "format_singleactivity" => 2016052300,
            "format_social" => 2016052300,
            "format_topics" => 2016052300,
            "format_weeks" => 2016052300,
            "dataformat_csv" => 2016052300,
            "dataformat_excel" => 2016052300,
            "dataformat_html" => 2016052300,
            "dataformat_json" => 2016052300,
            "dataformat_ods" => 2016052300,
            "profilefield_checkbox" => 2016052300,
            "profilefield_datetime" => 2016052300,
            "profilefield_menu" => 2016052300,
            "profilefield_text" => 2016052300,
            "profilefield_textarea" => 2016052300,
            "report_backups" => 2016052300,
            "report_competency" => 2016052300,
            "report_completion" => 2016052300,
            "report_configlog" => 2016052300,
            "report_courseoverview" => 2016052300,
            "report_eventlist" => 2016052300,
            "report_log" => 2016052300,
            "report_loglive" => 2016052300,
            "report_outline" => 2016052300,
            "report_participation" => 2016052300,
            "report_performance" => 2016052300,
            "report_progress" => 2016052300,
            "report_questioninstances" => 2016052300,
            "report_search" => 2016052300,
            "report_security" => 2016052300,
            "report_stats" => 2016052300,
            "report_usersessions" => 2016052300,
            "gradeexport_ods" => 2016052300,
            "gradeexport_txt" => 2016052300,
            "gradeexport_xls" => 2016052300,
            "gradeexport_xml" => 2016052300,
            "gradeimport_csv" => 2016052300,
            "gradeimport_direct" => 2016052300,
            "gradeimport_xml" => 2016052300,
            "gradereport_grader" => 2016052300,
            "gradereport_history" => 2016052300,
            "gradereport_outcomes" => 2016052300,
            "gradereport_overview" => 2016052300,
            "gradereport_singleview" => 2016052300,
            "gradereport_user" => 2016052300,
            "gradingform_guide" => 2016052300,
            "gradingform_rubric" => 2016052300,
            "mnetservice_enrol" => 2016052300,
            "webservice_rest" => 2016052300,
            "webservice_soap" => 2016052300,
            "webservice_xmlrpc" => 2016052300,
            "repository_alfresco" => 2016052300,
            "repository_areafiles" => 2016052300,
            "repository_boxnet" => 2016052300,
            "repository_coursefiles" => 2016052300,
            "repository_dropbox" => 2016052301,
            "repository_equella" => 2016052300,
            "repository_filesystem" => 2016052300,
            "repository_flickr" => 2016052300,
            "repository_flickr_public" => 2016052300,
            "repository_googledocs" => 2016052300,
            "repository_local" => 2016052300,
            "repository_merlot" => 2016052300,
            "repository_picasa" => 2016052300,
            "repository_recent" => 2016052300,
            "repository_s3" => 2016052300,
            "repository_skydrive" => 2016052300,
            "repository_upload" => 2016052300,
            "repository_url" => 2016052300,
            "repository_user" => 2016052300,
            "repository_webdav" => 2016052300,
            "repository_wikimedia" => 2016052300,
            "repository_youtube" => 2016052300,
            "portfolio_boxnet" => 2016052300,
            "portfolio_download" => 2016052300,
            "portfolio_flickr" => 2016052300,
            "portfolio_googledocs" => 2016052300,
            "portfolio_mahara" => 2016052300,
            "portfolio_picasa" => 2016052300,
            "search_solr" => 2016052300,
            "cachestore_file" => 2016052300,
            "cachestore_memcache" => 2016052300,
            "cachestore_memcached" => 2016052300,
            "cachestore_mongodb" => 2016052300,
            "cachestore_session" => 2016052300,
            "cachestore_static" => 2016052300,
            "cachelock_file" => 2016052300,
            "theme_base" => 2016052300,
            "theme_bootstrapbase" => 2016052300,
            "theme_canvas" => 2016052300,
            "theme_clean" => 2016052300,
            "theme_more" => 2016052300,
        ];
    }
}