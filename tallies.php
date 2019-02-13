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
$cid = optional_param('cid', 0, PARAM_INT);
$catid = optional_param('catid', 0, PARAM_INT);

$urlparams = [ 'instance' => $instance, 'cid' => $cid, 'catid' => $catid ];

$baseurl = new moodle_url('/local/popular/tallies.php', $urlparams);
$baseurl_noparams = new moodle_url('/local/popular/tallies.php');

// Make sure query parameters are valid
local_popular_check_query_params($urlparams, $baseurl_noparams);

// Get display strings for instance based on query parameter
$strcat = $strcourse = $strmod = '';
$strcat_plural = $strcourse_plural = $strmod_plural = '';

$strcat = get_string('category');
$strcat_plural = get_string('categories');

$strcourse = get_string('course');
$strcourse_plural = get_string('courses');

$strmod = get_string('activitymodule');
$strmod_plural = get_string('activitymodules');

// Get this instance's string and target based on instance query param
$strinstance_plural = '';
if ($instance === 'category') {
    $strinstance_plural = get_string('categories');
    $target = 'course_category';
}
else if ($instance === 'course') {
    $strinstance_plural = get_string('courses');
    $target = 'course';
}
else if ($instance === 'module') {
    $strinstance_plural = get_string('activitymodules');
    $target = 'course_module';
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url($baseurl);
$PAGE->set_pagetype('admin-popular');
$PAGE->set_pagelayout('admin');
$PAGE->set_heading(format_string($SITE->fullname));
$PAGE->set_title(format_string($SITE->fullname) . ': ' . $pluginname);

// Add plugin URL to navbar breadcrumbs
$pluginurl = new moodle_url($CFG->wwwroot . '/admin/category.php', [ 'category' => 'popular' ]);
$PAGE->navbar->add($pluginname, $pluginurl);

// Set navbar breadcrumbs based on query parameters
if ($instance === 'category') {
    $PAGE->navbar->add($strcat_plural);
}
else if ($instance === 'course') {
    if ($catid) {
        $PAGE->navbar->add($strcat_plural, new moodle_url($baseurl_noparams, [ 'instance' => 'category' ]));
        $PAGE->navbar->add($strcourse_plural . ' (' . $strcat . ' ID: ' . $catid . ')');
    }
    else {
        $PAGE->navbar->add($strcourse_plural);
    }
}
else if ($instance === 'module') {
    if ($catid) {
        $PAGE->navbar->add($strcat_plural, new moodle_url($baseurl_noparams, [ 'instance' => 'category' ]));
        $PAGE->navbar->add($strcourse_plural . ' (' . $strcat . ' ID: ' . $catid . ')', new moodle_url($baseurl_noparams, [ 'instance' => 'course', 'catid' => $catid ]));
    }
    else if ($cid) {
        $PAGE->navbar->add($strcourse_plural, new moodle_url($baseurl_noparams, [ 'instance' => 'course' ]));
    }
    
    if ($cid) {
        $PAGE->navbar->add($strmod_plural . ' (' . $strcourse . ' ID: ' . $cid . ')');
    }
    else {
        $PAGE->navbar->add($strmod_plural);
    }
}

echo $OUTPUT->header();

// Set the heading based on query parameters
$heading = get_string('tallies_for_instance', 'local_popular', $strinstance_plural);
if ($instance === 'course') {
    if ($catid) {
        $heading .= ' (' . $strcat . ' ID: ' . $catid . ')';
    }
}
else if ($instance === 'module') {
    if ($cid) {
        $heading .= ' (' . $strcourse . ' ID: ' . $cid . ')';
    }
}

echo $OUTPUT->heading($heading);

// Get time lengths from config. They will be columns of the table.
$timelengthsval = get_config('local_popular', 'timelengths');
$timelengths = array_map('trim', explode(',', $timelengthsval));
sort($timelengths);
array_unshift($timelengths, get_string('name')); // Prefix with a name column

// Prefix column internal names with "col_", because a column internal name of "0" results in "First name / Surname" for some reason. Also make them lowercase.
$columns = preg_filter('/^/', 'col_', array_map('strtolower', $timelengths));
$columns[] = 'col_actions'; // Add column for action buttons

// Substitute in header names for time lengths, if defined in locallib.php
$headers = $timelengths;
array_walk($headers, function (&$value, $key, $timelength_names) {
    if (array_key_exists($value, $timelength_names)) {
        $value = $timelength_names[$value];
    }
}, local_popular_get_timelength_names());
$headers[] = get_string('actions'); // Add column for action buttons

// Setup the table
$table = new flexible_table('local-popular-tallies-display');
$table->define_columns($columns);
$table->define_headers($headers);
$table->define_baseurl($baseurl);
$table->sortable(true);
$table->no_sorting('col_actions'); // Actions can't be sorted
$table->maxsortkeys = 1; // Only allow sorting by one column at a time

$table->set_attribute('cellspacing', '0');
$table->set_attribute('id', 'local-popular-tallies-display');
$table->set_attribute('class', 'generaltable generalbox');
$table->column_class('col_name', 'name');
foreach ($timelengths as $timelength) {
    $table->column_class('col_' . $timelength, 'timelength');
}
$table->column_class('col_actions', 'actions');

$table->setup();

// Rows will be stored in a 2D array first so we can sort before outputting
$table_array = [];

// Get a recordset of this target's tallies
$sql = "SELECT * FROM {local_popular_tallies} WHERE target = ?";
$sqlparams = [ $target ];

// Filter list by category/course as necessary
if ($instance === 'course') {
    if ($catid) {
        $sql = "SELECT * FROM {local_popular_tallies} lpt JOIN {course} c ON lpt.contextinstanceid = c.id JOIN {course_categories} cc ON c.category = cc.id WHERE lpt.target = ? AND cc.id = ?";
        $sqlparams[] = $catid;
    }
}
else if ($instance === 'module') {
    if ($cid) {
        $sql = "SELECT * FROM {local_popular_tallies} lpt JOIN {course_modules} cm ON lpt.contextinstanceid = cm.id JOIN {course} c ON cm.course = c.id WHERE lpt.target = ? AND c.id = ?";
        $sqlparams[] = $cid;
    }
}

$records = $DB->get_recordset_sql($sql, $sqlparams);

if ($records->valid()) {
    foreach ($records as $record) {
        $row = [];
        $name = '';

        // Get the display name based on the target
        if ($instance === 'category') {
            $cat = $DB->get_record('course_categories', [ 'id' => $record->contextinstanceid ]);

            if ($cat) {
                $name = format_string($cat->name);
            }
        }
        else if ($instance === 'course') {
            $course = $DB->get_record('course', [ 'id' => $record->contextinstanceid ]);
            if ($course) {
                $name = format_string($course->fullname);
            }
        }
        else if ($instance === 'module') {
            $sql = "SELECT m.name AS modinstance, cm.instance AS instanceid, cm.visible AS visible FROM {course_modules} cm JOIN {modules} m ON cm.module = m.id WHERE cm.id = ?";
            $cm = $DB->get_record_sql($sql, [ $record->contextinstanceid ]);

            if ($cm) {
                $mod = $DB->get_record($cm->modinstance, [ 'id' => $cm->instanceid ], 'name');
                if ($mod) {
                    $name = format_string($mod->name);
                }
            }
        }

        // Decode tally data
        $tallydata = json_decode($record->tallies, true);

        // Instance should be valid if name was retrieved
        if ($name !== '') {
            // Link the name to its target
            if ($instance === 'category') {
                $targetlink = new moodle_url($CFG->wwwroot . '/course/index.php', [ 'categoryid' => $record->contextinstanceid ]);
            }
            else if ($instance === 'course') {
                $targetlink = new moodle_url($CFG->wwwroot . '/course/view.php', [ 'id' => $record->contextinstanceid ]);
            }
            else if ($instance === 'module') {
                $targetlink = new moodle_url($CFG->wwwroot . '/mod/' . $cm->modinstance . '/view.php', [ 'id' => $record->contextinstanceid ]);
            }

            // When sorting by name, we want to use the actual name (no HTML). This cell won't be included in the actual table.
            $row['sortkey_name'] = trim($name);

            // Add link to target
            if (isset($targetlink)) {
                $name = html_writer::link($targetlink, $name);
            }

            // Add ID to name
            $name .= ' <small>(ID: ' . $record->contextinstanceid . ')</small>';

            // Set the row's name
            $row['col_name'] = $name;

            // Set the row's other cells
            foreach ($tallydata as $timelength => $tally) {
                $row['col_' . $timelength] = $tally;
            }

            // Action icons
            $lastvisitsurl = new moodle_url('/local/popular/lastvisits.php', [ 'instance' => $instance, 'contextinstanceid' => $record->contextinstanceid, 'cid' => $cid, 'catid' => $catid ]);
            $lastvisitsaction = $OUTPUT->action_icon($lastvisitsurl, new pix_icon('t/groupv', get_string('last_visits', 'local_popular')));

            $filteraction = '';
            if ($instance === 'category') {
                $filterurl = new moodle_url($baseurl_noparams, [ 'instance' => 'course', 'catid' => $cat->id ]);
                $filteraction = $OUTPUT->action_icon($filterurl, new pix_icon('t/grades', $strcourse_plural));
            }
            else if ($instance === 'course') {
                $filterurl = new moodle_url($baseurl_noparams, [ 'instance' => 'module', 'cid' => $course->id, 'catid' => $catid ]);
                $filteraction = $OUTPUT->action_icon($filterurl, new pix_icon('t/grades', $strmod_plural));
            }

            $row['col_actions'] = $lastvisitsaction . ' ' . $filteraction;

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
        else {
            array_multisort(array_column($table_array, $col), $sortdir, SORT_NUMERIC, $table_array);
        }
    }

    // Transfer data to table and output it
    foreach ($table_array as $row) {
        $row_data = [];

        foreach ($row as $col => $cell) {
            // Skip the name sort key
            if ($col === 'sortkey_name') {
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
