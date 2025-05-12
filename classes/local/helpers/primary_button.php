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

namespace tool_vault\local\helpers;

use single_button;

/**
 * Wrapper for single_button that creates a primary button in any Moodle version.
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class primary_button extends single_button {
    /**
     * Constructor
     *
     * @param string $text Button text
     * @param array $attributes Additional attributes
     * @param bool $disabled Whether the button is disabled
     * @param bool $isprimary Whether the button is primary or secondary
     */
    public function __construct(string $text, array $attributes = [], bool $disabled = false, bool $isprimary = true) {
        global $CFG, $PAGE;
        $url = $PAGE->url;
        if ((int)$CFG->branch == 401) {
            parent::__construct(
                $url,
                $text,
                'get',
                $isprimary,
                $attributes);
        } else {
            parent::__construct(
                $url,
                $text,
                'get',
                $isprimary ? single_button::BUTTON_PRIMARY : single_button::BUTTON_SECONDARY,
                $attributes);
        }
        if ($disabled) {
            $this->disabled = true;
        }
    }

    /**
     * Create a button for starting a dry run.
     *
     * @param string $backupkey
     * @param bool $encrypted
     * @param bool $disabled
     * @param string|null $label
     * @return single_button
     */
    public static function dryrun_button(string $backupkey, bool $encrypted, bool $disabled, ?string $label = null): single_button {
        $label = $label ?? get_string('startdryrun', 'tool_vault');
        $attributes = [
            'data-action' => 'startdryrun',
            'data-backupkey' => $backupkey,
            'data-encrypted' => $encrypted ? 1 : 0,
        ];
        return new self($label, $attributes, $disabled, false);
    }

    /**
     * Create a button for starting a restore.
     *
     * @param string $backupkey
     * @param bool $encrypted
     * @param bool $disabled
     * @param string|null $label
     * @return single_button
     */
    public static function restore_button(string $backupkey, bool $encrypted, bool $disabled,
            ?string $label = null): single_button {
        $label = $label ?? get_string('startrestore', 'tool_vault');
        $attributes = [
            'data-action' => 'startrestore',
            'data-backupkey' => $backupkey,
            'data-encrypted' => $encrypted ? 1 : 0,
        ];
        return new self($label, $attributes, $disabled, false);
    }

    /**
     * Create a button for resuming a restore.
     *
     * @param int $restoreid
     * @param bool $encrypted
     * @return single_button
     */
    public static function resume_button(int $restoreid, bool $encrypted): single_button {
        $attributes = [
            'data-action' => 'resumerestore',
            'data-restoreid' => $restoreid,
            'data-encrypted' => $encrypted ? 1 : 0,
        ];
        return new self(get_string('resume', 'tool_vault'), $attributes, false, false);
    }
}
