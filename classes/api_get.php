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
 * Class to manage api with "get" command
 *
 * @package    local_ezxlate
 * @copyright  2025 EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezxlate;

use DateTime;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/questionlib.php');

/**
 * Class to manage API for entry point : get.php
 *
 * This entry point is to get informations from Moodle data
 * In this entry point, "action" parameters tell what kind of informations you want to get
 *
 * @package    local_ezxlate
 * @copyright  2025 EzGlobe
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_get extends api {
    /** @var string $action Action requested */
    protected $action;

    /** @var array $contextlevel Explicit texts for contexts levels, used by some answers */
    protected static $contextlevel = [
                10 => "system",
                30 => "user",
                40 => "coursecat",
                50 => "course",
                60 => "group",
                70 => "module",
                80 => "block",
            ];

    /**
     * Check the parameters: the action is required
     *
     * @return null|\stdClass error answer, null if parameters are correct
     */
    protected function check_parameters() {
        // Check if we have an action and set it.
        if (empty($this->param->action)) {
            return $this->error("error", "action is missing");
        }
        $this->action = $this->param->action;
    }

    /**
     * Launch the do_xxx method of the requested action
     *
     * @return null|\stdClass the answer, null if built in attributes
     */
    protected function do() {
        // We made a do_xxx method for each action : so we call the right method.
        $method = "do_" . $this->action;
        if (!method_exists($this, $method)) {
            // No method found for this action, so action is unknown.
            return $this->error("error", "action '$this->action' unknown");
        } else {
            return $this->$method();
        }
    }

    /**
     * Process the data to send for action : course
     *
     * Extract the information from a course (see documention)
     */
    protected function do_course() {
        // Check if course exists.
        if (!empty($this->param->courseid)) {
            $course = new course($this->param->courseid);
        } else if (!empty($this->param->shortname)) {
            $course = new course($this->param->shortname, true);
        } else {
            return $this->error("error", "courseid or shortname must be provided");
        }
        if (!$course->is()) {
            return $this->error("notfound", "course not found");
        }
        // Check if shortname and id are the same course.
        if (!empty($this->param->courseid) && !empty($this->param->shortname)) {
            if ($this->param->shortname != $course->shortname) {
                return $this->error("notfound", "course not found");
            }
        }
        // Check if course is not restricted.
        if (!$course->allowed()) {
            return $this->error("restricted", "course restricted in API settings");
        }
        // The course may have been requested by its shortname only.
        $context = \context_course::instance($course->id);
        // Check if user has the capability 'moodle/course:update' on this course.
        if (! has_capability('moodle/course:update', $context)) {
            return $this->error("restricted", "User defined for the API is not allowed to update this course");
        }
        // Construct the course entity.
        $course = new \local_ezxlate\entities\course($course->get());
        // Extract the information in data attribute.
        $this->data = $course->get();
    }

    /**
     * Process the data to send for action : module
     *
     * Extract the texts from a module (depending on the type of activity) (see documention)
     */
    protected function do_module() {
        // Check course and module.
        if (empty($this->param->courseid)) {
            return $this->error("error", "courseid must be provided");
        }
        if (empty($this->param->cmid)) {
            return $this->error("error", "cmid must be provided");
        }
        $course = new course($this->param->courseid);
        // Check if course exists.
        if (!$course->is()) {
            return $this->error("notfound", "course not found");
        }
        // Check if course is not restricted.
        if (!$course->allowed()) {
            return $this->error("restricted", "course restricted in API settings");
        }
        $module = database::get("course_modules", $this->param->cmid);
        if (empty($module) || $module->course != $this->param->courseid) {
            // Module with given cmid is not in database.
            return $this->error("notfound", "module not found");
        }
        if (entity::module_name($module->module) == "subsection") {
            // Module is a subesction, has to be managed as a section.
            return $this->error("notfound", "module is a subsection");
        }
        // Check if user has the capability 'moodle/course:manageactivities' on this module.
        $context = \context_module::instance($this->param->cmid);
        if (! has_capability('moodle/course:manageactivities', $context)) {
            return $this->error("restricted", "User defined for the API is not allowed to update this module");
        }

        // Construct the entity to manage this module.
        $infosfields = [
            "courseid" => $module->course,
            "module" => entity::module_name($module->module),
            "cmid" => $module->id,
        ];
        // Specific classe for this kind of activity.
        $class = "\\local_ezxlate\\entities\\" . entity::module_name($module->module);
        if (class_exists($class)) {
            // We have a specific class.
            $module = new $class($module->instance, null, [], $infosfields);
        } else {
            // We don't have specific class, we use the generic one.
            $module = new entity($module->instance, entity::module_name($module->module), [ "name", "intro"], $infosfields);
        }
        // Extract data to translate for Moodle database.
        $this->data = $module->get();
    }

    /**
     * Process the data to send for action : questioncategories
     *
     * Extract questions categories available in a course
     * Plugin settings can limit the categories to some contexte levels
     */
    protected function do_questioncategories() {
        // Is question management restricted.
        if (is_null(question_scope::minimal_level())) {
            return $this->error("restricted", "restricted by plugin settings");
        }
        // Check course.
        if (empty($this->param->courseid)) {
            return $this->error("error", "courseid must be provided");
        }
        $course = new course($this->param->courseid);
        if (!$course->is()) {
            return $this->error("notfound", "course not found");
        }
        if (!$course->allowed()) {
            return $this->error("restricted", "course restricted by API settings");
        }
        $context = \context_course::instance($course->id);
        if (! has_capability('moodle/course:update', $context)) {
            // User must hav the capability 'moodle/course:update' in this course.
            return $this->error("restricted", "User defined for the API is not allowed to update this course");
        }

        // Get all questions contexts for this course (the storage model changed in Moodle 5.0).
        $contexts = question_scope::course_contexts($context);
        if (empty($contexts)) {
            return;
        }

        // Non-top categories holding at least one question. The core helper is not used: since Moodle 5.0
        // it keeps the module contexts only, and would hide the categories not transferred yet.
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal($contexts, SQL_PARAMS_NAMED);
        $sql = "SELECT c.* FROM {question_categories} c "
                . "WHERE c.contextid $insql AND c.parent <> 0 "
                . "AND EXISTS (SELECT 1 FROM {question_bank_entries} qbe WHERE qbe.questioncategoryid = c.id) "
                . "ORDER BY c.parent, c.sortorder, c.name";
        foreach (database::load_multiple($sql, $params) as $categ) {
            $id = $categ->id;
            $catcontext = database::get("context", $categ->contextid);
            if (empty($catcontext)) {
                continue;
            }
            // Verify is category is allowed.
            if (! has_capability('moodle/question:editall', \context::instance_by_id($catcontext->id))) {
                continue;
            }
            // Verify is the level is allowed (logical level of the category, as it was before Moodle 5.0).
            $level = question_scope::category_level($catcontext);
            if (!question_scope::level_allowed($level)) {
                continue;
            }
            // Check if the course owning the category is restricted.
            $courseid = question_scope::category_courseid($catcontext);
            if (!empty($courseid)) {
                $owner = new course($courseid);
                if (!$owner->allowed()) {
                    continue;
                }
            }
            $this->data->$id = [
                "name" => $categ->name,
                "context" => isset(static::$contextlevel[$level]) ? static::$contextlevel[$level] : "unknown",
            ];
        }
    }

    /**
     * Process the data to send for action : questions
     *
     * Extract questions of a question category
     */
    protected function do_questions() {
        // Is question management restricted.
        if (is_null(question_scope::minimal_level())) {
            return $this->error("restricted", "restricted by plugin settings");
        }
        if (empty($this->param->categoryid)) {
            return $this->error("error", "categoryid must be provided");
        }
        if (isset($this->param->versions) && $this->param->versions == "last") {
            $last = true;
        } else {
            $last = false;
        }
        // Verify is category is allowed.
        $category = database::get("question_categories", $this->param->categoryid);
        if (empty($category)) {
            return $this->error("notfound", "category not found");
        }
        $context = \context::instance_by_id($category->contextid);
        // Verify if user can edit this categoty.
        if (! has_capability('moodle/question:editall', $context)) {
            return $this->error("restricted", "User defined for the API is not allowed to update this category");
        }
        if (!question_scope::level_allowed(question_scope::category_level($context))) {
            return $this->error("restricted", "Context level of this question category is not allowed by API settings");
        }
        // Check if the course owning the category is allowed.
        $courseid = question_scope::category_courseid($context);
        if (!empty($courseid)) {
            $course = new course($courseid);
            if (!$course->is()) {
                return $this->error("notfound", "course for category not found");
            }
            if (!$course->allowed()) {
                return $this->error("restricted", "course for category restricted");
            }
        }
        // Build list of questions.
        $questions = new \stdClass();
        foreach (database::get_all("question_bank_entries", $this->param->categoryid, "questioncategoryid") as $questionbank) {
            $sql = "SELECT q.* FROM {question} q "
                    . "JOIN {question_versions} qv ON qv.questionid = q.id "
                    . "WHERE qv.questionbankentryid = :qb "
                    . "ORDER BY qv.version DESC";
            // We want only last version of each question, or all versions.
            $limit = $last ? 1 : 0;
            foreach (database::load_multiple($sql, ["qb" => $questionbank->id], 0, $limit) as $record) {
                // If user can't edit this question, we skip it.
                if (! question_has_capability_on($record->id, 'edit')) {
                    continue;
                }
                // Get all texts to translate from the entity "question".
                $question = new \local_ezxlate\entities\question($record);
                $qid = $record->id;
                $questions->$qid = $question->get();
            }
        }
        $this->data->categoryid = $this->param->categoryid;
        if (!empty($questions)) {
            $this->data->questions = $questions;
        }
    }

    /**
     * Process the data to send for action : tags
     *
     * Extract tags
     */
    protected function do_tags() {
        // Check ig tag management is allowed.
        if (get_config("local_ezxlate", "tags") == 0) {
            return $this->error("restricted", "restricted by plugin settings");
        }
        // Check if user can edit ags (capability 'moodle/tag:edit').
        $context = \context_system::instance();
        if (! has_capability('moodle/tag:edit', $context)) {
            return $this->error("restricted", "User defined for the API is not allowed to update tags");
        }

        // Build tag list (using entity "tag").
        foreach (database::get_all("tag") as $tag) {
            $id = $tag->id;
            $tag = new \local_ezxlate\entities\tag($tag);
            $this->data->$id = $tag->get();
        }
    }
}
