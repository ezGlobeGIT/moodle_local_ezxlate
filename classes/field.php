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
 * Class to manage fields
 *
 * @package    local_ezxlate
 * @copyright  2025 EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezxlate;

/**
 * Class to manage a node that is a field (a column of a row) of a table
 *
 * see local_ezxlate\entity and local_ezxlate\tree_interface
 * This class also manage the update in Database, and the field extension if needed (and allowed)
 *
 * @package    local_ezxlate
 * @copyright  2025 EzGlobe
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field implements tree_interface {
    /**
     * @var string $table table of the field
     */
    protected $table;
    /**
     * @var string $name name of the field (column)
     */
    protected $name;
    /**
     * @var int $id id of the row
     */
    protected $id;
    /**
     * @var mixed $value current value of the field
     */
    protected $value;
    /**
     * @var bool $onlyget true if the field is available only to get information
     */
    protected $onlyget = false;
    /**
     * @var bool $gradebook true if the gradebook item name follows this field (activity names)
     */
    protected $gradebook = false;
    /**
     * @var string $error result code of update ("ok" or error code)
     */
    protected $error = "ok";
    /**
     * @var array $updated fields really changed during the request ("table:id:name")
     */
    protected static $updated = [];

    /**
     * @var bool $previousverification true if the previous value must be checked before updating
     */
    protected static $previousverification = false;
    /**
     * @var bool $extend true if fields can be extended in the database schema
     */
    protected static $extend = false;
    /**
     * @var bool $updategradebook true if gradebook item names must follow activity names
     */
    protected static $updategradebook = false;

    /**
     * Set the features requested by the client, if allowed by the settings
     *
     * The previous value verification is imposed by the settings: the client can't avoid it.
     *
     * @param int $extend 1 to allow fields extension
     * @param int $updategradebook 1 to update gradebook item names
     */
    public static function features($extend, $updategradebook) {
        static::$previousverification = get_config("local_ezxlate", "previous") == 1;
        if (dbinfos::can_extend() && $extend == 1) {
            static::$extend = true;
        } else {
            static::$extend = false;
        }
        if (get_config("local_ezxlate", "gradebook") == 1 && $updategradebook == 1) {
            static::$updategradebook = true;
        }
    }

    /**
     * Get the fields really changed during the request
     *
     * @return array list of "table:id:name"
     */
    public static function updated() {
        return static::$updated;
    }


    /**
     * Constructor
     *
     * @param mixed $value value, or row (array or object) holding the field
     * @param null|string $table table of the field
     * @param null|int $id id of the row
     * @param null|string $name name of the field (column)
     */
    public function __construct($value = null, $table = null, $id = null, $name = null) {
        $this->table = $table;
        $this->id = $id;
        $this->name = $name;
        if (is_array($value) && isset($value[$name])) {
            $this->value = $value[$name];
        } else if (is_object($value) && isset($value->$name)) {
            $this->value = $value->$name;
        } else if (is_object($value) || is_array($value)) {
            $this->value = null;
        } else {
            $this->value = $value;
        }
    }

    /**
     * Tell this field is available only to get information and can't be updated
     *
     * @return field this object
     */
    public function only_get() {
        $this->onlyget = true;
        return $this;
    }

    /**
     * Tell the gradebook item name follows this field (activity names)
     *
     * @return field this object
     */
    public function gradebook() {
        $this->gradebook = true;
        return $this;
    }

    /**
     * Get the value of the field
     *
     * @return mixed the value, 0 for zero, null if empty
     */
    public function get() {
        // Return value of field if it's allowed for GET API.
        if ($this->value === 0 || $this->value === "0") {
            return 0;
        }
        if (empty($this->value)) {
            return null;
        }
        return $this->value;
    }

    /**
     * Set the error code
     *
     * @param string $error error code
     * @return bool always false
     */
    protected function error($error = "error") {
        $this->error = $error;
        return false;
    }

    /**
     * Update the field in the database (and the gradebook item name if needed)
     *
     * @param string $newvalue new value
     * @param string $previous expected current value (checked only if required)
     * @return bool true if updated
     */
    public function update($newvalue, $previous = "") {
        if ($this->onlyget) {
            return $this->error("notfound");
        }
        if (!is_scalar($newvalue)) {
            return $this->error("error");
        }
        $newvalue = (string) $newvalue;
        if (empty(trim((string) $this->value))) {
            return $this->error("empty", "It's forbidden to change values of empty fields");
        }
        if (empty(trim($newvalue))) {
            return $this->error("empty", "New value can't be empty");
        }
        if (! $this->check_previous($previous)) {
            return $this->error("previous");
        }
        if (! $this->check_and_extend(mb_strlen($newvalue))) {
            return $this->error("toolong");
        }
        if (!database::update($this->table, $this->id, $this->name, $newvalue)) {
            return $this->error("error");
        }
        if ($this->value != $newvalue) {
            static::$updated[] = $this->table . ':' . $this->id .  ':' . $this->name;
        }
        if ($this->gradebook && static::$updategradebook) {
            $this->update_gradebook($newvalue);
        }
        return $this->error == "ok";
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

    /**
     * Check the previous value sent by the client, if required
     *
     * @param string $previous expected current value
     * @return bool true if the check is not required or succeeds
     */
    protected function check_previous($previous) {
        if (!static::$previousverification) {
            return true;
        }
        if (!is_scalar($previous) || empty(trim((string) $previous))) {
            return false;
        }
        if (trim((string) $previous) !== trim((string) $this->value)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Check the size of a field, and extend it if needed and allowed
     *
     * @param int $len length of the new value
     * @param null|string $table table (default: table of this field)
     * @param null|string $field field (default: this field)
     * @return bool true if the new value fits in the field
     */
    protected function check_and_extend($len, $table = null, $field = null) {
        if (empty($table)) {
            $table = $this->table;
        }
        if (empty($field)) {
            $field = $this->name;
        }
        if ($len <= dbinfos::get_field_size($table, $field)) {
            return true;
        }
        if (!static::$extend) {
            return false;
        }
        if (!dbinfos::adjust_field($table, $field, $len)) {
            return false;
        }
        // The recycle bin copies the names of the deleted activities and courses: its columns follow,
        // only once the column itself is extended, and only for these names.
        if ($field == "name" && !empty(database::get("modules", $table, "name"))) {
            dbinfos::adjust_field("tool_recyclebin_course", "name", $len);
        }
        if ($table == "course" && $field == "fullname") {
            dbinfos::adjust_field("tool_recyclebin_category", "fullname", $len);
        }
        return true;
    }

    /**
     * Update the name of the gradebook item of the activity
     *
     * @param string $newvalue new name
     * @return bool true if updated or no item to update
     */
    protected function update_gradebook($newvalue) {
        global $CFG;
        $modname = $this->table;
        // Only existing grade items are renamed: the gradebook settings of the activity are kept.
        $sql = "SELECT id FROM {grade_items} "
                . "WHERE itemtype = 'mod' AND itemmodule = :module AND iteminstance = :instance";
        if (empty(database::load_multiple($sql, ["module" => $modname, "instance" => $this->id]))) {
            return true;
        }
        $len = mb_strlen($newvalue);
        if (
            !$this->check_and_extend($len, "grade_items", "itemname")
                || !$this->check_and_extend($len, "grade_items_history", "itemname")
        ) {
            return $this->error("gradebookfailed");
        }

        // The activity names its grade items itself, as when its settings form is saved: a forum or a
        // workshop derives the names of its items, and no grade is recalculated since none is given.
        // <mod>_grade_item_update($instance, $grades = null) exists from Moodle 4.1 to 5.3 for every
        // graded core activity (bigbluebuttonbn from 5.0 only) and for the usual third-party ones.
        $dir = \core_component::get_component_directory("mod_" . $modname);
        if (empty($dir) || !file_exists("$dir/lib.php")) {
            return true;
        }
        require_once($CFG->libdir . '/gradelib.php');
        require_once("$dir/lib.php");
        $function = $modname . "_grade_item_update";
        if (!function_exists($function)) {
            return true;
        }
        if ((new \ReflectionFunction($function))->getNumberOfRequiredParameters() > 1) {
            return $this->error("gradebookfailed");
        }
        $instance = database::get($modname, $this->id);
        $sql = "SELECT cm.id, cm.idnumber FROM {course_modules} cm "
                . "JOIN {modules} m ON m.id = cm.module "
                . "WHERE m.name = :module AND cm.instance = :instance";
        $cm = database::load_one($sql, ["module" => $modname, "instance" => $this->id]);
        if (empty($instance) || empty($cm)) {
            return $this->error("gradebookfailed");
        }
        // Properties given by the settings form, not stored in the activity table: without them, some
        // activities (assign, h5pactivity) would erase the idnumber of the grade item.
        $instance->cmidnumber = $cm->idnumber;
        if (!property_exists($instance, "idnumber")) {
            $instance->idnumber = $cm->idnumber;
        }
        $instance->coursemodule = $cm->id;
        $instance->modulename = $modname;
        $instance->instance = $instance->id;

        // The JSON answer must not be broken by an output of the activity.
        ob_start();
        try {
            $result = $function($instance);
        } catch (\Throwable $e) {
            $result = GRADE_UPDATE_FAILED;
            debugging("local_ezxlate: $function failed: " . $e->getMessage(), DEBUG_DEVELOPER);
        } finally {
            ob_end_clean();
        }
        // Some activities return nothing (workshop), the others a GRADE_UPDATE_xxx code.
        if ($result === false || $result === GRADE_UPDATE_FAILED || $result === GRADE_UPDATE_ITEM_LOCKED) {
            return $this->error("gradebookfailed");
        }
        return true;
    }
}
