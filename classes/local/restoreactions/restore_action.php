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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_vault\local\restoreactions;

use tool_vault\site_restore;

/**
 * Base class for various restore actions
 *
 * @package     tool_vault
 * @copyright   2022 Marina Glancy <marina.glancy@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class restore_action {
    /** @var string */
    const STAGE_BEFORE = 'before';
    /** @var string */
    const STAGE_AFTER_DB = 'afterdb';
    /** @var string */
    const STAGE_AFTER_DATA = 'afterdata';
    /** @var string */
    const STAGE_AFTER_ALL = 'afterall';
    /** @var array */
    const ACTIONS = [
        'before' => [
            clear_caches::class,
            cleanup_existing_files::class,
        ],
        'afterdb' => [
            kill_sessions::class,
            clear_caches::class,
            recalc_version_hash::class,
        ],
        'afterdata' => [
        ],
        'afterall' => [
            uninstall_missing_plugins::class,
            cleanup_existing_files::class,
        ],
    ];

    /**
     * Execute before restore
     *
     * @param site_restore $logger
     * @return void
     */
    final public static function execute_before_restore(site_restore $logger) {
        self::execute_actions($logger, self::STAGE_BEFORE);
    }

    /**
     * Execute after DB restore
     *
     * @param site_restore $logger
     * @return void
     */
    final public static function execute_after_db_restore(site_restore $logger) {
        self::execute_actions($logger, self::STAGE_AFTER_DB);
    }

    /**
     * Execute after dataroot restore
     *
     * @param site_restore $logger
     * @return void
     */
    final public static function execute_after_dataroot_restore(site_restore $logger) {
        self::execute_actions($logger, self::STAGE_AFTER_DATA);
    }

    /**
     * Execute after the full restore
     *
     * @param site_restore $logger
     * @return void
     */
    final public static function execute_after_restore(site_restore $logger) {
        self::execute_actions($logger, self::STAGE_AFTER_ALL);
    }

    /**
     * Executes all actions for the stage
     *
     * @param site_restore $logger
     * @param string $stage
     * @return void
     */
    private static function execute_actions(site_restore $logger, string $stage) {
        $actions = self::ACTIONS[$stage] ?? [];
        foreach ($actions as $action) {
            (new $action())->execute($logger, $stage);
        }
    }

    /**
     * Executes individual action
     *
     * @param site_restore $logger
     * @param string $stage
     * @return void
     */
    abstract public function execute(site_restore $logger, string $stage);
}
