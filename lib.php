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
 * @package    local_popular
 * @copyright  Nicholas Yang
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/popular/locallib.php');

/**
 * Returns tallies of a specified target (i.e. course, course_module), given a time length (i.e. 86400 for today's tallies).
 */
function local_popular_get_top_tallies($target, $timelength, $limit = 0) {
    $targettallies = local_popular_get_target($target, 'tallies');

    // Sort in descending order by $timelength key; uasort() to preserve keys
    uasort($targettallies, function($a, $b) use($timelength) {
        return $b[$timelength] - $a[$timelength];
    });

    // Don't include site "course"
    $site = get_site();
    if (array_key_exists($site->id, $targettallies)) {
        unset($targettallies[$site->id]);
    }

    if ($limit > 0) {
        return array_slice($targettallies, 0, $limit, true);
    }

    return $targettallies;
}

/**
 * Returns top items for a target (i.e. course, course_module) and time length (i.e. 'course', 86400 for today's top courses.
 */
function local_popular_get_top_items($target, $timelength, $limit = 0) {
    $items = [];

    if ($target === 'course') {
        $items = get_courses();

        // Don't include site "course"
        $site = get_site();
        if (array_key_exists($site->id, $items)) {
            unset($items[$site->id]);
        }
    }
    else if ($target === 'course_module') {
        global $DB;
        $items = $DB->get_records('course_modules');
    }
    else if ($target === 'course_category') {
        global $DB;
        $items = $DB->get_records('course_categories');
    }

    // Only include if item tally > 0 AND item hasn't been deleted
    $tallies = local_popular_get_top_tallies($target, $timelength, $limit);
    foreach ($tallies as $itemid => $itemtallies) {
        if ($itemtallies[$timelength] == 0 || !array_key_exists($itemid, $items)) {
            unset($tallies[$itemid]);
        }
    }

    // Get items of only keys in the tallies array, preserving the order
    return array_replace($tallies, array_intersect_key($items, $tallies));
}
