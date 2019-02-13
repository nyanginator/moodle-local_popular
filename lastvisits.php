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
 * @package   local_popular
 * @copyright Nicholas Yang
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/popular/locallib.php');

// Allow admins only
require_login();
require_capability('moodle/site:config', context_system::instance());

$pluginname = get_string('pluginname', 'local_popular');

$instance = optional_param('instance', '', PARAM_TEXT);
$contextinstanceid = optional_param('contextinstanceid', 0, PARAM_INT);
$cid = optional_param('cid', 0, PARAM_INT);
$catid = optional_param('catid', 0, PARAM_INT);

$urlparams = [ 'instance' => $instance, 'contextinstanceid' => $contextinstanceid, 'cid' => $cid, 'catid' => $catid ];

$pluginurl = new moodle_url($CFG->wwwroot . '/admin/category.php', [ 'category' => 'popular' ]);
$baseurl = new moodle_url('/local/popular/lastvisits.php', $urlparams);
$baseurl_noparams = new moodle_url('/local/popular/lastvisits.php');
$talliesurl = new moodle_url('/local/popular/tallies.php', $urlparams);
$talliesurl_noparams = new moodle_url('/local/popular/tallies.php');

// Make sure query parameters are valid
local_popular_check_query_params($urlparams, $talliesurl_noparams);

// Get display strings for instance based on query parameter
$strcat = $strcourse = $strmod = '';
$strcat_plural = $strcourse_plural = $strmod_plural = '';

$strcat = get_string('category');
$strcat_plural = get_string('categories');

$strcourse = get_string('course');
$strcourse_plural = get_string('courses');

$strmod = get_string('activitymodule');
$strmod_plural = get_string('activitymodules');

$strinstance = $strinstance_plural = '';
if ($instance === 'category') {
    $strinstance = $strcat;
    $strinstance_plural = $strcat_plural;
    $target = 'course_category';
}
else if ($instance === 'course') {
    $strinstance = $strcourse;
    $strinstance_plural = $strcourse_plural;
    $target = 'course';
}
else if ($instance === 'module') {
    $strinstance = $strmod;
    $strinstance_plural = $strmod_plural;
    $target = 'course_module';
}

// Make sure instance ID is valid
if ($contextinstanceid == 0) {
    redirect($talliesurl, get_string('invalid_param', 'local_popular', 'contextinstanceid'), null, \core\output\notification::NOTIFY_ERROR);
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url($baseurl);
$PAGE->set_pagetype('admin-popular');
$PAGE->set_pagelayout('admin');
$PAGE->set_heading(format_string($SITE->fullname));
$PAGE->set_title(format_string($SITE->fullname) . ': ' . $pluginname);

// Set the heading based on query parameters
$strtitle = get_string('last_visits', 'local_popular');
$strtitle .= ' (' . $strinstance . ' ID: ' . $contextinstanceid . ')';

// Get readable name
if ($instance === 'category') {
    $record = $DB->get_record('course_categories', [ 'id' => $contextinstanceid ]);
    $instancename = format_string($record->name);
}
else if ($instance === 'course') {
    $record = $DB->get_record('course', [ 'id' => $contextinstanceid ]);
    $instancename = format_string($record->fullname);
}
else if ($instance === 'module') {
    $sql = "SELECT cm.id AS modid, m.name AS modinstance, cm.instance AS instanceid FROM {course_modules} cm JOIN {modules} m ON cm.module = m.id WHERE cm.id = ?";
    $cm = $DB->get_record_sql($sql, [ $contextinstanceid ]);

    $instancename = format_string($DB->get_record($cm->modinstance, [ 'id' => $cm->instanceid ], 'name')->name);
}

// Add plugin URL to navbar breadcrumbs
$PAGE->navbar->add($pluginname, $pluginurl);

// Set navbar breadcrumbs based on query parameters
if ($instance === 'category') {
    $PAGE->navbar->add($strcat_plural, new moodle_url($talliesurl_noparams, ['instance' => 'category' ]));
}
else if ($instance === 'course' || $instance === 'module') {
    if ($catid) {
        $PAGE->navbar->add($strcat_plural, new moodle_url($talliesurl_noparams, [ 'instance' => 'category' ]));
        $PAGE->navbar->add($strcourse_plural . ' (' . $strcat . ' ID: ' . $catid . ')', new moodle_url($talliesurl_noparams, [ 'instance' => 'course', 'catid' => $catid ]));
    }
    else if ($instance === 'course' || ($instance === 'module' && $cid)) {
        $PAGE->navbar->add($strcourse_plural, new moodle_url($talliesurl_noparams, [ 'instance' => 'course' ]));
    }

    if ($instance === 'module') {
        if ($cid) {
            $PAGE->navbar->add($strmod_plural . ' (' . $strcourse . ' ID: ' . $cid . ')', new moodle_url($talliesurl_noparams, [ 'instance' => 'module', 'catid' => $catid, 'cid' => $cid ]));
        }
        else {
            $PAGE->navbar->add($strmod_plural, new moodle_url($talliesurl_noparams, [ 'instance' => 'module', 'catid' => $catid ]));
        }
    }
}

// Add this page to navbar breadcrumb (no link)
$PAGE->navbar->add($strtitle);

echo $OUTPUT->header();
echo $OUTPUT->heading($strtitle);
if (isset($instancename)) {
    echo $OUTPUT->heading($instancename, 4);
}

// Setup the table
$table = new flexible_table('local-popular-lastvisits-display');
$table->define_columns([ 'col_name', 'col_time' ]);
$table->define_headers([ 'Name', 'Time' ]);
$table->define_baseurl($baseurl);
$table->sortable(true);
$table->maxsortkeys = 1; // Only allow sorting by one column at a time

$table->set_attribute('cellspacing', '0');
$table->set_attribute('id', 'local-popular-lastvisits-display');
$table->set_attribute('class', 'generaltable generalbox');
$table->column_class('name', 'name');
$table->column_class('username', 'username');

$table->setup();

// Rows will be stored in a 2D array first so we can sort before outputting
$table_array = [];

// Get a recordset of this target's tallies
$sql = "SELECT * FROM {local_popular_lastvisits} WHERE target = ? AND contextinstanceid = ?";
$sqlparams = [ $target, $contextinstanceid ];

$records = $DB->get_recordset_sql($sql, $sqlparams);

if ($records->valid()) {
    // We need to define keys to sort by, since table data will be formatted with user links and human-readable dates.
    $sortkeys = [ 'sortkey_name', 'sortkey_time' ];

    foreach ($records as $record) {
        $userid = $record->userid;
        $lastvisittime = $record->lastvisittime;

        $row = [];

        $user = core_user::get_user($userid);
        if ($user) {
            // Name column
            $firstlastname = $user->firstname . ' ' . $user->lastname;
            $row['sortkey_name'] = $firstlastname;
            $row['col_name'] = html_writer::link(new moodle_url('/user/profile.php', [ 'id' => $user->id ]), $firstlastname);
            $row['col_name'] .= ' <small>(Username: ' . $user->username . ')</small>';

            // Time column
            $row['col_time'] = date('d M Y (D) H:i:s T', $lastvisittime);
            $row['sortkey_time'] = $lastvisittime;

            $table_array[] = $row;
        }
    }

    // Handle column sorting
    $sort_columns = $table->get_sort_columns();
    if (count($sort_columns)) {
        // There should be only one element since maxsortkeys = 1
        $col = key($sort_columns);
        $sortdir = $sort_columns[$col];

        if ($col === 'col_name') {
            array_multisort(array_column($table_array, 'sortkey_name'), $sortdir, SORT_STRING, $table_array);
        }
        else if ($col === 'col_time') {
            array_multisort(array_column($table_array, 'sortkey_time'), $sortdir, SORT_NUMERIC, $table_array);
        }
    }

    // Transfer data to table and output it
    foreach ($table_array as $row) {
        $row_data = [];

        foreach ($row as $col => $cell) {
            // Skip sort key columns
            if (in_array($col, $sortkeys)) {
                continue;
            }

            $row_data[] = $cell;
        }

        $table->add_data($row_data);
    }

    $table->finish_output();
}
else {
    echo '<p><em>' . get_string('no_instances_found', 'local_popular', $strinstance_plural) . '</em></p>';
}

$records->close(); // IMPORTANT

echo $OUTPUT->footer();
