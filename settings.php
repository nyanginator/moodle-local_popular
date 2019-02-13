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

defined('MOODLE_INTERNAL') || die;

if (is_siteadmin()) {
    require_once(__DIR__ . '/locallib.php');

    $ADMIN->add('localplugins', new admin_category('popular', new lang_string('pluginname','local_popular')));

    $settings = new admin_settingpage('local_popular_settings', get_string('manage_popular', 'local_popular'));

    $countguests_setting = new admin_setting_configcheckbox('local_popular/countguestvisits', get_string('countguestvisits', 'local_popular'), get_string('countguestvisits_desc', 'local_popular'), 0);
    $countguests_setting->set_updatedcallback('local_popular_refresh_tallies');
    $settings->add($countguests_setting);

    $mintime_setting = new admin_setting_configtext('local_popular/mintimebtvisits', get_string('mintimebtvisits', 'local_popular'), get_string('mintimebtvisits_desc', 'local_popular'), '3600', PARAM_INT);
    $mintime_setting->set_updatedcallback('local_popular_refresh_tallies');
    $settings->add($mintime_setting);

    $timelengths_setting = new admin_setting_configtextarea('local_popular/timelengths', get_string('timelengths', 'local_popular'), get_string('timelengths_desc', 'local_popular'), '86400, 604800, 2592000, 31536000', '/^[0-9]+([\s]*[,][\s]*[0-9]+)*$/');
    $timelengths_setting->set_updatedcallback('local_popular_refresh_tallies');
    $settings->add($timelengths_setting);

    $blacklist_setting = new admin_setting_configtextarea('local_popular/blacklist', get_string('blacklist', 'local_popular'), get_string('blacklist_desc', 'local_popular'), '', '/^([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+([\s]*[,][\s]*[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)*)*$/');
    $blacklist_setting->set_updatedcallback('local_popular_refresh_tallies');
    $settings->add($blacklist_setting);

    $ADMIN->add('popular', new admin_externalpage('local_popular_categories', get_string('tallies_for_instance', 'local_popular', get_string('categories')), $CFG->wwwroot . '/local/popular/tallies.php?instance=category'));
    $ADMIN->add('popular', new admin_externalpage('local_popular_courses', get_string('tallies_for_instance', 'local_popular', get_string('courses')), $CFG->wwwroot . '/local/popular/tallies.php?instance=course'));
    $ADMIN->add('popular', new admin_externalpage('local_popular_modules', get_string('tallies_for_instance', 'local_popular', get_string('activitymodules')), $CFG->wwwroot . '/local/popular/tallies.php?instance=module'));

    $ADMIN->add('popular', $settings);
}
