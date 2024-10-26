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

namespace tool_vault\local\checks;

use tool_vault\local\uiactions\restore_checkreport;

/**
 * Class check_base_restore
 *
 * @package    tool_vault
 * @copyright  2024 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class check_base_restore extends check_base {

    /**
     * Link to this check full review (if applicable)
     *
     * @return \moodle_url|null
     */
    public function get_fullreport_url(): ?\moodle_url {
        return restore_checkreport::url(['id' => $this->get_model()->id]);
    }

    /**
     * Displays a badge with text
     *
     * @param string $class one of: 'badge-info', 'badge-warning', 'badge-danger'
     * @param string $text text inside the badge
     * @return string
     */
    protected static function badge(string $class, string $text): string {
        if (!in_array($class, ['badge-info', 'badge-warning', 'badge-danger'])) {
            throw new \coding_exception('Unrecognised badge class: '.s($class));
        }
        return "<span class=\"badge {$class} mr-1\">{$text}</span><span class=\"accesshide\">. </span>";
    }

    /**
     * Displays an "Error" badge
     *
     * @param string|null $text
     * @return string
     */
    protected static function badge_error(?string $text = null): string {
        return self::badge('badge-danger', $text ?? get_string('error', 'moodle'));
    }

    /**
     * Displays a "Warning" badge
     *
     * @param string|null $text
     * @return string
     */
    protected static function badge_warning(?string $text = null): string {
        return self::badge('badge-warning', $text ?? get_string('warning', 'moodle'));
    }

    /**
     * Displays an "Info" badge
     *
     * @param string|null $text
     * @return string
     */
    protected static function badge_info(?string $text = null): string {
        return self::badge('badge-info', $text ?? get_string('statusinfo', 'moodle'));
    }

    /**
     * Get last check of this type for the specified backup
     *
     * @param array $parentselect - select query for the parent, i.e. ['backupkey' => $backupkey] or ['id' => $id]
     * @param int $maxage if >0, max number of seconds since the operation finished
     * @return check_base_restore|null
     */
    public static function get_last_check_for_parent(array $parentselect, int $maxage = 0): ?self {
        $parent = \tool_vault\local\models\operation_model::get_last_of(
            [\tool_vault\local\models\restore_model::class, \tool_vault\local\models\dryrun_model::class],
            $parentselect);
        if (!$parent || !$parent->get_finished_time() || ($maxage && $parent->get_finished_time() < time() - $maxage)) {
            return null;
        }
        $checks = check_base::get_all_checks_for_operation($parent->id);
        foreach ($checks as $check) {
            if ($check instanceof static) {
                $check->parent = $parent;
                return $check;
            }
        }
        return null;
    }
}
