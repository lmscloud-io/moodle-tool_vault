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

namespace tool_vault\local\restoreactions\upgrade_36;

use tool_vault\api;
use tool_vault\constants;
use tool_vault\site_restore;

/**
 * Upgrade core and standard plugins to 3.6.10 (last release in 3.6 branch)
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upgrade_36 {
    /**
     * Upgrade the restored site to 3.6.10
     *
     * @param site_restore $logger
     * @return void
     */
    public static function upgrade(site_restore $logger) {
        self::upgrade_core($logger);
        \tool_vault\local\restoreactions\upgrade::upgrade_plugins_to_intermediate_release(
            $logger, __DIR__, self::plugin_versions(), '36');
        set_config('upgraderunning', 0);
    }

    /**
     * Upgrade core to 3.6.10
     *
     * @param site_restore $logger
     * @return void
     */
    protected static function upgrade_core(site_restore $logger) {
        global $CFG;
        require_once(__DIR__ ."/core.php");

        try {
            tool_vault_36_core_upgrade($CFG->version);
        } catch (\Throwable $t) {
            $logger->add_to_log("Exception executing core upgrade script: ".
               $t->getMessage(), constants::LOGLEVEL_WARNING);
            api::report_error($t);
        }

        set_config('version', 2018120310.00);
        set_config('release', '3.6.10');
        set_config('branch', '36');
    }

    /**
     * List of standard plugins in 3.6.10 and their exact versions
     *
     * @return array
     */
    protected static function plugin_versions() {
        return [
            "mod_assign" => 2018120300,
            "mod_assignment" => 2018120300,
            "mod_book" => 2018120300,
            "mod_chat" => 2018120300,
            "mod_choice" => 2018120300,
            "mod_data" => 2018120301,
            "mod_feedback" => 2018120300,
            "mod_folder" => 2018120300,
            "mod_forum" => 2018120300,
            "mod_glossary" => 2018120300,
            "mod_imscp" => 2018120300,
            "mod_label" => 2018120300,
            "mod_lesson" => 2018120301,
            "mod_lti" => 2018120300,
            "mod_page" => 2018120300,
            "mod_quiz" => 2018120301,
            "mod_resource" => 2018120300,
            "mod_scorm" => 2018120300,
            "mod_survey" => 2018120300,
            "mod_url" => 2018120300,
            "mod_wiki" => 2018120300,
            "mod_workshop" => 2018120300,
            "assignsubmission_comments" => 2018120300,
            "assignsubmission_file" => 2018120300,
            "assignsubmission_onlinetext" => 2018120300,
            "assignfeedback_comments" => 2018120300,
            "assignfeedback_editpdf" => 2018120300,
            "assignfeedback_file" => 2018120300,
            "assignfeedback_offline" => 2018120300,
            "assignment_offline" => 2018120300,
            "assignment_online" => 2018120300,
            "assignment_upload" => 2018120300,
            "assignment_uploadsingle" => 2018120300,
            "booktool_exportimscp" => 2018120300,
            "booktool_importhtml" => 2018120300,
            "booktool_print" => 2018120300,
            "datafield_checkbox" => 2018120300,
            "datafield_date" => 2018120300,
            "datafield_file" => 2018120300,
            "datafield_latlong" => 2018120300,
            "datafield_menu" => 2018120300,
            "datafield_multimenu" => 2018120300,
            "datafield_number" => 2018120300,
            "datafield_picture" => 2018120300,
            "datafield_radiobutton" => 2018120300,
            "datafield_text" => 2018120300,
            "datafield_textarea" => 2018120300,
            "datafield_url" => 2018120300,
            "datapreset_imagegallery" => 2018120300,
            "ltiservice_gradebookservices" => 2018120300,
            "ltiservice_memberships" => 2018120300,
            "ltiservice_profile" => 2018120300,
            "ltiservice_toolproxy" => 2018120300,
            "ltiservice_toolsettings" => 2018120300,
            "quiz_grading" => 2018120300,
            "quiz_overview" => 2018120300,
            "quiz_responses" => 2018120300,
            "quiz_statistics" => 2018120300,
            "quizaccess_delaybetweenattempts" => 2018120300,
            "quizaccess_ipaddress" => 2018120300,
            "quizaccess_numattempts" => 2018120300,
            "quizaccess_offlineattempts" => 2018120300,
            "quizaccess_openclosedate" => 2018120300,
            "quizaccess_password" => 2018120300,
            "quizaccess_safebrowser" => 2018120300,
            "quizaccess_securewindow" => 2018120300,
            "quizaccess_timelimit" => 2018120300,
            "scormreport_basic" => 2018120300,
            "scormreport_graphs" => 2018120300,
            "scormreport_interactions" => 2018120300,
            "scormreport_objectives" => 2018120300,
            "workshopform_accumulative" => 2018120300,
            "workshopform_comments" => 2018120300,
            "workshopform_numerrors" => 2018120300,
            "workshopform_rubric" => 2018120300,
            "workshopallocation_manual" => 2018120300,
            "workshopallocation_random" => 2018120300,
            "workshopallocation_scheduled" => 2018120300,
            "workshopeval_best" => 2018120300,
            "block_activity_modules" => 2018120300,
            "block_activity_results" => 2018120300,
            "block_admin_bookmarks" => 2018120300,
            "block_badges" => 2018120300,
            "block_blog_menu" => 2018120300,
            "block_blog_recent" => 2018120300,
            "block_blog_tags" => 2018120300,
            "block_calendar_month" => 2018120300,
            "block_calendar_upcoming" => 2018120300,
            "block_comments" => 2018120300,
            "block_community" => 2018120300,
            "block_completionstatus" => 2018120300,
            "block_course_list" => 2018120300,
            "block_course_summary" => 2018120300,
            "block_feedback" => 2018120300,
            "block_globalsearch" => 2018120300,
            "block_glossary_random" => 2018120300,
            "block_html" => 2018120300,
            "block_login" => 2018120300,
            "block_lp" => 2018120300,
            "block_mentees" => 2018120300,
            "block_mnet_hosts" => 2018120300,
            "block_myoverview" => 2018120301,
            "block_myprofile" => 2018120300,
            "block_navigation" => 2018120300,
            "block_news_items" => 2018120300,
            "block_online_users" => 2018120300,
            "block_participants" => 2018120300,
            "block_private_files" => 2018120300,
            "block_quiz_results" => 2018120300,
            "block_recent_activity" => 2018120300,
            "block_recentlyaccessedcourses" => 2018120300,
            "block_recentlyaccesseditems" => 2018120302,
            "block_rss_client" => 2018120300,
            "block_search_forums" => 2018120300,
            "block_section_links" => 2018120300,
            "block_selfcompletion" => 2018120300,
            "block_settings" => 2018120300,
            "block_site_main_menu" => 2018120300,
            "block_social_activities" => 2018120300,
            "block_starredcourses" => 2018120300,
            "block_tag_flickr" => 2018120300,
            "block_tag_youtube" => 2018120300,
            "block_tags" => 2018120300,
            "block_timeline" => 2018120300,
            "qtype_calculated" => 2018120300,
            "qtype_calculatedmulti" => 2018120300,
            "qtype_calculatedsimple" => 2018120300,
            "qtype_ddimageortext" => 2018120300,
            "qtype_ddmarker" => 2018120300,
            "qtype_ddwtos" => 2018120300,
            "qtype_description" => 2018120300,
            "qtype_essay" => 2018120300,
            "qtype_gapselect" => 2018120300,
            "qtype_match" => 2018120300,
            "qtype_missingtype" => 2018120300,
            "qtype_multianswer" => 2018120301,
            "qtype_multichoice" => 2018120300,
            "qtype_numerical" => 2018120300,
            "qtype_random" => 2018120301,
            "qtype_randomsamatch" => 2018120300,
            "qtype_shortanswer" => 2018120300,
            "qtype_truefalse" => 2018120300,
            "qbehaviour_adaptive" => 2018120300,
            "qbehaviour_adaptivenopenalty" => 2018120300,
            "qbehaviour_deferredcbm" => 2018120300,
            "qbehaviour_deferredfeedback" => 2018120300,
            "qbehaviour_immediatecbm" => 2018120300,
            "qbehaviour_immediatefeedback" => 2018120300,
            "qbehaviour_informationitem" => 2018120300,
            "qbehaviour_interactive" => 2018120300,
            "qbehaviour_interactivecountback" => 2018120300,
            "qbehaviour_manualgraded" => 2018120300,
            "qbehaviour_missing" => 2018120300,
            "qformat_aiken" => 2018120300,
            "qformat_blackboard_six" => 2018120300,
            "qformat_examview" => 2018120300,
            "qformat_gift" => 2018120300,
            "qformat_missingword" => 2018120300,
            "qformat_multianswer" => 2018120300,
            "qformat_webct" => 2018120300,
            "qformat_xhtml" => 2018120300,
            "qformat_xml" => 2018120300,
            "filter_activitynames" => 2018120300,
            "filter_algebra" => 2018120300,
            "filter_censor" => 2018120300,
            "filter_data" => 2018120300,
            "filter_emailprotect" => 2018120300,
            "filter_emoticon" => 2018120300,
            "filter_glossary" => 2018120300,
            "filter_mathjaxloader" => 2018120301,
            "filter_mediaplugin" => 2018120300,
            "filter_multilang" => 2018120300,
            "filter_tex" => 2018120300,
            "filter_tidy" => 2018120300,
            "filter_urltolink" => 2018120300,
            "editor_atto" => 2018120300,
            "editor_textarea" => 2018120300,
            "editor_tinymce" => 2018120300,
            "atto_accessibilitychecker" => 2018120300,
            "atto_accessibilityhelper" => 2018120300,
            "atto_align" => 2018120300,
            "atto_backcolor" => 2018120300,
            "atto_bold" => 2018120300,
            "atto_charmap" => 2018120300,
            "atto_clear" => 2018120300,
            "atto_collapse" => 2018120300,
            "atto_emoticon" => 2018120300,
            "atto_equation" => 2018120300,
            "atto_fontcolor" => 2018120300,
            "atto_html" => 2018120300,
            "atto_image" => 2018120300,
            "atto_indent" => 2018120300,
            "atto_italic" => 2018120300,
            "atto_link" => 2018120300,
            "atto_managefiles" => 2018120300,
            "atto_media" => 2018120300,
            "atto_noautolink" => 2018120300,
            "atto_orderedlist" => 2018120300,
            "atto_recordrtc" => 2018120300,
            "atto_rtl" => 2018120300,
            "atto_strike" => 2018120300,
            "atto_subscript" => 2018120300,
            "atto_superscript" => 2018120300,
            "atto_table" => 2018120300,
            "atto_title" => 2018120300,
            "atto_underline" => 2018120300,
            "atto_undo" => 2018120300,
            "atto_unorderedlist" => 2018120300,
            "tinymce_ctrlhelp" => 2018120300,
            "tinymce_managefiles" => 2018120300,
            "tinymce_moodleemoticon" => 2018120300,
            "tinymce_moodleimage" => 2018120300,
            "tinymce_moodlemedia" => 2018120300,
            "tinymce_moodlenolink" => 2018120300,
            "tinymce_pdw" => 2018120300,
            "tinymce_spellchecker" => 2018120300,
            "tinymce_wrap" => 2018120300,
            "enrol_category" => 2018120300,
            "enrol_cohort" => 2018120300,
            "enrol_database" => 2018120300,
            "enrol_flatfile" => 2018120300,
            "enrol_guest" => 2018120300,
            "enrol_imsenterprise" => 2018120300,
            "enrol_ldap" => 2018120300,
            "enrol_lti" => 2018120300,
            "enrol_manual" => 2018120300,
            "enrol_meta" => 2018120300,
            "enrol_mnet" => 2018120300,
            "enrol_paypal" => 2018120300,
            "enrol_self" => 2018120300,
            "auth_cas" => 2018120300,
            "auth_db" => 2018120300,
            "auth_email" => 2018120300,
            "auth_ldap" => 2018120300,
            "auth_lti" => 2018120300,
            "auth_manual" => 2018120300,
            "auth_mnet" => 2018120300,
            "auth_nologin" => 2018120300,
            "auth_none" => 2018120300,
            "auth_oauth2" => 2018120301,
            "auth_shibboleth" => 2018120300,
            "auth_webservice" => 2018120300,
            "tool_analytics" => 2018120300,
            "tool_availabilityconditions" => 2018120300,
            "tool_behat" => 2018120300,
            "tool_capability" => 2018120300,
            "tool_cohortroles" => 2018120300,
            "tool_customlang" => 2018120300,
            "tool_dataprivacy" => 2018120300,
            "tool_dbtransfer" => 2018120300,
            "tool_filetypes" => 2018120300,
            "tool_generator" => 2018120300,
            "tool_health" => 2018120300,
            "tool_httpsreplace" => 2018120300,
            "tool_innodb" => 2018120300,
            "tool_installaddon" => 2018120300,
            "tool_langimport" => 2018120300,
            "tool_log" => 2018120300,
            "tool_lp" => 2018120300,
            "tool_lpimportcsv" => 2018120300,
            "tool_lpmigrate" => 2018120300,
            "tool_messageinbound" => 2018120300,
            "tool_mobile" => 2018120300,
            "tool_monitor" => 2018120300,
            "tool_multilangupgrade" => 2018120300,
            "tool_oauth2" => 2018120300,
            "tool_phpunit" => 2018120300,
            "tool_policy" => 2018120300,
            "tool_profiling" => 2018120300,
            "tool_recyclebin" => 2018120300,
            "tool_replace" => 2018120300,
            "tool_spamcleaner" => 2018120300,
            "tool_task" => 2018120300,
            "tool_templatelibrary" => 2018120300,
            "tool_unsuproles" => 2018120300,
            "tool_uploadcourse" => 2018120300,
            "tool_uploaduser" => 2018120300,
            "tool_usertours" => 2018120301,
            "tool_xmldb" => 2018120300,
            "logstore_database" => 2018120300,
            "logstore_legacy" => 2018120300,
            "logstore_standard" => 2018120300,
            "antivirus_clamav" => 2018120300,
            "availability_completion" => 2018120300,
            "availability_date" => 2018120300,
            "availability_grade" => 2018120300,
            "availability_group" => 2018120300,
            "availability_grouping" => 2018120300,
            "availability_profile" => 2018120300,
            "calendartype_gregorian" => 2018120300,
            "message_airnotifier" => 2018120300,
            "message_email" => 2018120300,
            "message_jabber" => 2018120300,
            "message_popup" => 2018120300,
            "media_html5audio" => 2018120300,
            "media_html5video" => 2018120300,
            "media_swf" => 2018120300,
            "media_videojs" => 2018120300,
            "media_vimeo" => 2018120300,
            "media_youtube" => 2018120300,
            "format_singleactivity" => 2018120300,
            "format_social" => 2018120300,
            "format_topics" => 2018120300,
            "format_weeks" => 2018120300,
            "dataformat_csv" => 2018120300,
            "dataformat_excel" => 2018120300,
            "dataformat_html" => 2018120300,
            "dataformat_json" => 2018120300,
            "dataformat_ods" => 2018120300,
            "profilefield_checkbox" => 2018120300,
            "profilefield_datetime" => 2018120300,
            "profilefield_menu" => 2018120300,
            "profilefield_text" => 2018120300,
            "profilefield_textarea" => 2018120300,
            "report_backups" => 2018120300,
            "report_competency" => 2018120300,
            "report_completion" => 2018120300,
            "report_configlog" => 2018120300,
            "report_courseoverview" => 2018120300,
            "report_eventlist" => 2018120300,
            "report_insights" => 2018120300,
            "report_log" => 2018120300,
            "report_loglive" => 2018120300,
            "report_outline" => 2018120300,
            "report_participation" => 2018120300,
            "report_performance" => 2018120300,
            "report_progress" => 2018120300,
            "report_questioninstances" => 2018120300,
            "report_security" => 2018120300,
            "report_stats" => 2018120300,
            "report_usersessions" => 2018120300,
            "gradeexport_ods" => 2018120300,
            "gradeexport_txt" => 2018120300,
            "gradeexport_xls" => 2018120300,
            "gradeexport_xml" => 2018120300,
            "gradeimport_csv" => 2018120300,
            "gradeimport_direct" => 2018120300,
            "gradeimport_xml" => 2018120300,
            "gradereport_grader" => 2018120300,
            "gradereport_history" => 2018120300,
            "gradereport_outcomes" => 2018120300,
            "gradereport_overview" => 2018120300,
            "gradereport_singleview" => 2018120300,
            "gradereport_user" => 2018120300,
            "gradingform_guide" => 2018120300,
            "gradingform_rubric" => 2018120300,
            "mlbackend_php" => 2018120300,
            "mlbackend_python" => 2018120300,
            "mnetservice_enrol" => 2018120300,
            "webservice_rest" => 2018120300,
            "webservice_soap" => 2018120300,
            "webservice_xmlrpc" => 2018120300,
            "repository_areafiles" => 2018120300,
            "repository_boxnet" => 2018120300,
            "repository_coursefiles" => 2018120300,
            "repository_dropbox" => 2018120300,
            "repository_equella" => 2018120300,
            "repository_filesystem" => 2018120300,
            "repository_flickr" => 2018120300,
            "repository_flickr_public" => 2018120300,
            "repository_googledocs" => 2018120300,
            "repository_local" => 2018120300,
            "repository_merlot" => 2018120300,
            "repository_nextcloud" => 2018120300,
            "repository_onedrive" => 2018120300,
            "repository_picasa" => 2018120300,
            "repository_recent" => 2018120300,
            "repository_s3" => 2018120300,
            "repository_skydrive" => 2018120300,
            "repository_upload" => 2018120300,
            "repository_url" => 2018120300,
            "repository_user" => 2018120300,
            "repository_webdav" => 2018120300,
            "repository_wikimedia" => 2018120300,
            "repository_youtube" => 2018120300,
            "portfolio_boxnet" => 2018120300,
            "portfolio_download" => 2018120300,
            "portfolio_flickr" => 2018120300,
            "portfolio_googledocs" => 2018120300,
            "portfolio_mahara" => 2018120300,
            "portfolio_picasa" => 2018120300,
            "search_simpledb" => 2018120300,
            "search_solr" => 2018120300,
            "cachestore_apcu" => 2018120300,
            "cachestore_file" => 2018120300,
            "cachestore_memcached" => 2018120300,
            "cachestore_mongodb" => 2018120300,
            "cachestore_redis" => 2018120300,
            "cachestore_session" => 2018120300,
            "cachestore_static" => 2018120300,
            "cachelock_file" => 2018120300,
            "fileconverter_googledrive" => 2018120300,
            "fileconverter_unoconv" => 2018120300,
            "theme_boost" => 2018120300,
            "theme_bootstrapbase" => 2018120300,
            "theme_clean" => 2018120300,
            "theme_more" => 2018120300,
        ];
    }
}
