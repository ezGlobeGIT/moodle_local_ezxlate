<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package    local_ezxlate
 * @copyright  2025 EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['config:allowed_courses:desc'] = "Enter the list of courses allowed to be managed by the API."
        . "<br>If this list is not empty, Only these courses can be read and modified."
        . "<br>Ids of courses or shortnames, separated by commas or new line."
        . "<br>Keep it empty if you don't want to restrict courses."
        . "<br>The designated user must have the capability to update the courses.";
$string['config:allowed_courses:label'] = 'Allowed courses';
$string['config:extend:desc'] = "You can allow or block the automatic fields extension.";
$string['config:extend:impossible'] = "<span style='color: red'>Your database configuration is not supported to allow fields extension.</b></span>";
$string['config:extend:label'] = 'Fields extension';
$string['config:gradebook:desc'] = "You can allow or block the automatic gradebook names update.";
$string['config:gradebook:label'] = 'Gradebook update';
$string['config:ips:desc'] = "Enter the list of IP addresses allowed to call the API, one per line."
        . "<br>Keep it empty if you don't want to restrict senders.";
$string['config:ips:label'] = 'Allowed IPs adresses';
$string['config:key:desc'] = "Enter a random key and keep it secret. You will need it to use the API."
        . "<br><b>Enter * to generate a new key automatically</b>."
        . "<br>If the key is empty, or less than 10 characters, the API is disabled.";
$string['config:key:label'] = 'API Key';
$string['config:open:desc'] = "Answer 'Yes' to enable the API, 'no' to suspend it.";
$string['config:open:label'] = 'Enable API';
$string['config:previous:desc'] = "You can force update request to provide the current value of each text."
        . "<br>If this value is not the current value, the update will be rejected.";
$string['config:previous:label'] = 'Require previous value verification';
$string['config:questions:desc'] = "You can allow or block exporting and importing texts of questions, depending on where is the question bank";
$string['config:questions:label'] = 'Questions management';
$string['config:restricted_courses:desc'] = "Enter the list of courses the API can't access."
        . "<br>Ids of courses or shortnames, separated by commas or new line."
        . "<br>Keep it empty if you don't want to restrict courses.";
$string['config:restricted_courses:label'] = 'Restricted courses';
$string['config:tags:desc'] = "You can allow or block exporting and importing texts of tags";
$string['config:tags:label'] = 'Tags management';
$string['config:title'] = 'ezXlate plugin configuration';
$string['config:user:desc'] = "The API wil be used by this user, with this user's capabilities";
$string['config:user:label'] = 'User for API';
$string['config:user:none'] = 'No user active with availability local/ezxlate:use';
$string['contextlevel_course'] = 'Only questions in courses';
$string['contextlevel_coursecat'] = 'Questions in courses or course categories';
$string['contextlevel_none'] = 'Questions management disabled';
$string['contextlevel_system'] = 'All questions';
$string['ezxlate:use'] = 'Use the ezxlate API';
$string['pluginname'] = 'ezXlate : API for external translation pipelines';
$string['privacy:metadata'] = 'The ezxlate plugin does not store any personal data.';
