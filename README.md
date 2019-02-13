# Moodle - Popular Courses/Modules
https://github.com/nyanginator/moodle-local_popular

Tallies and retrieves most popular [Moodle](https://moodle.org) categories, courses, and activity modules.

Table of Contents
=================
* [What This Plugin Does](#what-this-plugin-does)
* [Install](#install)
* [Usage](#usage)
  * [Admin Configuration](#admin-configuration)
  * [Viewing Tallies and Last Visits](#viewing-tallies-and-last-visits)
  * [Retrieving the Top Courses/Modules](#retrieving-the-top-coursesmodules)
* [Cron Task](#cron-task)
* [Notes](#notes)
* [Uninstall](#uninstall)
* [Contact](#contact)

What This Plugin Does
=====================
This is a Moodle local plugin that scans records from the database table `{logstore_standard_log}` to determine what categories, courses, and activity modules are the most frequently accessed -- that is, the most popular. Tallies are saved in the Moodle cache and updated through a cron task.

Install
=======
Create the folder `local/popular` in your Moodle installation and copy the contents of this repository there. Login as the Moodle admin and proceed through the normal installation of this new plugin. If the plugin is not automatically found, you may have to go to Site Administration > Notifications.

Usage
=====

Admin Configuration
-------------------
Settings for this plugin can be found at Site Administration > Plugins > Local Plugins > Popular. Note that any time a setting is updated, a full refresh is done to make sure current tally counts accurately reflect the new change. A full refresh consists of re-tallying all of the `{logstore_standard_log}` database records that are within range of the largest set time length. For example, if 31536000 is the largest time length, a full refresh would re-tally all records that were logged within the past year (31536000 seconds ago).

![Admin Configuration](https://raw.githubusercontent.com/nyanginator/moodle-local_popular/master/screenshots/admin-config.jpg)

* **Count Guest Visits** - Enable if you want to keep track of visits by users without a Moodle account. Visits by users with no account (User ID: 0) and users logged into the guest account (User ID: 1) will all be tallied under User ID: 1.
* **Minimum Time Between Visits** - Number of seconds to wait before considering a subsequent visit from the same visitor as unique. This prevents tallies from incrementing too quickly if for example, the visitor is just refreshing a page or quickly browsing back and forth between different courses.
* **Lengths of Time** - The default values of 86400, 604800, 2592000, 31536000 tell the plugin to keep track of tallies for each day, week, month, and year.
* **Blacklist** - Use a comma-separated list of IPs to blacklist them from being tallied. For example, you might want to add your IP here if you are doing a lot of testing or debugging and don't want to tally those visits.

Viewing Tallies and Last Visits
-------------------------------
In Site Administration > Plugins > Local Plugins > Popular, you will also find links to view the current tally counts for categories, courses, and activity modules in a sortable table. If you use the standard time lengths of 0, 86400, 604800, 2592000, and 31536000, the table's columns will have the names: All Time, Past Day, Past Week, Past Month, and Past Year. These column headers are defined in `local_popular_get_timelength_names()` of `locallib.php`.

* Click the person icon to view the the last visit time of users.
* In the Categories view, click the table icon to view courses of that category.
* In the Courses view, click the table icon to view activity modules of that course.
* Click a column header to sort the table by that column.
* Click a name to go to the category/course/module/user page.

![View Tallies](https://raw.githubusercontent.com/nyanginator/moodle-local_popular/master/screenshots/view-tallies.jpg)

![View Last Visits](https://raw.githubusercontent.com/nyanginator/moodle-local_popular/master/screenshots/view-last-visits.jpg)

Retrieving the Top Courses/Modules
----------------------------------
One of the intended uses of this plugin is to customize the output of popular items through your theme's code. Retrieving the items is easy using the `lib.php` functions. Here is a basic example:

```php
require_once($CFG->dirroot . '/local/popular/lib.php');
    
// Retrieve top courses of the past day, limit to 2 results
$topcourses = local_popular_get_top_items('course', 86400, 2);
    
print_r($topcourses); // Prints a sorted array of course objects, most popular first
```
The third parameter of `local_popular_get_top_items()` is optional, which allows you to limit the number of results. The above code would result in something like this:

```php
Array
(
    [3] => stdClass Object
        (
            [id] => 3
            [category] => 1
            [sortorder] => 10002
            [fullname] => The Very First Test Course
            [shortname] => Very First
            [idnumber] => very-first
            [summary] =>  Duis a sollicitudin sem. Vivamus consectetur, tortor id egestas viverra.
            [summaryformat] => 1
            [format] => topics
            [showgrades] => 1
            [newsitems] => 5
            [startdate] => 1495598400
            [enddate] => 0
            [marker] => 0
            [maxbytes] => 0
            [legacyfiles] => 0
            [showreports] => 0
            [visible] => 1
            [visibleold] => 1
            [groupmode] => 0
            [groupmodeforce] => 0
            [defaultgroupingid] => 0
            [lang] => 
            [calendartype] => 
            [theme] => 
            [timecreated] => 1495570097
            [timemodified] => 1531767946
            [requested] => 0
            [enablecompletion] => 1
            [completionnotify] => 0
            [cacherev] => 1531772400
        )

    [7] => stdClass Object
        (
            [id] => 7
            [category] => 1
            [sortorder] => 10001
            [fullname] => Second Most Popular
            [shortname] => Second
            [idnumber] => second
            [summary] => Suspendisse nec urna ac velit vehicula efficitur. Nulla facilisi.
            [summaryformat] => 1
            [format] => topics
            [showgrades] => 1
            [newsitems] => 5
            [startdate] => 1500004800
            [enddate] => 0
            [marker] => 0
            [maxbytes] => 0
            [legacyfiles] => 0
            [showreports] => 0
            [visible] => 1
            [visibleold] => 1
            [groupmode] => 0
            [groupmodeforce] => 0
            [defaultgroupingid] => 0
            [lang] => 
            [calendartype] => 
            [theme] => 
            [timecreated] => 1500152909
            [timemodified] => 1527190926
            [requested] => 0
            [enablecompletion] => 1
            [completionnotify] => 0
            [cacherev] => 1531772400
        )
)
```
You can also get the actual tally numbers:

```php
require_once($CFG->dirroot . '/local/popular/lib.php');
    
// Retrieve top courses of the past day, limit to 2 results
$toptallies = local_popular_get_top_tallies('course', 86400, 2);
   
print_r($toptallies); // Prints a sorted array of course visit tallies, most popular first
```

Which would give you something like this:

```php
Array
(
    [3] => Array
        (
            [0] => 1942
            [86400] => 156
            [604800] => 449
            [2592000] => 720
            [31536000] => 1942
        )

    [7] => Array
        (
            [0] => 255
            [86400] => 56
            [604800] => 101
            [2592000] => 175
            [31536000] => 255
        )
)
```
You can see that 156 > 56, which is why course 3 is before course 7. To further clarify, you can read these tallies as:
* Course 3 has been visited 1942 total times.
* Course 3 has been visited 156 times in the past day.
* Course 3 has been visited 449 times in the past week.
* Course 3 has been visited 720 times in the past month (30 days).
* Course 3 has been visited 1942 times in the past year (365 days).

Cron Task
=========
Make sure you periodically run the Moodle cron command, or else tallies will never get updated. A full refresh is done when you first install or update the plugin and whenever changes are made in the admin settings. Afterwards, it relies on the cron task to record any new tallies.

The cron task is also responsible for deleting tally records of deleted courses and course modules.

Notes
=====
* The main code resides in `locallib.php`. Most of the work is done in the `local_popular_refresh_tallies()` function, which checks/updates the Moodle cache, keeps track of unique visits, and synchronizes with the database. 

* Only 3 types of targets are defined: `course_category`, `course`, `course_module`. These targets are specified in the function `local_popular_get_target_names()`. If you want to tally other targets, you will need to find the names of the desired targets in the `{logstore_standard_log}` database table and add them here. Note that would also need to update `local_popular_get_top_items()` and the cron task to include the new target(s).

Uninstall
=========
Uninstall by going to Site Administration > Plugins > Plugins Overview and using the Uninstall link for the `local/popular` plugin.

Contact
=======
Nicholas Yang\
http://nyanginator.wixsite.com/home
