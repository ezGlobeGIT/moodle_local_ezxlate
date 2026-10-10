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
 * Class to access to database (using DB API from Moodle)
 *
 * @package    local_ezxlate
 * @copyright  2025 EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezxlate;

/**
 * Class to access data (using Moodle API)
 *
 * This class provides only static methods
 *
 * @package    local_ezxlate
 * @copyright  2025 EzGlobe
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class database {
    /** @var array $idnames tables where id name is not "id" : [ "tableName" => "id_name", ... ] */
    protected static $idnames = [];

    /**
     * Get the name of the id column of a table
     *
     * @param string $table table name (without prefix)
     * @return string name of the id column
     */
    public static function id_name($table) {
        if (isset(static::$idnames[$table])) {
            return static::$idnames[$table];
        } else {
            return "id";
        }
    }

    /**
     * Get one record of a table
     *
     * @param string $table table name (without prefix)
     * @param mixed $value value to search
     * @param null|string $name column to search in (default: id column)
     * @return null|\stdClass the record, null if not found or on error
     */
    public static function get($table, $value, $name = null) {
        if (is_null($name)) {
            $name = static::id_name($table);
        }
        return static::load_one("SELECT * FROM {" . $table . "} WHERE  $name = :$name", [ "$name" => $value]);
    }

    /**
     * Get records of a table, all records or those matching a value
     *
     * @param string $table table name (without prefix)
     * @param mixed $value value to search, null to get all records
     * @param null|string $name column to search in (default: id column)
     * @return array records indexed by the first column, empty on error
     */
    public static function get_all($table, $value = null, $name = null) {
        if (is_null($name)) {
            $name = static::id_name($table);
        }
        if (is_null($value)) {
            return static::load_multiple("SELECT * FROM {" . $table . "}");
        } else {
            return static::load_multiple("SELECT * FROM {" . $table . "} WHERE  $name = :$name", [ "$name" => $value]);
        }
    }

    /**
     * Check the query parameters: the values come from the JSON request, an array or an object
     * sent instead of an id would raise a PHP error exposing the database driver
     *
     * @param array $param query parameters
     * @return bool true if every value is scalar or null
     */
    protected static function scalar_params($param) {
        foreach ($param as $value) {
            if (!is_null($value) && !is_scalar($value)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get one record from an SQL query
     *
     * @param string $sql SQL query
     * @param array $param query parameters
     * @return null|\stdClass the record, null if not found or on error
     */
    public static function load_one($sql, $param = []) {
        global $DB;
        if (!static::scalar_params($param)) {
            return null;
        }
        try {
            $result = $DB->get_record_sql($sql, $param);
            if (empty($result)) {
                return null;
            } else {
                return $result;
            }
        } catch (\dml_exception $ex) {
            return null;
        }
    }

    /**
     * Get records from an SQL query
     *
     * @param string $sql SQL query
     * @param array $param query parameters
     * @param int $limitfrom return a subset of records, starting at this point
     * @param int $limitnum return a subset comprising this many records (0 for all)
     * @return array records indexed by the first column, empty on error
     */
    public static function load_multiple($sql, $param = [], $limitfrom = 0, $limitnum = 0) {
        global $DB;
        if (!static::scalar_params($param)) {
            return [];
        }
        try {
            $result = $DB->get_records_sql($sql, $param, $limitfrom, $limitnum);
            return (array) $result;
        } catch (\dml_exception $ex) {
            return [];
        }
    }

    /**
     * Update one field of one record
     *
     * @param string $table table name (without prefix)
     * @param int $id id of the record
     * @param string $fieldname name of the field to update
     * @param mixed $newvalue new value
     * @return bool true if updated, false on error
     */
    public static function update($table, $id, $fieldname, $newvalue) {
        global $DB;
        $object = new \stdClass();
        $idname = static::id_name($table);
        $object->$idname = $id;
        $object->$fieldname = $newvalue;
        try {
            $result = $DB->update_record($table, $object);
            return $result;
        } catch (\coding_exception $e) {
            return false;
        } catch (\dml_write_exception $e) {
            return false;
        } catch (\dml_exception $ex) {
            return false;
        }
    }
}
