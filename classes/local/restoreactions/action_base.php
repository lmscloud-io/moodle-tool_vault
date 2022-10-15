<?php
// This file is part of Moodle - https://moodle.org/
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
abstract class action_base {
    /** @var string */
    const STAGE_BEFORE = 'before';
    /** @var string */
    const STAGE_AFTER_DB = 'afterdb';
    /** @var string */
    const STAGE_AFTER_DATA = 'afterdata';
    /** @var string */
    const STAGE_AFTER_ALL = 'afterall';

    /**
     * Executes all actions for the stage
     *
     * @param site_restore $logger
     * @param string $stage
     * @return void
     */
    public static function execute_all(site_restore $logger, string $stage) {
        $componentinstances = \core_component::get_component_classes_in_namespace('tool_vault',
            '\\local\\restoreactions');
        foreach (array_keys($componentinstances) as $instanceclass) {
            // Create instance if this is not abstract class.
            $reflectionclass = new \ReflectionClass($instanceclass);
            if (!$reflectionclass->isAbstract()) {
                /** @var action_base $instance */
                $instance = new $instanceclass();
                if ($instance->applies_to_stage($stage)) {
                    $instance->execute($logger, $stage);
                }
            }
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

    /**
     * Check if the action should be executed for the stage
     *
     * @param string $stage
     * @return bool
     */
    abstract protected function applies_to_stage(string $stage): bool;
}
