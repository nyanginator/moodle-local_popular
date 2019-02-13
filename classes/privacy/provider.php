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
 * @package         local_popular
 * @copyright       Nicholas Yang
 * @license         http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_popular\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\helper;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

class provider implements
    // This plugin stores personal data.
    \core_privacy\local\metadata\provider,

    // This plugin is a core_user_data_provider.
    \core_privacy\local\request\plugin\provider,

    // This plugin is capable of determining which users have data within it.
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Return the fields which contain personal data.
     *
     * @param collection $items a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $items) : collection {
        $items->add_database_table(
            'local_popular_lastvisits',
            [
                'userid' => 'privacy:metadata:local_popular_lastvisits:userid',
                'target' => 'privacy:metadata:local_popular_lastvisits:target',
                'contextinstanceid' => 'privacy:metadata:local_popular_lastvisits:contextinstanceid',
                'lastvisittime' => 'privacy:metadata:local_popular_lastvisits:lastvisittime'
            ],
            'privacy:metadata:local_popular_lastvisits'
        );
        
        return $items;
    }
    
    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid the userid.
     * @return contextlist the list of contexts containing user info for the user.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        $contextlist = new \core_privacy\local\request\contextlist();

        $sqlvars = [
            [
                'table' => 'course_categories',
                'target' => 'course_category',
                'contextlevel' => CONTEXT_COURSECAT
            ],
            [
                'table' => 'course',
                'target' => 'course',
                'contextlevel' => CONTEXT_COURSE
            ],
            [
                'table' => 'course_modules',
                'target' => 'course_module',
                'contextlevel' => CONTEXT_MODULE
            ]
        ];

        foreach ($sqlvars as $vars) {
            $table = $vars['table'];
            $target = $vars['target'];
            $contextlevel = $vars['contextlevel'];

            $sql = "SELECT ctx.id FROM {context} ctx INNER JOIN {{$table}} tbl ON tbl.id = ctx.instanceid AND ctx.contextlevel = :contextlevel INNER JOIN {local_popular_lastvisits} lplv ON lplv.contextinstanceid = tbl.id WHERE lplv.target=:target AND lplv.userid = :userid";
            $contextlist->add_from_sql($sql, [ 'table' => $table, 'contextlevel' => $contextlevel, 'target' => $target, 'userid' => $userid ]);
        }

        return $contextlist;
    }

    
    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        // Categories
        if ($context instanceof \context_coursecat) {
            $table = 'course_categories';
            $target = 'course_category';
        }
        // Courses
        else if ($context instanceof \context_course) {
            $table = 'course';
            $target = 'course';
        }
        // Activity modules
        else if ($context instanceof \context_module) {
            $table = 'course_modules';
            $target = 'course_module';
        }
        else {
            return;
        }

        $sql = "SELECT lplv.userid FROM {{$table}} tbl JOIN {local_popular_lastvisits} lplv ON lplv.contextinstanceid = tbl.id WHERE lplv.target = :target AND tbl.id = :ciid";
        $userlist->add_from_sql('userid', $sql, [ 'table' => $table, 'target' => $target, 'ciid' => $context->instanceid ]);
    }
    
    /**
     * Export personal data for the given approved_contextlist. User and context information is contained within the contextlist.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sqlvars = [
            [
                'table' => 'course_categories',
                'target' => 'course_category',
                'contextlevel' => CONTEXT_COURSECAT
            ],
            [
                'table' => 'course',
                'target' => 'course',
                'contextlevel' => CONTEXT_COURSE
            ],
            [
                'table' => 'course_modules',
                'target' => 'course_module',
                'contextlevel' => CONTEXT_MODULE
            ]
        ];

        foreach ($sqlvars as $vars) {
            $table = $vars['table'];
            $target = $vars['target'];
            $contextlevel = $vars['contextlevel'];

            $sql = "SELECT lplv.contextinstanceid AS ciid, lplv.target, lplv.lastvisittime FROM {context} ctx INNER JOIN {{$table}} tbl ON tbl.id = ctx.instanceid AND ctx.contextlevel = :contextlevel INNER JOIN {local_popular_lastvisits} lplv ON lplv.contextinstanceid = tbl.id WHERE ctx.id {$contextsql} AND lplv.target = :target AND lplv.userid = :userid ORDER BY tbl.id";
            $params = [ 'contextlevel' => $contextlevel, 'target' => $target, 'userid' => $user->id ] + $contextparams;

            $lastvisits = $DB->get_recordset_sql($sql, $params);
            foreach ($lastvisits as $lastvisit) {
                $lastvisittime = $lastvisit->lastvisittime;
                $target = $lastvisit->target;
                $ciid = $lastvisit->ciid;

                $lastvisitdata = [
                    'lastvisittime' => \core_privacy\local\request\transform::datetime($lastvisittime)
                ];

                if ($target === 'course_category') {
                    $context = \context_coursecat::instance($ciid);
                }
                else if ($target === 'course') {
                    $context = \context_course::instance($ciid);
                }
                else if ($target === 'course_module') {
                    $context = \context_module::instance($ciid);
                }
                else {
                    continue;
                }

                // Fetch the generic module data
                $contextdata = helper::get_context_data($context, $user);

                // Merge with this plugin's data and write it
                $contextdata = (object)array_merge((array)$contextdata, $lastvisitdata);
                writer::with_context($context)->export_data([ get_string('pluginname', 'local_popular') ], $contextdata);

                // Write generic module intro files
                helper::export_context_files($context, $user);
            }
            $lastvisits->close();
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context the context to delete in.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context instanceof \context_coursecat) {
            $target = 'course_category';
        }
        else if ($context instanceof \context_course) {
            $target = 'course';
        }
        else if ($context instanceof \context_module) {
            $target = 'course_module';
        }
        else {
            return;
        }

        $DB->delete_records('local_popular_lastvisits', [ 'target' => $target ]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for deletion.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {

            if ($context instanceof \context_coursecat) {
                $target = 'course_category';
            }
            else if ($context instanceof \context_course) {
                $target = 'course';
            }
            else if ($context instanceof \context_module) {
                $target = 'course_module';
            }
            else {
                continue;
            }

            $DB->delete_records('local_popular_lastvisits', [ 'userid' => $userid, 'target' => $target, 'contextinstanceid' => $context->instanceid ]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if ($context instanceof \context_coursecat) {
            $target = 'course_category';
        }
        else if ($context instanceof \context_course) {
            $target = 'course';
        }
        else if ($context instanceof \context_module) {
            $target = 'course_module';
        }
        else {
            return;
        }

        $userids = $userlist->get_userids();
        list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $select = "target = :target AND contextinstanceid = :contextinstanceid AND userid $usersql";
        $params = [ 'target' => $target, 'contextinstanceid' => $context->instanceid ] + $userparams;
        $DB->delete_records_select('local_popular_lastvisits', $select, $params);
    }
}
