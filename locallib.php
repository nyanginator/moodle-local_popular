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

defined('MOODLE_INTERNAL') || die();

/**
 * Helper functions for local_popular
 *
 * @package    local_popular
 * @copyright  Nicholas Yang
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Goes through {logstore_standard_log} to tally targets specified in the
 * local_popular_get_target_names() function where action is equal to "viewed".
 * Stores data in {local_popular} table and Moodle cache.
 *
 * @param $logsearchlimit UTC date in seconds of oldest record to tally. The
 *        default value of -1 forces a full refresh (all records tallied).
 *
 * @param $alltallies Stores the actual tally counts, indexed by target, ciid,
 *        timelength: $alltallies['course'][2][86400] = 45 (45 visits today
 *        for course ID 2)
 *
 * @param $alllastvisits Keeps track of user's last visit time for this target
 *        so we can compare against minimum time between visits. We want to
 *        ensure enough time has passed before adding to a tally.  Indexed by
 *        target, ciid, userid: $alllastvisits['course'][2][70] = 1505706340
 */
function local_popular_refresh_tallies($logsearchlimit = -1, $alltallies = [], $alllastvisits = [], $deletedtargets = []) {
    global $DB; 

    // Prepare caches
    $talliescache = cache::make('local_popular', 'tallies');
    $lastvisitscache = cache::make('local_popular', 'lastvisits');

    // Prepare some need-to-know variables
    $timenow = time();
    $timelengthsval = get_config('local_popular', 'timelengths');
    $timelengths = array_map('trim', explode(',', $timelengthsval));
    sort($timelengths);

    $blacklistval = get_config('local_popular', 'blacklist');
    $blacklist = array_map('trim', explode(',', $blacklistval));

    // Retrieve only records that are within range of longest time length;
    // 0 means to retrieve everything regardless.
    if ($logsearchlimit == -1) {
        $logsearchlimit = in_array(0, $timelengths) ? 0 : $timenow - max($timelengths);

        // Purge/delete since we're scanning all records (full refresh)
        $talliescache->purge();
        $lastvisitscache->purge();
        $DB->delete_records('local_popular_tallies');
        $DB->delete_records('local_popular_lastvisits');
    }

    // Update database and cache for faster access later
    $targets = local_popular_get_target_names();

    // SQL call to retrieve all relevant rows
    $target_sql = "target = '" . $targets[0] . "'";
    for ($i = 1; $i < count($targets); ++$i) {
        $target_sql .= " OR target = '" . $targets[$i] . "'";
    }
    $sql = "SELECT target, contextinstanceid, userid, courseid, timecreated, ip FROM {logstore_standard_log} WHERE (" . $target_sql . ") AND action = 'viewed' AND timecreated > ? ORDER BY timecreated ASC";
    $rs = $DB->get_recordset_sql($sql, [ $logsearchlimit ]);

    // We only want to save information for active users, so get a list of IDs
    $activeusers = $DB->get_fieldset_select('user', 'id', 'deleted = 0');

    // Grab the config setting for minimum time between visits
    $mintimebtvisits = get_config('local_popular', 'mintimebtvisits');

    // Keep track of new tallies, just to return some feedback
    $newtallies = [];

    // Iterate through the records we pulled and tally up what's relevant
    foreach ($rs as $record) {
        // Targets (i.e. 'course', 'course_module') are defined in the
        // function local_popular_get_target_names(). The target's associated ID is
        // $contextinstanceid.
        $target = $record->target;
        $contextinstanceid = $record->contextinstanceid;
        $userid = $record->userid;
        $courseid = $record->courseid;
        $timecreated = $record->timecreated;
        $ip = $record->ip;

        // Check ID. This also ignores course_category ID 0 (/course/index.php).
        if ($contextinstanceid == 0) {
            continue;
        }

        // Initialize counter for new tallies
        if (!isset($newtallies[$target][$contextinstanceid])) {
            $newtallies[$target][$contextinstanceid] = 0;
        }

        // If a target was just deleted, don't bother tallying for this refresh
        if (in_array($target . ':' . $contextinstanceid, $deletedtargets)) {
            continue;
        }

        // If no record exists for this ID, initialize counters with 0's
        if (!array_key_exists($target, $alltallies)) {
            $alltallies[$target] = [];
        }
        if (!array_key_exists($contextinstanceid, $alltallies[$target])) {
            // Initialize counter for each time length to zero
            $alltallies[$target][$contextinstanceid] = array_combine($timelengths, array_fill(0, count($timelengths), 0));
        }

        // Check for last visit time before updating with this one
        $countguestvisits = get_config('local_popular', 'countguestvisits');
        $lastvisittime = 0;
        if (in_array($userid, $activeusers) || ($countguestvisits && $userid <= 1)) {
            // Normally, users who aren't logged in have ID: 0 and guest
            // logins have user ID: 1. For our purposes, they are the same.
            // So all users who aren't logged in (whether guest or not) will be
            // considered as a single user with ID: 1. This is only used to
            // determine when a visit is unique.
            if ($countguestvisits && $userid <= 1) {
                $userid = 1;
            }
            
            // Make sure array keys exist before trying to grab value
            if (!array_key_exists($target, $alllastvisits)) {
                $alllastvisits[$target] = [];
            }
            if (array_key_exists($contextinstanceid, $alllastvisits[$target])) {
                if (array_key_exists($userid, $alllastvisits[$target][$contextinstanceid])) {
                    $lastvisittime = $alllastvisits[$target][$contextinstanceid][$userid];
                }
            }

            // Update this target's last visit time for this user
            $alllastvisits[$target][$contextinstanceid][$userid] = $timecreated;
        }

        // Only tally if enough time has passed since last visit and IP isn't
        // blacklisted.
        if (!in_array($ip, $blacklist)) {
            // Using >= so it handles when $mintimebtvisits is 0.
            if ($timecreated - $lastvisittime >= $mintimebtvisits) {
                foreach ($timelengths as $timelength) {
                    $tltime = $timenow - (int)$timelength;
                    if ($timecreated > $tltime || $timelength == 0) {
                        $alltallies[$target][$contextinstanceid][$timelength]++;
                    }
                }

                // Keep track of this run's new tallies so we know what was added
                $newtallies[$target][$contextinstanceid]++;
            }
        }
    }

    $rs->close(); // IMPORTANT

    // Update database records
    foreach ($targets as $target) {
        // Note that the frontpage (Course ID: 1) is included
        foreach ($alltallies[$target] as $ciid => $tally) {
            // Tallies
            $row = new stdClass();
            $row->target = $target;
            $row->contextinstanceid = $ciid;
            $row->tallies = json_encode($tally);

            if ($existing = $DB->get_record('local_popular_tallies', [ 'target' => $target, 'contextinstanceid' => $ciid ])) {
                $row->id = $existing->id;
                $DB->update_record('local_popular_tallies', $row);
            }
            else {
                $DB->insert_record('local_popular_tallies', $row);
            }

            // Last visit times
            foreach ($alllastvisits[$target][$ciid] as $uid => $lvtime) {
                $lvrecord = new stdClass();
                $lvrecord->userid = $uid;
                $lvrecord->target = $target;
                $lvrecord->contextinstanceid = $ciid;
                $lvrecord->lastvisittime = $lvtime;

                if ($existing = $DB->get_record('local_popular_lastvisits', [ 'userid' => $uid, 'target' => $target, 'contextinstanceid' => $ciid ])) {
                    $lvrecord->id = $existing->id;
                    $DB->update_record('local_popular_lastvisits', $lvrecord);
                }
                else {
                    $DB->insert_record('local_popular_lastvisits', $lvrecord);
                }
            }
        }

        // Cache ALL tallies for this target (easier to search and sort later)
        $talliescache->set($target, $alltallies[$target]);
        $lastvisitscache->set($target, $alllastvisits[$target]);
    }

    // Update cron task's lastruntime, so next cron run knows where we left off
    if ($row = $DB->get_record('task_scheduled', [ 'classname' => '\\local_popular\\task\\popular_cron_task' ])) {
        $row->lastruntime = $timenow;
        $DB->update_record('task_scheduled', $row);
    }

    return $newtallies;
}

/**
 * Returns data of a target, depending on specified cache name. If cached data is not found, it is retrieved from the database.
 *
 * @param $target Target as specified in {logstore_standard_log} (e.g. course_category, course, course_category)
 * @param $cachename Cache to check for data. Either "tallies" or "lastvisits".
 */
function local_popular_get_target($target, $cachename) {
    // Check cache (key is target, e.g. 'course')
    $cache = cache::make('local_popular', $cachename);
    $cached = $cache->get($target);

    if ($cached) {
        $targetdata = $cached;
    }
    else {
        global $DB;

        $rs = $DB->get_recordset('local_popular_' . $cachename, [ 'target' => $target ]);
        $targetdata = [];
        foreach ($rs as $record) {
            if ($cachename === 'tallies') {
                $keys = json_decode($record->$cachename);
                foreach ($keys as $key => $value) {
                    $targetdata[$record->contextinstanceid][$key] = $value;
                }
            }
            else if ($cachename === 'lastvisits') {
                $uid = $record->userid;
                $target = $record->target;
                $ciid = $record->contextinstanceid;
                $lastvisittime = $record->lastvisittime;
                $targetdata[$ciid][$uid] = $lastvisittime;
            }
        }
        $rs->close(); // IMPORTANT

        // Update cache
        $cache->set($target, $targetdata);
    }

    return $targetdata;
}

/**
 * Check validity of URL query parameters and redirect in case of errors.
 *
 * @param $params Associative array of query params to check
 * @param $redirecturl URL to redirect to in case of error
 */
function local_popular_check_query_params($params, $redirecturl) {
    global $CFG, $DB;

    $instance = isset($params['instance']) ? $params['instance'] : '';
    $contextinstanceid = isset($params['contextinstanceid']) ? $params['contextinstanceid'] : 0;
    $cid = isset($params['cid']) ? $params['cid'] : 0;
    $catid = isset($params['catid']) ? $params['catid'] : 0;
    
    // First check that the instance type is valid
    if (!in_array($instance, [ 'category', 'course', 'module' ])) {
        $pluginurl = new moodle_url($CFG->wwwroot . '/admin/category.php', [ 'category' => 'popular' ]);
        redirect($pluginurl, get_string('invalid_param', 'local_popular', 'instance'), null, \core\output\notification::NOTIFY_ERROR);
    }
    // Now check that the instance actually exists in the database
    else {
        $redirect = false;
        $error;

        // 'contextinstanceid' will only be set when on lastvisits.php page
        if (isset($params['contextinstanceid'])) {
            if ($contextinstanceid > 0) {
                if ($instance === 'category') {
                    $record = $DB->get_record('course_categories', [ 'id' => $contextinstanceid ]);
                    $error = get_string('category') . ' ID';
                }
                else if ($instance === 'course') {
                    $record = $DB->get_record('course', [ 'id' => $contextinstanceid ]);
                    $error = get_string('course') . ' ID';
                }
                else if ($instance === 'module') {
                    $record = $DB->get_record('course_modules', [ 'id' => $contextinstanceid ]);
                    $error = get_string('activitymodule') . ' ID';
                }

                if (!$record) {
                    $redirect = true;
                    unset($params['contextinstanceid']); // Remove invalid parameter
                }
            }
        }

        // $catid/$cid will have values when the view is filtered down (e.g. courses of a specific category)
        if (isset($params['catid'])) {
            if ($catid > 0) {
                $record = $DB->get_record('course_categories', [ 'id' => $catid ]);

                if (!$record) {
                    $redirect = true;
                    $error = get_string('category');
                    unset($params['catid']); // Remove invalid parameter
                }
            }
        }

        if (isset($params['cid'])) {
            if ($cid > 0) {
                $record = $DB->get_record('course', [ 'id' => $cid ]);

                if (!$record) {
                    $redirect = true;
                    $error = get_string('course');
                    unset($params['cid']); // Remove invalid parameter
                }
            }
        }

        if ($redirect) {
            // Clean up URL
            unset($params['contextinstanceid']);

            redirect(new moodle_url($redirecturl, $params), get_string('invalid_instance', 'local_popular', $error), null, \core\output\notification::NOTIFY_ERROR);
        }
    }
}

/**
 * Returns an array of valid target names. They must match the target names of
 * {logstore_standard_log}. If you add targets here, you will also need to
 * update the function local_popular_get_top_items() in lib.php and the cron
 * task's SQL to include them.
 *
 * @return Array of targets that are being tallied.
 */
function local_popular_get_target_names() {
    return [ 'course_category', 'course', 'course_module' ];
}

/**
 * Returns an associative array of preferred display names for time lengths.
 * For use when viewing tallies table in the backend.
 *
 * @return Associative array of time lengths mapped to a human-readable name.
 */
function local_popular_get_timelength_names() {
    return [
        '0' => 'All Time',
        '86400' => 'Past Day',
        '604800' => 'Past Week',
        '2592000' => 'Past Month',
        '31536000' => 'Past Year',
    ];
}
