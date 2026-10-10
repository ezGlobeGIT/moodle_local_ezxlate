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
 * Class to manage the entity "feedback_item"
 *
 * @package    local_ezxlate
 * @copyright  2025 EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezxlate\entities;

/**
 * Class to manage an item of a feedback activity
 *
 * See local_ezxlate\entity and local_ezxlate\tree_interface
 *
 * @package    local_ezxlate
 * @copyright  2025 EzGlobe
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_item extends \local_ezxlate\entity {
    /** @var string $maintable main table of the entity */
    protected $maintable = "feedback_item";

    /**
     * Define the managed fields and sub-entities of this entity
     */
    protected function define_fields() {
        $this->add_fields("name", "label");
        // Field "presentation" only holds text for label items (the HTML of the label itself).
        // For the other types it holds sizes, ranges or flags, and for multichoice it holds
        // the options wrapped in structural separators, whose text is also referenced by the
        // "dependvalue" of the conditional items : not safe to translate as a whole.
        if ($this->record("typ") == "label") {
            $this->add_field("presentation");
        }
    }
}
