# local_ezxlate — Text Extraction & Update API for Moodle

`local_ezxlate` is a Moodle local plugin that offers a secure API for extracting and updating all Moodle 
textual content — courses, sections, activities, tags, questions, and more.
Created for EzGlobe and designed to support full multilingual translation, the plugin integrates smoothly 
with external translation pipelines as well as automated content-processing systems, 
ensuring efficient and accurate localization of Moodle content.


## Features

- Extract text content from Moodle in a structured JSON format
- Update existing text fields (course names, activity titles, descriptions…)
- Handle questions and tags
- Restrict access via:
  - API key
  - IP filtering
  - Moodle capability (`local/ezxlate:use`) and capabilities of the user dedicated to API
  - Optional per-entity restrictions (courses, question types, tags…), with user capabilities
- No personal data stored (privacy: null_provider)
- API-first design, no UI for end users

## License

This plugin is distributed under the terms of the GNU General Public License,
version 3 or later.

See the [LICENSE](LICENSE) file for details.

## Requirements

- Moodle 4.1 to Moodle 5.3
- PHP version required by your Moodle version
- A dedicated Moodle user account to run API actions

## Installation

1. Copy the plugin into `local/ezxlate`. Since Moodle 5.1 the web root has moved to a `public`
   directory, so the plugin belongs inside `public/local/ezxlate`.
2. Visit **Site administration → Notifications**
3. Configure the plugin under  
   **Site administration → Plugins → Local plugins → ezxlate**

## Configuration

The plugin requires at least:

- **Enable API**
- **API key**
- **Execution user**  (among users with `local/ezxlate:use` capability)
- Optional restrictions:
  - Explicit list of authorized courses, or restriction of sensitive courses
  - Explicit list of IP addresses allowed to use the API
  - Allowed question context level (system, course categories, course questions banks)
  - Tags management allowed or restricted
- Optional features
  - Updating gradebook items names when updating activity name
  - Extending fields maximum size in the database schema if needed (for texts with multilingual tags),
    MySQL and MariaDB only, never for indexed columns
  - Checking the previous value before changing a text
   
A complete configuration and usage guide is provided in the plugin wiki.

## API Endpoints

- `local/ezxlate/get.php`
- `local/ezxlate/set.php`
- `local/ezxlate/infos.php`

Full API documentation is provided in the plugin wiki.

## Permissions

- `local/ezxlate:use` and update capability on managed texts

## Privacy

Implements the null provider. No personal data stored.

## Notes and limitations

### Question banks

Since Moodle 5.0, question banks are `mod_qbank` activities: the upgrade moves every question
category that used to live at the site, course category or course level into such an activity.
The API hides this change, and the `questioncategories` action keeps reporting the original
level in its `context` value, so the "Questions management" setting keeps its meaning. Be aware
that the shared banks are now hosted by other courses: the former site bank by the front page,
and a former course category bank by a course created inside that category.

Question banks are activities, but they are not course content: they are left out of the module
list of a section, and their questions are exported by the `questioncategories` and `questions`
actions.

On Moodle 4.1 to 4.5, the question categories owned by an activity, typically the questions of a
quiz, are now exported as well. They were previously left out.

### Multilingual tags

Most of the exported texts are rendered by Moodle through its filters, so multilingual tags work
as expected. One exception: in a database activity (`mod_data`), the description of a field is
displayed without ever applying `format_string()`, so multilingual tags would be shown as is to
the learner. That field can only be translated by replacement, and the translation chain should
leave `fields.description` out when it works with tags.

### Feedback activities

The texts of a feedback activity are exported under the `items` key, which holds the question
text (`name`), the short label used in the analysis and the export (`label`), and, for the label
items only, their HTML content (`presentation`). The options of the multiple choice items are
not exported: their text is also stored in the `dependvalue` of the items that depend on them,
so translating one without the other would silently break the conditional display.

### Tags

Moodle stores a tag twice: `rawname` is the name displayed, `name` is its lowercase form, used to
find the tag and unique within its collection. The API writes each field as it is sent and never
derives one from the other: to rename a tag, send both, with `name` set to the lowercase of
`rawname`. A name already used by another tag of the same collection is refused by the database.
Tag names are displayed without filters, so multilingual tags can't be used in them.

## Changelog

See CHANGELOG.md for details.

## Credits

Maintainers:
- Christophe Blanchot, for EzGlobe

## Copyright

© 2025 EzGlobe, France
