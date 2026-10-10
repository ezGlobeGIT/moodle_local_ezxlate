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
 * API EzXlate translation Process
 *
 * @package    local_ezxlate
 * @copyright  2025 EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * 20261007 :   Compatibility with Moodle 5.0 to 5.3 (question banks, subsections, feedback items)
 *              Security improvements (API key check, IP filtering, sessionless endpoints)
 *              Gradebook kept in sync, detailed update errors, safer fields extension
 *              Some fixes (data, stickynotes, unilabel, questionnaire exports)
 *              Rewritten to follow all Moodle coding standards
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_ezxlate';
$plugin->release = '1.1';
$plugin->version = 2026101000;
$plugin->maturity  = MATURITY_STABLE;
$plugin->requires = 2022112800; // Moodle 4.1+.
