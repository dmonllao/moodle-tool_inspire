<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * Any access before the starts indicator.
 *
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_research\local\indicator;

defined('MOODLE_INTERNAL') || die();

/**
 * Any access before the start indicator.
 *
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class any_access_before_start extends base {

    public static function min_contextlevel_depth() {
        // Does not make much sense at context system, but it is calculable.
        return CONTEXT_SYSTEM;
    }

    public function calculate_row($row, \tool_research\analysable $analysable, $data, $starttime = false, $endtime = false) {
        global $DB;
        // Filter by context to use the db table index.
        $context = $analysable->get_context();
        $select = "userid = :userid AND contextlevel = :contextlevel AND contextinstanceid = :contextinstanceid AND " .
            "timecreated < :start";
        $params = array('userid' => $row, 'contextlevel' => $context->contextlevel,
            'contextinstanceid' => $context->instanceid, 'start' => $analysable->get_start());
        return $DB->record_exists_select('logstore_standard_log', $select, $params) ? self::get_max_value() : self::get_min_value();
    }
}
