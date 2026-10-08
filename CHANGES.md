# Changelog

All notable changes to this plugin are documented here.

## 1.0.2 - 2026-10-08

* Random question slots are no longer skipped. Every question in the slot's source
  category (including subcategories and tags, if the filter uses them) is now included
  in the copy, resolved using core's own random question loader rather than
  re-implementing the filter matching. Several slots sharing the same filter (e.g.
  "5 random questions from category X") only resolve that pool once.
* Verified against a real quiz with 5 random slots pulling from a shared category and
  subcategory on Moodle 5.2, and against a synthetic random slot (added the same way
  Moodle's own "Add random questions" feature does) on Moodle 4.5 LTS.

## 1.0.1 - 2026-10-08

* Fixed quizzes whose slots use a "hidden" question version (current for the quiz, but
  no longer addable to new quizzes from the bank) being reported as having no questions
  to export. The quiz engine treats a hidden version as usable as long as it isn't a
  draft; the plugin now matches that rule instead of requiring "ready".
* Fixed the copy step for such questions: core's own XML export skips "hidden"
  questions outright, so the plugin now resets the status on its internal copy before
  exporting it into the quiz's subcategory, where it becomes a normal, fully usable entry.

## 1.0.0 - 2026-10-08

* Initial release.
* Lists the course's quizzes in a dropdown with a format dropdown and an Export button.
* Copies each quiz's questions into a subcategory named after the quiz, in the course's
  question bank, then exports that subcategory in the chosen format.
* Questions can come from any subcategory, bank, or context the quiz uses.
* Random question slots are reported and not copied.
* Verified on both Moodle 4.5 LTS (question categories in the course context) and
  Moodle 5.2 (question categories in a question bank activity), using real multi-category
  quizzes on each.
