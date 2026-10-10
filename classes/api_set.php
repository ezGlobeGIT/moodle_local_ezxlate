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
 * Class to manage api with "set" command
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
require_once($CFG->dirroot . '/lib/modinfolib.php');
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Class to manage API for entry point : set.php
 *
 * This entry point is to send translated texts to update in Moodle database
 * In this entry point, "object" parameters tell what kind of data you want to update
 *   (a course, a course section, a module, a question, a tag)
 *
 * @package    local_ezxlate
 * @copyright  2025 EzGlobe
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_set extends api {
    /** @var string $object Type of object to update  */
    protected $object;

    /**
     * Check the parameters (object and data are required) and set the update features
     *
     * @return null|\stdClass error answer, null if parameters are correct
     */
    protected function check_parameters() {
        // Check if we have an object.
        if (empty($this->param->object)) {
            return $this->error("error", "object is missing");
        }
        // Check if we have datas.
        if (empty($this->param->data)) {
            return $this->error("error", "data are missing");
        }
        if (!is_object($this->param->data)) {
            return $this->error("error", "incorrect data");
        }
        $this->object = $this->param->object;

        // Default values for extend, gradebook, previous.
        if (! isset($this->param->extend)) {
            $this->param->extend = 0;
        }
        if (! isset($this->param->gradebook)) {
            $this->param->gradebook = 0;
        }
        // Without "previous", every text is rejected if the settings require the verification.
        if (! isset($this->param->previous) || !is_object($this->param->previous)) {
            $this->param->previous = new \stdClass();
        }
        if (! empty($this->param->gradebook)) {
            $gradebook = 1;
        } else {
            $gradebook = 0;
        }
        // Set the features in field class, will be used to define how to update fields.
        field::features($this->param->extend, $gradebook);
    }

    /**
     * Launch the do_xxx method of the object to update
     *
     * @return null|\stdClass the answer, null if built in attributes
     */
    protected function do() {
        // We made a do_xxx method for each action : so we call the right method.
        $method = "do_" . $this->object;
        if (!method_exists($this, $method)) {
            return $this->error("error", "object '$this->object' unknown");
        }
        return $this->$method();
    }

    /**
     * Last part of processing an object update : update the entity and build the answer
     *
     * @param \local_ezxloate\entity $entity entity to update
     */
    protected function end($entity) {
        // Update data.
        $entity->update($this->param->data, $this->param->previous);
        // Get all errors.
        $errors = $entity->get_errors();
        // Build error report if needed.
        if (!empty($errors)) {
            $this->answer->errors = $errors;
            $this->answer->code = "partial";
        } else {
            $this->answer->code = "ok";
        }
        // Build field extensions report if needed.
        $extended = dbinfos::get_extensions();
        if (!empty($extended)) {
            $this->answer->extended = $extended;
        }
    }

    /**
     * Update a course
     */
    protected function do_course() {
        // Get the course to uodate and check if we are allowed to update it.
        if (empty($this->param->courseid)) {
            return $this->error("error", "courseid must be provided");
        }
        if (empty($this->param->shortname)) {
            return $this->error("error", "shortname must be provided");
        }
        $course = new course($this->param->courseid);
        if (!$course->is()) {
            return $this->error("notfound", "course not found");
        }
        if ($this->param->shortname != $course->shortname) {
            return $this->error("notfound", "incorrect shortname");
        }
        if (!$course->allowed()) {
            return $this->error("restricted", "course restricted in API settings");
        }
        $context = \context_course::instance($this->param->courseid);
        if (! has_capability('moodle/course:update', $context)) {
            return $this->error("restricted", "User defined for the API is not allowed to update this course");
        }

        // Get the \local_ezxlate\entities\course for this course.
        $entity = new \local_ezxlate\entities\course($course->get());
        // Update it and build the answer.
        $this->end($entity);

        // Purge course cache.
        \course_modinfo::purge_course_cache($this->param->courseid);

        if (count(field::updated()) == 0) {
            return;      // We didn't change anything.
        }

        // Course names are also cached in the course listings, as update_course() does.
        \cache_helper::purge_by_event('changesincourse');

        // We change at least one field : we log an event.
        try {
            $event = \core\event\course_updated::create([
                    'context'  => \context_course::instance($this->param->courseid),
                    'objectid' => $this->param->courseid,
                ]);
            $event->trigger();
        } catch (\Throwable $e) {
            // Logging is optional: the texts are already updated, the answer must not fail.
            debugging("local_ezxlate: event not logged: " . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Update a course section
     */
    protected function do_section() {
        // Get course and section, check if we are allowed to manage this course.
        if (empty($this->param->courseid)) {
            return $this->error("error", "courseid must be provided");
        }
        if (empty($this->param->sectionid)) {
            return $this->error("error", "sectionid must be provided");
        }
        $course = new course($this->param->courseid);
        if (!$course->is()) {
            return $this->error("notfound", "course not found");
        }
        if (!$course->allowed()) {
            return $this->error("restricted", "course restricted in API settings");
        }
        $context = \context_course::instance($this->param->courseid);
        if (! has_capability('moodle/course:update', $context)) {
            return $this->error("restricted", "User defined for the API is not allowed to update this course");
        }
        $sql = "SELECT * FROM {course_sections} WHERE course = :course and id = :sectionid";
        $section = database::load_one($sql, [ "course" => $this->param->courseid, "sectionid" => $this->param->sectionid]);
        if (empty($section)) {
            return $this->error("notfound", "section not found");
        }
        if (!$course->allowed()) {
            return $this->error("restricted");
        }

        // Get the \local_ezxlate\entities\section for this section.
        $entity = new \local_ezxlate\entities\section($section);
        // Update it and build the answer.
        $this->end($entity);
        $this->update_subsection_name($section);

        // Purge course cache.
        \course_modinfo::purge_course_cache($this->param->courseid);

        if (count(field::updated()) == 0) {
            return;   // We didn't change anything.
        }

        // We change at least one field : we log an event.
        try {
            $event = \core\event\course_section_updated::create([
                    'context'  => \context_course::instance($this->param->courseid),
                    'objectid' => $this->param->sectionid,
                    'other'    => [ 'sectionnum' => $section->section ],
                ]);
            $event->trigger();
        } catch (\Throwable $e) {
            // Logging is optional: the texts are already updated, the answer must not fail.
            debugging("local_ezxlate: event not logged: " . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Follow the name of a section delegated to a subsection (Moodle 4.5+) in the subsection activity
     *
     * Moodle copies the name of the activity to its section, never the other way: without this, the
     * activity would keep its former name (activity lists, completion, logs...).
     *
     * @param \stdClass $section record of the updated section
     */
    protected function update_subsection_name($section) {
        if (($section->component ?? '') !== 'mod_subsection' || !isset($this->param->data->name)) {
            return;
        }
        if (!in_array('course_sections:' . $section->id . ':name', field::updated())) {
            return;     // The name of the section was not changed.
        }
        $instance = database::get('subsection', $section->itemid);
        if (empty($instance)) {
            return;
        }
        // Same checks as the section name (extension, length), the current value being the previous one.
        $name = new field($instance->name, 'subsection', $instance->id, 'name');
        if (!$name->update($this->param->data->name, $instance->name)) {
            $errors = (array) ($this->answer->errors ?? []);
            $errors['subsection'] = ['name' => $name->get_errors()];
            $this->answer->errors = $errors;
            $this->answer->code = "partial";
        }
    }

    /**
     * Update an activity
     */
    protected function do_module() {
        // Get module and check parameters, check if allowed.
        if (empty($this->param->courseid)) {
            return $this->error("error", "courseid must be provided");
        }
        if (empty($this->param->module)) {
            return $this->error("error", "module name must be provided");
        }
        if (empty($this->param->cmid)) {
            return $this->error("error", "cmid must be provided");
        }
        $course = new course($this->param->courseid);
        if (!$course->is()) {
            return $this->error("notfound", "course not found");
        }
        if (!$course->allowed()) {
            return $this->error("restricted", "course restricted in API settings");
        }
        $cm = database::get("course_modules", $this->param->cmid);
        if (
            empty($cm)
                || $cm->course != $this->param->courseid
        ) {
            return $this->error("notfound", "module not found");
        }
        if (entity::module_name($cm->module) != $this->param->module) {
            return $this->error("notfound", "module is not a " . $this->param->module);
        }
        // Subsection as to be processed as sections.
        if (entity::module_name($cm->module) == "subsection") {
            return $this->error("notfound", "module is a subsection");
        }
        $context = \context_module::instance($this->param->cmid);
        if (! has_capability('moodle/course:manageactivities', $context)) {
            return $this->error("restricted", "User defined for the API is not allowed to update this module");
        }

        // Look for the right entity class (main classe or inherited specific classe).
        $class = "\\local_ezxlate\\entities\\" . entity::module_name($cm->module);
        if (class_exists($class)) {
            // We have a specific class, we use it.
            $module = new $class($cm->instance, null, []);
        } else {
            // We don't have a specific class for this activity, we use the generic one.
            $module = new entity($cm->instance, entity::module_name($cm->module), [ "name", "intro"]);
        }
        // Every activity name may be followed by grade items (forum, scorm... have no specific class).
        $module->follow_gradebook("name");
        $this->end($module);

        // Purge course cache.
        \course_modinfo::purge_course_cache($this->param->courseid);

        if (count(field::updated()) == 0) {
            return;  // We didn't change anything.
        }

        // Glossary concepts are cached for the auto-linking filter.
        if (entity::module_name($cm->module) == "glossary") {
            $glossary = database::get("glossary", $cm->instance);
            if (!empty($glossary)) {
                \mod_glossary\local\concept_cache::reset_glossary($glossary);
            }
        }

        // We change at least one field : we log an event.
        try {
            $event = \core\event\course_module_updated::create([
                    'context'  => \context_module::instance($this->param->cmid),
                    'objectid' => $this->param->cmid,
                    'other'    => [
                        'modulename' => $this->param->module,
                        'instanceid' => $cm->instance,
                        'name' => isset($this->param->data->name) ? $this->param->data->name : $module->get()["name"],
                    ],
                ]);
            $event->trigger();
        } catch (\Throwable $e) {
            // Logging is optional: the texts are already updated, the answer must not fail.
            debugging("local_ezxlate: event not logged: " . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Update a question
     */
    protected function do_question() {
        // Verify if question management is allowed by setting.
        if (is_null(question_scope::minimal_level())) {
            return $this->error("restricted", "restricted by plugin settings");
        }
        if (empty($this->param->categoryid)) {
            return $this->error("error", "categoryid must be provided");
        }
        // Verify is category is allowed.
        $category = database::get("question_categories", $this->param->categoryid);
        if (empty($category)) {
            return $this->error("notfound", "category not found");
        }
        $context = \context::instance_by_id($category->contextid);
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
        if (! isset($this->param->questionid)) {
            return $this->error("error", "questionid must be provided");
        }
        $question = database::get("question", $this->param->questionid);
        if (empty($question)) {
            return $this->error("notfound");
        }
        $version = database::get("question_versions", $this->param->questionid, "questionid");
        if (empty($version)) {
            return $this->error("notfound", "no version");
        }
        $bank = database::get("question_bank_entries", $version->questionbankentryid);
        if (empty($bank) || $bank->questioncategoryid != $this->param->categoryid) {
            return $this->error("notfound", "wrong category");
        }
        if (! question_has_capability_on($question->id, 'edit')) {
            return $this->error("restricted", "User defined for the API is not allowed to update this question");
        }

        // Get the \local_ezxlate\entities\question for this question.
        $entity = new \local_ezxlate\entities\question($question);
        // Update it and build the answer.
        $this->end($entity);

        if (count(field::updated()) == 0) {
            return;   // We didn't change anything.
        }

        // Question definitions are cached (core/questiondata).
        \question_bank::notify_question_edited($question->id);

        // We change at least one field : we log an event.
        try {
            $event = \core\event\question_updated::create_from_question_instance(
                $question,
                $context,
                [ "categoryid" => $this->param->categoryid ]
            );
            $event->trigger();
        } catch (\Throwable $e) {
            // Logging is optional: the texts are already updated, the answer must not fail.
            debugging("local_ezxlate: event not logged: " . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Update a tag
     */
    protected function do_tag() {
        if (empty($this->param->id)) {
            return $this->error("error", "id must be provided");
        }
        // Check if tag management is allowed in plugin settings.
        if (get_config("local_ezxlate", "tags") == 0) {
                return $this->error("restricted", "tag management restricted in api parameters");
        }
        $tag = database::get("tag", $this->param->id);
        if (empty($tag)) {
            return $this->error("notfound", "tag not found");
        }
        // Check if user is allowed to manage tags.
        $context = \context_system::instance();
        if (! has_capability('moodle/tag:edit', $context)) {
            return $this->error("restricted", "User defined for the API is not allowed to update tags");
        }

        // Get the \local_ezxlate\entities\tag for this tag.
        $entity = new \local_ezxlate\entities\tag($tag);
        $this->end($entity);

        if (count(field::updated()) == 0) {
            return;   // We didn't change anything.
        }

        // We change at least one field : we log an event.
        try {
            $event = \core\event\tag_updated::create([
                    'context'  => $context,
                    'objectid' => $this->param->id,
                    'other'    => [
                        'rawname' => isset($this->param->data->rawname) ? $this->param->data->rawname : $tag->rawname,
                        'name' => isset($this->param->data->name) ? $this->param->data->name : $tag->name,
                    ],
                ]);
            $event->trigger();
        } catch (\Throwable $e) {
            // Logging is optional: the texts are already updated, the answer must not fail.
            debugging("local_ezxlate: event not logged: " . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
