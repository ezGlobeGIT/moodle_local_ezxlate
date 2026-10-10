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
 * Class to manage entity fields not directly connected to DB
 *
 * @package    local_ezxlate
 * @copyright  2025 EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezxlate;

/**
 * Class to manage a direct value (so not attached to the database)
 *
 * See local_ezxlate\entity and local_ezxlate\tree_interface
 *
 * @package    local_ezxlate
 * @copyright  2025 EzGlobe
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class value implements tree_interface {
    /**
     * @var mixed $value the value
     */
    protected $value;
    /**
     * @var bool $onlyget always true in practice: a direct value can't be updated
     */
    protected $onlyget = false;
    /**
     * @var string $error result code of update ("ok" or error code)
     */
    protected $error = "ok";

    /**
     * Constructor
     *
     * @param mixed $value the value
     */
    public function __construct($value) {
         $this->value = $value;
    }
    /**
     * Tell this value is available only to get information and can't be updated
     *
     * @return value this object
     */
    public function only_get() {
        $this->onlyget = true;
        return $this;
    }

    /**
     * Get the value
     *
     * @return mixed the value, 0 for zero, null if empty
     */
    public function get() {
        if ($this->value === 0 || $this->value === "0") {
            return 0;
        }
        if (empty($this->value)) {
            return null;
        }
        return $this->value;
    }

    /**
     * Update the value: not possible for a direct value, sets the "notfound" error
     *
     * @param mixed $newvalue new value
     * @param mixed $previous expected current value
     */
    public function update($newvalue, $previous = null) {
        $this->error = "notfound";      // Simple values can't be updated.
    }

    /**
     * Get the update error
     *
     * @return string|null error code, null if no error
     */
    public function get_errors() {
        // Return all errors in the tree.
        if ($this->error != "ok") {
            return $this->error;
        }
    }
}
