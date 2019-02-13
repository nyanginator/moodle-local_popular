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

$string['pluginname'] = 'Popular';
$string['manage_popular'] = 'Manage popular';

// Cron
$string['cron_last_update'] = 'Last update was';
$string['cron_name'] = 'Update tally counts of popular categories/courses/activities/etc.';
$string['cron_lastruntime_is_zero'] = 'Doing a full refresh since lastruntime = 0';
$string['cron_cleaning_deleted'] = 'Cleaning out deleted targets from database and cache';
$string['cron_cleaning_lastvisits'] = 'Cleaning out last visit times of nonexistent users';
$string['cron_removing_target'] = 'Removing target';
$string['cron_new_tallies'] = 'New tallies are as follows';
$string['cron_nothing_to_delete'] = 'No records to delete!';
$string['cron_no_new_tallies'] = 'No new tallies!';
$string['cron_task_not_found'] = 'Task not found in database table';

// Privacy API
$string['privacy:metadata:local_popular_lastvisits'] = 'Timestamp of the user\'s last unique visit to a given category, course, or activity module.';
$string['privacy:metadata:local_popular_lastvisits:userid'] = 'The ID of the user.';
$string['privacy:metadata:local_popular_lastvisits:target'] = 'The target type of this instance: course_category, course, or course_module.';
$string['privacy:metadata:local_popular_lastvisits:contextinstanceid'] = 'The ID of the instance visited.';
$string['privacy:metadata:local_popular_lastvisits:lastvisittime'] = 'The timestamp of the last unique visit to this instance.';

// Settings
$string['countguestvisits'] = 'Count Guest Visits';
$string['countguestvisits_desc'] = 'When enabled, visits by people with no account (User ID: 0) or logged in as Guest (User ID: 1) will count in tallies. They will all be tallied under User ID: 1.';

$string['mintimebtvisits'] = 'Minimum Time Between Visits';
$string['mintimebtvisits_desc'] = 'Minimum amount of time (in seconds) that must pass before counting another visit. For example, if set to 3600, when a user visits any number of pages in a course within 1 hour, it counts as 1 visit to that course. If an hour passes after the last visit and the user visits the course again, only then is the tally incremented. If set to 0, then <em>every</em> page visit will increment the tally, which could result in slower cron runs.';

$string['timelengths'] = 'Lengths of Time';
$string['timelengths_desc'] = 'Comma-separated list of time lengths (in seconds) to keep track of. For example, 86400 would keep a tally on popular visits for each day. 604800 for each week. 2592000 for each month (30 days). 31536000 for each year (365 days). 0 for all time. Including 0 means the initialization of the database table might be slower, since it traverses all log records.';

$string['blacklist'] = 'Blacklist';
$string['blacklist_desc'] = 'Comma-separated list of IPs to blacklist. Visits from any IP in this list will not be tallied.';

// Browsing tallies and last visits
$string['invalid_param'] = 'Invalid/missing URL query parameter: {$a}';
$string['invalid_instance'] = 'Invalid: {$a}.';
$string['no_instances_found'] = '{$a} not found!';
$string['tallies_for_instance'] = 'Tallies for: {$a}';
$string['last_visits'] = 'Last Visits';
