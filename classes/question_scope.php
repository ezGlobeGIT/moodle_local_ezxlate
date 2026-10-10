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
 * Scope of the question banks managed by the API
 *
 * @package    local_ezxlate
 * @copyright  2025 EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezxlate;

/**
 * Scope of the question banks managed by the API
 *
 * The "questions" setting is a minimal context level (system, course category, course).
 * Before Moodle 5.0, it is the context level of the question category. Since Moodle 5.0, every
 * category lives in a module context (mod_qbank, or the activity owning its questions), so the
 * level is rebuilt from the bank hosting the category, as the migration to mod_qbank did it:
 *   - the bank of the site course holds the former system categories,
 *   - a course created by the migration inside a course category ("{category name}-{category id}"
 *     shortname) holds the former categories of this course category,
 *   - any other bank is a course bank.
 *
 * This class provides only static methods
 *
 * @package    local_ezxlate
 * @copyright  2025 EzGlobe
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_scope {
    /** @var null|bool $qbankmodel cache of qbank_model() */
    protected static $qbankmodel = null;

    /**
     * Minimal context level allowed by the settings
     *
     * @return int|null context level, null if questions management is disabled (999, or 0/unset as by default)
     */
    public static function minimal_level() {
        $level = (int) get_config("local_ezxlate", "questions");
        if ($level == 0 || $level == 999) {
            return null;
        }
        return $level;
    }

    /**
     * Tell if a logical level is allowed by the settings
     *
     * @param int $level logical context level of a category (see category_level())
     * @return bool true if allowed
     */
    public static function level_allowed($level) {
        $minimal = static::minimal_level();
        return !is_null($minimal) && $level >= $minimal;
    }

    /**
     * Tell if question banks are mod_qbank activities (Moodle 5.0+)
     *
     * @return bool
     */
    public static function qbank_model() {
        if (is_null(static::$qbankmodel)) {
            static::$qbankmodel = !empty(database::get("modules", "qbank", "name"));
        }
        return static::$qbankmodel;
    }

    /**
     * Logical level of a question category, as it was before Moodle 5.0
     *
     * @param \stdClass|\context $context context of the category (contextlevel and instanceid are used)
     * @return int context level
     */
    public static function category_level($context) {
        if (!static::qbank_model() || $context->contextlevel != CONTEXT_MODULE) {
            return (int) $context->contextlevel;
        }
        $cm = database::get("course_modules", $context->instanceid);
        if (empty($cm) || entity::module_name($cm->module) != "qbank") {
            return CONTEXT_MODULE;     // Questions owned by an activity (a quiz).
        }
        if ($cm->course == SITEID) {
            return CONTEXT_SYSTEM;
        }
        if (static::is_category_bank_course($cm->course)) {
            return CONTEXT_COURSECAT;
        }
        return CONTEXT_COURSE;
    }

    /**
     * Course owning a question category, for the course restrictions of the settings
     *
     * Shared banks (system and course category levels) are not attached to a course, as before Moodle 5.0.
     *
     * @param \stdClass|\context $context context of the category
     * @return int|null course id, null for a shared bank
     */
    public static function category_courseid($context) {
        $level = static::category_level($context);
        if ($level != CONTEXT_COURSE && $level != CONTEXT_MODULE) {
            return null;
        }
        if ($context->contextlevel == CONTEXT_COURSE) {
            return (int) $context->instanceid;
        }
        if ($context->contextlevel == CONTEXT_MODULE) {
            $cm = database::get("course_modules", $context->instanceid);
            if (!empty($cm)) {
                return (int) $cm->course;
            }
        }
        return null;
    }

    /**
     * Question contexts visible from a course (whatever the settings)
     *
     * @param \context_course $coursecontext context of the course
     * @return int[] context ids
     */
    public static function course_contexts($coursecontext) {
        $courseid = $coursecontext->instanceid;
        // Banks owned by the activities of the course, and the course banks (Moodle 5.0+).
        $ids = static::course_module_contexts($courseid);
        // The course and the context tree above it: the shared categories of Moodle 4.x, and on
        // Moodle 5.0+ those not transferred yet (an ad hoc task moves them, at the first cron run
        // after the upgrade). Once transferred, these contexts hold no category any more.
        foreach ($coursecontext->get_parent_contexts(true) as $c) {
            $ids[] = (int) $c->id;
        }
        if (!static::qbank_model()) {
            return array_unique($ids);
        }

        // Moodle 5.0+ : the shared banks relocated by the migration : the bank of the site course
        // (formerly the system level), and those of the courses created by the migration inside
        // the parent course categories (formerly the course category level).
        $catids = [];
        foreach ($coursecontext->get_parent_contexts(false) as $c) {
            if ($c->contextlevel == CONTEXT_COURSECAT) {
                $catids[] = (int) $c->instanceid;
            }
        }
        $sql = "SELECT ctx.id, co.id AS courseid FROM {qbank} q "
                . "JOIN {course} co ON co.id = q.course "
                . "JOIN {modules} m ON m.name = 'qbank' "
                . "JOIN {course_modules} cm ON cm.instance = q.id AND cm.module = m.id "
                . "JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :level "
                . "WHERE q.type = 'system' AND co.id <> :courseid";
        foreach (database::load_multiple($sql, [ "level" => CONTEXT_MODULE, "courseid" => $courseid]) as $ctx) {
            if ($ctx->courseid == SITEID) {
                $ids[] = (int) $ctx->id;
            } else if (in_array(static::category_bank_course_category($ctx->courseid), $catids)) {
                $ids[] = (int) $ctx->id;
            }
        }
        return array_unique($ids);
    }

    /**
     * Contexts of every module of a course
     *
     * @param int $courseid
     * @return int[] context ids
     */
    protected static function course_module_contexts($courseid) {
        $ids = [];
        $sql = "SELECT ctx.id FROM {context} ctx "
                . "JOIN {course_modules} cm ON cm.id = ctx.instanceid "
                . "WHERE ctx.contextlevel = :level AND cm.course = :courseid";
        foreach (database::load_multiple($sql, [ "level" => CONTEXT_MODULE, "courseid" => $courseid]) as $ctx) {
            $ids[] = (int) $ctx->id;
        }
        return $ids;
    }

    /**
     * Tell if a course was created by the migration to mod_qbank to host the banks of a course category
     *
     * @param int $courseid
     * @return bool
     */
    protected static function is_category_bank_course($courseid) {
        return !is_null(static::category_bank_course_category($courseid));
    }

    /**
     * Course category whose former banks are hosted by a course (created by the migration to mod_qbank)
     *
     * The migration task (mod_qbank\task\transfer_question_categories) names such a course
     * "{category name}-{category id}" and creates it inside the category.
     *
     * @param int $courseid
     * @return int|null course category id, null if the course is not such a course
     */
    protected static function category_bank_course_category($courseid) {
        $course = database::get("course", $courseid);
        if (empty($course) || empty($course->category)) {
            return null;
        }
        $category = database::get("course_categories", $course->category);
        if (empty($category) || $course->shortname !== $category->name . "-" . $category->id) {
            return null;
        }
        return (int) $category->id;
    }
}
