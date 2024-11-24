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

namespace tool_vault\local\restoreactions\upgrade_27\helpers;

use calendar_event;
use stdClass;

/**
 * Class mod_workshop_helper
 *
 * @package    tool_vault
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_workshop_helper {

    /**
     * Updates the calendar events associated to the given workshop
     *
     * @param stdClass $workshop the workshop instance record
     * @param int $cmid course module id
     */
    public static function workshop_calendar_update(stdClass $workshop, $cmid) {
        global $DB;

        // get the currently registered events so that we can re-use their ids
        $currentevents = $DB->get_records('event', array('modulename' => 'workshop', 'instance' => $workshop->id));

        // the common properties for all events
        $base = new stdClass();
        $base->description  = format_module_intro('workshop', $workshop, $cmid, false);
        $base->courseid     = $workshop->course;
        $base->groupid      = 0;
        $base->userid       = 0;
        $base->modulename   = 'workshop';
        $base->eventtype    = 'pluginname';
        $base->instance     = $workshop->id;
        $base->visible      = instance_is_visible('workshop', $workshop);
        $base->timeduration = 0;

        if ($workshop->submissionstart) {
            $event = clone($base);
            $event->name = get_string('submissionstartevent', 'mod_workshop', $workshop->name);
            $event->timestart = $workshop->submissionstart;
            if ($reusedevent = array_shift($currentevents)) {
                $event->id = $reusedevent->id;
            } else {
                // should not be set but just in case
                unset($event->id);
            }
            // update() will reuse a db record if the id field is set
            $eventobj = new calendar_event($event); // TODO avoid using core classes and methods.
            $eventobj->update($event, false);
        }

        if ($workshop->submissionend) {
            $event = clone($base);
            $event->name = get_string('submissionendevent', 'mod_workshop', $workshop->name);
            $event->timestart = $workshop->submissionend;
            if ($reusedevent = array_shift($currentevents)) {
                $event->id = $reusedevent->id;
            } else {
                // should not be set but just in case
                unset($event->id);
            }
            // update() will reuse a db record if the id field is set
            $eventobj = new calendar_event($event);
            $eventobj->update($event, false);
        }

        if ($workshop->assessmentstart) {
            $event = clone($base);
            $event->name = get_string('assessmentstartevent', 'mod_workshop', $workshop->name);
            $event->timestart = $workshop->assessmentstart;
            if ($reusedevent = array_shift($currentevents)) {
                $event->id = $reusedevent->id;
            } else {
                // should not be set but just in case
                unset($event->id);
            }
            // update() will reuse a db record if the id field is set
            $eventobj = new calendar_event($event);
            $eventobj->update($event, false);
        }

        if ($workshop->assessmentend) {
            $event = clone($base);
            $event->name = get_string('assessmentendevent', 'mod_workshop', $workshop->name);
            $event->timestart = $workshop->assessmentend;
            if ($reusedevent = array_shift($currentevents)) {
                $event->id = $reusedevent->id;
            } else {
                // should not be set but just in case
                unset($event->id);
            }
            // update() will reuse a db record if the id field is set
            $eventobj = new calendar_event($event);
            $eventobj->update($event, false);
        }

        // delete any leftover events
        foreach ($currentevents as $oldevent) {
            $oldevent = calendar_event::load($oldevent);
            $oldevent->delete();
        }
    }
}
