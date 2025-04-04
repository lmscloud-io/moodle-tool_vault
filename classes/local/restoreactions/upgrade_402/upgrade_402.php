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

namespace tool_vault\local\restoreactions\upgrade_402;

use tool_vault\api;
use tool_vault\constants;
use tool_vault\site_restore;

/**
 * Class upgrade_402
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upgrade_402 {
    /**
     * Upgrade the restored site to 4.2.3
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
     * Upgrade core to 4.2.3
     *
     * @param site_restore $logger
     * @return void
     */
    protected static function upgrade_core(site_restore $logger) {
        global $CFG;
        require_once(__DIR__ ."/core.php");

        try {
            tool_vault_402_core_upgrade($CFG->version);
        } catch (\Throwable $t) {
            $logger->add_to_log("Exception executing core upgrade script: ".
               $t->getMessage(), constants::LOGLEVEL_WARNING);
            api::report_error($t);
        }

        set_config('version', 2023042403.00);
        set_config('release', '4.2.3');
        set_config('branch', '402');
    }

    /**
     * Upgrade all standard plugins to 4.2.3
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
            if (file_exists(__DIR__ ."/". $plugin .".php") && \core_component::get_component_directory($plugin)) {
                require_once(__DIR__ ."/". $plugin .".php");
                $pluginshort = preg_replace("/^mod_/", "", $plugin);
                $funcname = "tool_vault_402_xmldb_{$pluginshort}_upgrade";
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
     * List of standard plugins in 4.2.3 and their exact versions
     *
     * @return array
     */
    protected static function plugin_versions() {
        return [
            "mod_assign" => 2023042400,
            "mod_bigbluebuttonbn" => 2023042400,
            "mod_book" => 2023042400,
            "mod_chat" => 2023042400,
            "mod_choice" => 2023042400,
            "mod_data" => 2023042401,
            "mod_feedback" => 2023042400,
            "mod_folder" => 2023042400,
            "mod_forum" => 2023042400,
            "mod_glossary" => 2023042400,
            "mod_h5pactivity" => 2023042401,
            "mod_imscp" => 2023042400,
            "mod_label" => 2023042400,
            "mod_lesson" => 2023042400,
            "mod_lti" => 2023042400,
            "mod_page" => 2023042400,
            "mod_quiz" => 2023042400,
            "mod_resource" => 2023042400,
            "mod_scorm" => 2023042400,
            "mod_survey" => 2023042400,
            "mod_url" => 2023042400,
            "mod_wiki" => 2023042400,
            "mod_workshop" => 2023042400,
            "assignsubmission_comments" => 2023042400,
            "assignsubmission_file" => 2023042400,
            "assignsubmission_onlinetext" => 2023042400,
            "assignfeedback_comments" => 2023042400,
            "assignfeedback_editpdf" => 2023042400,
            "assignfeedback_file" => 2023042400,
            "assignfeedback_offline" => 2023042400,
            "booktool_exportimscp" => 2023042400,
            "booktool_importhtml" => 2023042400,
            "booktool_print" => 2023042400,
            "datafield_checkbox" => 2023042400,
            "datafield_date" => 2023042400,
            "datafield_file" => 2023042400,
            "datafield_latlong" => 2023042400,
            "datafield_menu" => 2023042400,
            "datafield_multimenu" => 2023042400,
            "datafield_number" => 2023042400,
            "datafield_picture" => 2023042400,
            "datafield_radiobutton" => 2023042400,
            "datafield_text" => 2023042400,
            "datafield_textarea" => 2023042400,
            "datafield_url" => 2023042400,
            "datapreset_imagegallery" => 2023042400,
            "datapreset_journal" => 2023042400,
            "datapreset_proposals" => 2023042400,
            "datapreset_resources" => 2023042400,
            "forumreport_summary" => 2023042400,
            "ltiservice_basicoutcomes" => 2023042400,
            "ltiservice_gradebookservices" => 2023042400,
            "ltiservice_memberships" => 2023042400,
            "ltiservice_profile" => 2023042400,
            "ltiservice_toolproxy" => 2023042400,
            "ltiservice_toolsettings" => 2023042400,
            "quiz_grading" => 2023042400,
            "quiz_overview" => 2023042400,
            "quiz_responses" => 2023042400,
            "quiz_statistics" => 2023042404,
            "quizaccess_delaybetweenattempts" => 2023042400,
            "quizaccess_ipaddress" => 2023042400,
            "quizaccess_numattempts" => 2023042400,
            "quizaccess_offlineattempts" => 2023042400,
            "quizaccess_openclosedate" => 2023042400,
            "quizaccess_password" => 2023042400,
            "quizaccess_seb" => 2023042400,
            "quizaccess_securewindow" => 2023042400,
            "quizaccess_timelimit" => 2023042400,
            "scormreport_basic" => 2023042400,
            "scormreport_graphs" => 2023042400,
            "scormreport_interactions" => 2023042400,
            "scormreport_objectives" => 2023042400,
            "workshopform_accumulative" => 2023042400,
            "workshopform_comments" => 2023042400,
            "workshopform_numerrors" => 2023042400,
            "workshopform_rubric" => 2023042400,
            "workshopallocation_manual" => 2023042400,
            "workshopallocation_random" => 2023042400,
            "workshopallocation_scheduled" => 2023042400,
            "workshopeval_best" => 2023042400,
            "block_accessreview" => 2023042400,
            "block_activity_modules" => 2023042400,
            "block_activity_results" => 2023042400,
            "block_admin_bookmarks" => 2023042400,
            "block_badges" => 2023042400,
            "block_blog_menu" => 2023042400,
            "block_blog_recent" => 2023042400,
            "block_blog_tags" => 2023042400,
            "block_calendar_month" => 2023042400,
            "block_calendar_upcoming" => 2023042400,
            "block_comments" => 2023042400,
            "block_completionstatus" => 2023042400,
            "block_course_list" => 2023042400,
            "block_course_summary" => 2023042400,
            "block_feedback" => 2023042400,
            "block_globalsearch" => 2023042400,
            "block_glossary_random" => 2023042400,
            "block_html" => 2023042400,
            "block_login" => 2023042400,
            "block_lp" => 2023042400,
            "block_mentees" => 2023042400,
            "block_mnet_hosts" => 2023042400,
            "block_myoverview" => 2023042400,
            "block_myprofile" => 2023042400,
            "block_navigation" => 2023042400,
            "block_news_items" => 2023042400,
            "block_online_users" => 2023042400,
            "block_private_files" => 2023042400,
            "block_recent_activity" => 2023042400,
            "block_recentlyaccessedcourses" => 2023042400,
            "block_recentlyaccesseditems" => 2023042400,
            "block_rss_client" => 2023042400,
            "block_search_forums" => 2023042400,
            "block_section_links" => 2023042400,
            "block_selfcompletion" => 2023042400,
            "block_settings" => 2023042400,
            "block_site_main_menu" => 2023042400,
            "block_social_activities" => 2023042400,
            "block_starredcourses" => 2023042400,
            "block_tag_flickr" => 2023042400,
            "block_tag_youtube" => 2023042400,
            "block_tags" => 2023042400,
            "block_timeline" => 2023042400,
            "qtype_calculated" => 2023042400,
            "qtype_calculatedmulti" => 2023042400,
            "qtype_calculatedsimple" => 2023042400,
            "qtype_ddimageortext" => 2023042400,
            "qtype_ddmarker" => 2023042400,
            "qtype_ddwtos" => 2023042400,
            "qtype_description" => 2023042400,
            "qtype_essay" => 2023042400,
            "qtype_gapselect" => 2023042400,
            "qtype_match" => 2023042400,
            "qtype_missingtype" => 2023042400,
            "qtype_multianswer" => 2023042400,
            "qtype_multichoice" => 2023042400,
            "qtype_numerical" => 2023042400,
            "qtype_random" => 2023042400,
            "qtype_randomsamatch" => 2023042400,
            "qtype_shortanswer" => 2023042400,
            "qtype_truefalse" => 2023042400,
            "qbank_bulkmove" => 2023042400,
            "qbank_columnsortorder" => 2023042400,
            "qbank_comment" => 2023042400,
            "qbank_customfields" => 2023042400,
            "qbank_deletequestion" => 2023042400,
            "qbank_editquestion" => 2023042400,
            "qbank_exportquestions" => 2023042400,
            "qbank_exporttoxml" => 2023042400,
            "qbank_history" => 2023042400,
            "qbank_importquestions" => 2023042400,
            "qbank_managecategories" => 2023042400,
            "qbank_previewquestion" => 2023042400,
            "qbank_statistics" => 2023042400,
            "qbank_tagquestion" => 2023042400,
            "qbank_usage" => 2023042400,
            "qbank_viewcreator" => 2023042400,
            "qbank_viewquestionname" => 2023042400,
            "qbank_viewquestiontext" => 2023042400,
            "qbank_viewquestiontype" => 2023042400,
            "qbehaviour_adaptive" => 2023042400,
            "qbehaviour_adaptivenopenalty" => 2023042400,
            "qbehaviour_deferredcbm" => 2023042400,
            "qbehaviour_deferredfeedback" => 2023042400,
            "qbehaviour_immediatecbm" => 2023042400,
            "qbehaviour_immediatefeedback" => 2023042400,
            "qbehaviour_informationitem" => 2023042400,
            "qbehaviour_interactive" => 2023042400,
            "qbehaviour_interactivecountback" => 2023042400,
            "qbehaviour_manualgraded" => 2023042400,
            "qbehaviour_missing" => 2023042400,
            "qformat_aiken" => 2023042400,
            "qformat_blackboard_six" => 2023042400,
            "qformat_gift" => 2023042400,
            "qformat_missingword" => 2023042400,
            "qformat_multianswer" => 2023042400,
            "qformat_xhtml" => 2023042400,
            "qformat_xml" => 2023042400,
            "filter_activitynames" => 2023042400,
            "filter_algebra" => 2023042400,
            "filter_data" => 2023042400,
            "filter_displayh5p" => 2023042400,
            "filter_emailprotect" => 2023042400,
            "filter_emoticon" => 2023042400,
            "filter_glossary" => 2023042400,
            "filter_mathjaxloader" => 2023042400,
            "filter_mediaplugin" => 2023042400,
            "filter_multilang" => 2023042400,
            "filter_tex" => 2023042400,
            "filter_tidy" => 2023042400,
            "filter_urltolink" => 2023042400,
            "editor_atto" => 2023042400,
            "editor_textarea" => 2023042400,
            "editor_tiny" => 2023042400,
            "atto_accessibilitychecker" => 2023042400,
            "atto_accessibilityhelper" => 2023042400,
            "atto_align" => 2023042400,
            "atto_backcolor" => 2023042400,
            "atto_bold" => 2023042400,
            "atto_charmap" => 2023042400,
            "atto_clear" => 2023042400,
            "atto_collapse" => 2023042400,
            "atto_emojipicker" => 2023042400,
            "atto_emoticon" => 2023042400,
            "atto_equation" => 2023042400,
            "atto_fontcolor" => 2023042400,
            "atto_h5p" => 2023042400,
            "atto_html" => 2023042400,
            "atto_image" => 2023042400,
            "atto_indent" => 2023042400,
            "atto_italic" => 2023042400,
            "atto_link" => 2023042400,
            "atto_managefiles" => 2023042400,
            "atto_media" => 2023042400,
            "atto_noautolink" => 2023042400,
            "atto_orderedlist" => 2023042400,
            "atto_recordrtc" => 2023042400,
            "atto_rtl" => 2023042400,
            "atto_strike" => 2023042400,
            "atto_subscript" => 2023042400,
            "atto_superscript" => 2023042400,
            "atto_table" => 2023042400,
            "atto_title" => 2023042400,
            "atto_underline" => 2023042400,
            "atto_undo" => 2023042400,
            "atto_unorderedlist" => 2023042400,
            "tiny_accessibilitychecker" => 2023042400,
            "tiny_autosave" => 2023042400,
            "tiny_equation" => 2023042400,
            "tiny_h5p" => 2023042400,
            "tiny_link" => 2023042400,
            "tiny_media" => 2023042400,
            "tiny_recordrtc" => 2023042400,
            "enrol_category" => 2023042400,
            "enrol_cohort" => 2023042400,
            "enrol_database" => 2023042400,
            "enrol_fee" => 2023042400,
            "enrol_flatfile" => 2023042400,
            "enrol_guest" => 2023042400,
            "enrol_imsenterprise" => 2023042400,
            "enrol_ldap" => 2023042400,
            "enrol_lti" => 2023042400,
            "enrol_manual" => 2023042400,
            "enrol_meta" => 2023042400,
            "enrol_mnet" => 2023042400,
            "enrol_paypal" => 2023042400,
            "enrol_self" => 2023042400,
            "auth_cas" => 2023042400,
            "auth_db" => 2023042400,
            "auth_email" => 2023042400,
            "auth_ldap" => 2023042400,
            "auth_lti" => 2023042400,
            "auth_manual" => 2023042400,
            "auth_mnet" => 2023042400,
            "auth_nologin" => 2023042400,
            "auth_none" => 2023042400,
            "auth_oauth2" => 2023042400,
            "auth_shibboleth" => 2023042400,
            "auth_webservice" => 2023042400,
            "tool_admin_presets" => 2023042400,
            "tool_analytics" => 2023042400,
            "tool_availabilityconditions" => 2023042400,
            "tool_behat" => 2023042401,
            "tool_brickfield" => 2023042400,
            "tool_capability" => 2023042400,
            "tool_cohortroles" => 2023042401,
            "tool_componentlibrary" => 2023042400,
            "tool_customlang" => 2023042400,
            "tool_dataprivacy" => 2023042400,
            "tool_dbtransfer" => 2023042400,
            "tool_filetypes" => 2023042400,
            "tool_generator" => 2023042400,
            "tool_httpsreplace" => 2023042400,
            "tool_innodb" => 2023042400,
            "tool_installaddon" => 2023042400,
            "tool_langimport" => 2023042400,
            "tool_licensemanager" => 2023042400,
            "tool_log" => 2023042400,
            "tool_lp" => 2023042400,
            "tool_lpimportcsv" => 2023042400,
            "tool_lpmigrate" => 2023042400,
            "tool_messageinbound" => 2023042400,
            "tool_mobile" => 2023042400,
            "tool_monitor" => 2023042400,
            "tool_moodlenet" => 2023042400,
            "tool_multilangupgrade" => 2023042400,
            "tool_oauth2" => 2023042400,
            "tool_phpunit" => 2023042400,
            "tool_policy" => 2023042400,
            "tool_profiling" => 2023042400,
            "tool_recyclebin" => 2023042400,
            "tool_replace" => 2023042400,
            "tool_spamcleaner" => 2023042400,
            "tool_task" => 2023042400,
            "tool_templatelibrary" => 2023042400,
            "tool_unsuproles" => 2023042400,
            "tool_uploadcourse" => 2023042400,
            "tool_uploaduser" => 2023042400,
            "tool_usertours" => 2023042400,
            "tool_xmldb" => 2023042400,
            "logstore_database" => 2023042400,
            "logstore_standard" => 2023042400,
            "antivirus_clamav" => 2023042400,
            "availability_completion" => 2023042400,
            "availability_date" => 2023042400,
            "availability_grade" => 2023042400,
            "availability_group" => 2023042400,
            "availability_grouping" => 2023042400,
            "availability_profile" => 2023042400,
            "calendartype_gregorian" => 2023042400,
            "customfield_checkbox" => 2023042400,
            "customfield_date" => 2023042400,
            "customfield_select" => 2023042400,
            "customfield_text" => 2023042400,
            "customfield_textarea" => 2023042400,
            "message_airnotifier" => 2023042400,
            "message_email" => 2023042400,
            "message_popup" => 2023042400,
            "media_html5audio" => 2023042400,
            "media_html5video" => 2023042400,
            "media_videojs" => 2023042400,
            "media_vimeo" => 2023042400,
            "media_youtube" => 2023042400,
            "format_singleactivity" => 2023042400,
            "format_social" => 2023042400,
            "format_topics" => 2023042400,
            "format_weeks" => 2023042400,
            "dataformat_csv" => 2023042400,
            "dataformat_excel" => 2023042400,
            "dataformat_html" => 2023042400,
            "dataformat_json" => 2023042400,
            "dataformat_ods" => 2023042400,
            "dataformat_pdf" => 2023042400,
            "profilefield_checkbox" => 2023042400,
            "profilefield_datetime" => 2023042400,
            "profilefield_menu" => 2023042400,
            "profilefield_social" => 2023042400,
            "profilefield_text" => 2023042400,
            "profilefield_textarea" => 2023042400,
            "report_backups" => 2023042400,
            "report_competency" => 2023042400,
            "report_completion" => 2023042400,
            "report_configlog" => 2023042400,
            "report_courseoverview" => 2023042400,
            "report_eventlist" => 2023042400,
            "report_infectedfiles" => 2023042400,
            "report_insights" => 2023042400,
            "report_log" => 2023042400,
            "report_loglive" => 2023042400,
            "report_outline" => 2023042400,
            "report_participation" => 2023042400,
            "report_performance" => 2023042400,
            "report_progress" => 2023042400,
            "report_questioninstances" => 2023042400,
            "report_security" => 2023042400,
            "report_stats" => 2023042400,
            "report_status" => 2023042400,
            "report_usersessions" => 2023042400,
            "gradeexport_ods" => 2023042400,
            "gradeexport_txt" => 2023042400,
            "gradeexport_xls" => 2023042400,
            "gradeexport_xml" => 2023042400,
            "gradeimport_csv" => 2023042400,
            "gradeimport_direct" => 2023042400,
            "gradeimport_xml" => 2023042400,
            "gradereport_grader" => 2023042400,
            "gradereport_history" => 2023042400,
            "gradereport_outcomes" => 2023042400,
            "gradereport_overview" => 2023042400,
            "gradereport_singleview" => 2023042400,
            "gradereport_summary" => 2023042400,
            "gradereport_user" => 2023042400,
            "gradingform_guide" => 2023042400,
            "gradingform_rubric" => 2023042400,
            "mlbackend_php" => 2023042400,
            "mlbackend_python" => 2023042400,
            "mnetservice_enrol" => 2023042400,
            "webservice_rest" => 2023042400,
            "webservice_soap" => 2023042400,
            "repository_areafiles" => 2023042400,
            "repository_contentbank" => 2023042400,
            "repository_coursefiles" => 2023042400,
            "repository_dropbox" => 2023042400,
            "repository_equella" => 2023042400,
            "repository_filesystem" => 2023042400,
            "repository_flickr" => 2023042400,
            "repository_flickr_public" => 2023042400,
            "repository_googledocs" => 2023042400,
            "repository_local" => 2023042400,
            "repository_merlot" => 2023042400,
            "repository_nextcloud" => 2023042400,
            "repository_onedrive" => 2023042400,
            "repository_recent" => 2023042400,
            "repository_s3" => 2023042400,
            "repository_upload" => 2023042400,
            "repository_url" => 2023042400,
            "repository_user" => 2023042400,
            "repository_webdav" => 2023042400,
            "repository_wikimedia" => 2023042400,
            "repository_youtube" => 2023042400,
            "portfolio_download" => 2023042400,
            "portfolio_flickr" => 2023042400,
            "portfolio_googledocs" => 2023042400,
            "portfolio_mahara" => 2023042400,
            "search_simpledb" => 2023042400,
            "search_solr" => 2023042400,
            "cachestore_apcu" => 2023042400,
            "cachestore_file" => 2023042400,
            "cachestore_redis" => 2023042400,
            "cachestore_session" => 2023042400,
            "cachestore_static" => 2023042400,
            "cachelock_file" => 2023042400,
            "fileconverter_googledrive" => 2023042400,
            "fileconverter_unoconv" => 2023042400,
            "contenttype_h5p" => 2023042400,
            "theme_boost" => 2023042400,
            "theme_classic" => 2023042400,
            "h5plib_v124" => 2023042400,
            "paygw_paypal" => 2023042400,
        ];
    }
}
