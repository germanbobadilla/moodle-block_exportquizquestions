# Changelog

All notable changes to this plugin are documented here.

## 1.2.0 - 2026-10-10

* Restructured where subcategories are created: every course now gets one
  `[shortname] | Quizzes` category (no questions of its own) with one subcategory per
  quiz directly underneath, instead of each quiz's subcategory sitting loose under the
  question bank's top category.
* Added a "download" choice to the export form: a specific quiz (as before), or every
  quiz in the course at once. A bulk export rebuilds every quiz's subcategory, skipping
  any quiz with nothing to export rather than aborting the whole run, then exports all
  of them together in one file.
* Exports (single or bulk) now embed category structure in the file. Importing the file
  into another course, even one with no existing question bank structure, recreates the
  `[shortname] | Quizzes` category and each quiz's subcategory, with every question back
  in the right one.
* Verified against a real 23-quiz course (Moodle 5.3): all 23 subcategories built
  correctly under the shared parent, and a full export/reimport round trip into a brand
  new course recreated the entire structure with matching question counts. Verified
  the same way on Moodle 4.5 LTS with a synthetic multi-quiz course.

## 1.1.0 - 2026-10-08

* Added a choice to the export form for how random question slots are handled: "Only as
  many questions as the quiz uses" (one representative question per random slot, the new
  default) or "All questions from their source categories" (the previous 1.0.2 behaviour,
  which can be very large if the source category is a big shared pool).
* Verified on Moodle 5.3 LTS (2026-10-10): no code changes were needed. Confirmed via a
  full uninstall and reinstall through Moodle's own plugin manager (not just continued
  operation after an in-place core upgrade), plus the block's rendering and a full HTTP
  export round trip, all against real course data.
* "Only as many" uses the same random question loader as before, but asks for one pick per
  slot instead of draining the whole pool; slots that share an identical filter each still
  get a distinct question.
* Verified both modes against the real quiz with 5 random slots on Moodle 5.2 (sample mode:
  5 questions; full mode: unchanged at 2,261) and against a synthetic 3-random-slot quiz
  sharing an 8-question pool on Moodle 4.5 LTS (sample mode: 3; full mode: 8).

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
