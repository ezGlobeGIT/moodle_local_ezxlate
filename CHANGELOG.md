# Changelog — local_ezxlate

## 1.1 — 2026-10-10
- Moodle 5.0 to 5.3 support: question banks as `mod_qbank` activities (the "Questions management"
  setting keeps its meaning, categories not transferred yet by the upgrade included), feedback items,
  subsections and question banks left out of sections
- Security: strict comparison of the API key, ids sent as arrays or objects rejected without PHP error
- IP filtering accepts subnets and ranges, and takes the reverse proxy settings into account
- Gradebook item names updated by the activity itself (as its settings form does), for every activity;
  caches purged after updates
- `course` action of `get.php` works with the shortname only
- The name of a section delegated to a subsection is also given to the subsection activity
- Previous value verification enforced whenever the settings require it
- Update errors detailed down to the field inside lists (chapters, entries, items...), instead of
  a bare `partial` for the whole list
- Questions management disabled by default (as documented)
- Fields extension keeps the column constraints, refuses indexed columns, MySQL family only,
  recycle bin columns extended only along with an activity or course name
- Fixed exports: data fields (description only, the name is displayed without filters), stickynotes
  columns, unilabel; the JSON `extradata` of questionnaire questions is no longer exported
- Moodle coding style

## 1.0 — 2025-12-13
- Initial public release
