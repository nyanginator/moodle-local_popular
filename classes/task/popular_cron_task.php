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

namespace local_popular\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/popular/locallib.php');

/**
 * Simple task to run the local_popular cron.
 */
class popular_cron_task extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('cron_name', 'local_popular');
    }

    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {
        $component = $this->get_component(); // local_popular
        
        mtrace('');
        mtrace('// BEGIN ' . $component);
        mtrace('');

        global $DB;
        if ($row = $DB->get_record('task_scheduled', [ 'classname' => '\\local_popular\\task\\popular_cron_task' ])) {
            $lastruntime = $row->lastruntime;
            mtrace('  ' . get_string('cron_last_update', 'local_popular') . ' ' . $lastruntime . ($lastruntime == 0 ? '' : ' (' . date("F jS Y h:i:s A", $lastruntime) . ')') . '.');
            mtrace('');

            if ($lastruntime == 0) {
                mtrace('  --' . get_string('cron_lastruntime_is_zero', 'local_popular'));
                $newtallies = local_popular_refresh_tallies();
                mtrace('    [OK]');
            }
            else {
                // Retrieve existing records of tallies and lastvisits
                $targets = local_popular_get_target_names();
                foreach ($targets as $target) {
                    $alltallies[$target] = local_popular_get_target($target, 'tallies');
                    $alllastvisits[$target] = local_popular_get_target($target, 'lastvisits');
                }

                // Delete obsolete targets from database and exclude in refresh
                mtrace('  ' . get_string('cron_cleaning_deleted', 'local_popular') . ':');

                // LEFT JOIN to see if one field is NULL, while other isn't
                $sql = "SELECT lpt.id, lpt.target, lpt.contextinstanceid FROM {local_popular_tallies} lpt LEFT JOIN {course} c ON lpt.contextinstanceid=c.id AND lpt.target='course' LEFT JOIN {course_modules} cm ON lpt.contextinstanceid=cm.id AND lpt.target='course_module' LEFT JOIN {course_categories} cc ON lpt.contextinstanceid=cc.id AND lpt.target='course_category' WHERE (c.id IS NULL AND lpt.target='course') OR (cm.id IS NULL AND lpt.target='course_module') OR (cc.id IS NULL AND lpt.target='course_category')";
                $rs = $DB->get_recordset_sql($sql);

                $deletedtargets = [];
                if ($rs->valid()) {
                    foreach ($rs as $recordtodelete) {
                        $target = $recordtodelete->target;
                        $ciid = $recordtodelete->contextinstanceid;

                        // Remember, cron may need to run twice because when
                        // you delete, for example, a course_module, it is
                        // first scheduled for deletion. First cron run for
                        // Moodle to delete it. Second cron run to delete from
                        // local_popular's tables and caches.
                        mtrace('    * ' . get_string('cron_removing_target', 'local_popular') . ' \'' . $target . '\' with contextinstanceid: ' . $ciid);
                        $DB->delete_records('local_popular_tallies', [ 'target' => $target, 'contextinstanceid' => $ciid ]);
                        $DB->delete_records('local_popular_lastvisits', [ 'target' => $target, 'contextinstanceid' => $ciid ]);

                        // Exclude deleted targets from local_popular_refresh_tallies()
                        unset($alltallies[$target][$ciid]);
                        unset($alllastvisits[$target][$ciid]);

                        $deletedtargets[] = $target . ':' . $ciid;
                    }
                }
                else {
                    mtrace('    --' . get_string('cron_nothing_to_delete', 'local_popular'));
                }

                $rs->close(); // IMPORTANT

                // Remove last visit records of deleted users
                mtrace('');
                mtrace('  ' . get_string('cron_cleaning_lastvisits', 'local_popular') . ':');
                $activeusers = $DB->get_records('user', [ 'deleted' => 0 ], '', 'id');
                $invalid_lvrecords = $DB->get_records_select('local_popular_lastvisits', 'userid NOT IN (' . implode(',', array_keys($activeusers)) . ')');

                if (count($invalid_lvrecords)) {
                    $deleteuserids = array_unique(array_column($invalid_lvrecords, 'userid'));
                    // Show what user IDs are invalid
                    foreach ($deleteuserids as $userid) {
                        mtrace('    * User ID: ' . $userid);
                    }

                    $DB->delete_records_select('local_popular_lastvisits', 'id IN (' . implode(', ', array_keys($invalid_lvrecords)) . ')');

                    // Exclude deleted last visit records from local_popular_refresh_tallies()
                    foreach ($invalid_lvrecords as $lvrecord) {
                        $userid = $lvrecord->userid;
                        $target = $lvrecord->target;
                        $ciid = $lvrecord->contextinstanceid;

                        unset($alllastvisits[$target][$ciid][$userid]);
                    }
                }
                else {
                    mtrace('    --' . get_string('cron_nothing_to_delete', 'local_popular'));
                }

                // Retrieve only rows since last update. The last run
                // time is updated when cron runs, when the plugin is upgraded,
                // or when settings are changed. Refreshing tallies also
                // updates the caches and database values.
                $newtallies = local_popular_refresh_tallies($lastruntime, $alltallies, $alllastvisits, $deletedtargets);
            }

            mtrace('');
            mtrace('  ' . get_string('cron_new_tallies', 'local_popular') . ':');
            $count = 0;
            foreach ($newtallies as $target => $ciids) {
                foreach ($ciids as $ciid => $tally) {
                    if ($tally > 0) {
                        mtrace('    * ' . $target . ' ' . $ciid . ': (+' . $tally . ')');
                        $count++;
                    }
                }
            }

            if ($count == 0) {
                mtrace('    --' . get_string('cron_no_new_tallies', 'local_popular'));
            }
        }
        else {
            mtrace('  [ERROR] ' . get_string('cron_task_not_found', 'local_popular') . ' {task_scheduled}!');
        }

        mtrace('');
        mtrace('// END ' . $component);
        mtrace('');
    }
}
