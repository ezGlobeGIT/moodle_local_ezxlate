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
 * Class to check courses
 *
 * @package    local_ezxlate
 * @copyright  2025 EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezxlate;

/**
 * Class to get general infomation on a course
 *
 * @package    local_ezxlate
 * @copyright  2025 EzGlobe
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course {
    /**
     * @var null|\stdClass $record course record, null if the course doesn't exist
     */
    protected $record = null;

    /**
     * Constructor: load the course
     *
     * @param int|string $idorshortname id (numeric) or shortname of the course
     * @param bool $isshortname true if the value is always a shortname (a shortname can be numeric)
     */
    public function __construct($idorshortname, $isshortname = false) {
        if (!is_scalar($idorshortname)) {
            return;
        }
        if (is_numeric($idorshortname) && !$isshortname) {
            $this->record = database::get("course", $idorshortname, "id");
        } else {
            $this->record = database::get("course", $idorshortname, "shortname");
        }
    }

    /**
     * Get a field of the course record
     *
     * @param string $name name of the field
     * @return mixed value of the field, null if not set
     */
    public function __get($name) {
        if (isset($this->record->$name)) {
            return $this->record->$name;
        } else {
            return null;
        }
    }

    /**
     * Get the course record
     *
     * @return null|\stdClass course record, null if the course doesn't exist
     */
    public function get() {
        return $this->record;
    }

    /**
     * Check if the course exists
     *
     * @return bool true if the course exists
     */
    public function is() {
        return ! empty($this->record);
    }

    /**
     * Check if the course can be managed by the API (allowed and restricted courses settings)
     *
     * @return bool true if the course exists and is allowed
     */
    public function allowed() {
        if (! $this->is()) {
            return false;
        }
        if (! $this->check_if_in_list("allowed_courses")) {
            return false;
        }
        if ($this->check_if_in_list("restricted_courses", false)) {
            return false;
        }
        return true;
    }

    /**
     * Check if the course is in a list of courses defined in the settings (ids or shortnames)
     *
     * @param string $name name of the setting holding the list
     * @param bool $emptyisyes value to return when the list is empty
     * @return bool true if the course is in the list
     */
    protected function check_if_in_list($name, $emptyisyes = true) {
        $config = get_config("local_ezxlate", $name);
        $config = str_replace(",", "\n", $config);
        $empty = true;
        foreach (explode("\n", $config) as $course) {
            $course = trim($course);
            if (empty($course)) {
                continue;
            }
            $empty = false;
            if (is_numeric($course) && $course == $this->record->id) {
                return true;
            }
            if (is_string($course) && $course == $this->record->shortname) {
                return true;
            }
        }
        if ($empty) {
            return $emptyisyes;
        } else {
            return false;
        }
    }
}
