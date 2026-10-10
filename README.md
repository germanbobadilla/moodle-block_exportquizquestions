# Export Quiz Questions

**Export Quiz Questions** is a Moodle block that exports a quiz's questions in any
installed question format (Moodle XML, GIFT, Aiken, XHTML, and others), even when
those questions come from several different question bank subcategories.

## Description

The block is added inside a course. It lists the course's quizzes in a dropdown, and
lets you export one quiz or every quiz in the course at once.

Every course gets one `[shortname] | Quizzes` category in the question bank (it holds
no questions itself) with one subcategory per quiz, named after the quiz, directly
underneath. When you export:

1. Each quiz's subcategory is rebuilt: emptied, then refilled with the questions that
   quiz actually uses, taken from whichever subcategories, banks, or contexts the quiz
   draws from.
2. The export is in the format you chose, with the category structure embedded in the
   file. Importing it into another course (even one with no existing question bank
   structure) recreates the same `[shortname] | Quizzes` category and per-quiz
   subcategories, with each question back in the right one.

Choosing **a specific quiz** exports just that quiz's subcategory. Choosing **all
quizzes** rebuilds every quiz's subcategory and exports them together in one file.

Random question slots have no single fixed question, so the export form also lets you
choose how they're handled: copy only as many questions as the quiz actually uses (one
representative question per random slot, the default), or copy every question in the
slot's source category (including subcategories and tags, if the filter uses them).

Only users who can manage the question bank (editing teachers and managers) see the
block and can export.

## Requirements

* Moodle 4.0 or later (`$plugin->requires = 2022041900`). Tested on Moodle 4.5 LTS,
  5.2, and 5.3 LTS: question categories live in the course context on 4.x and in a
  question bank activity on 5.x, and the block handles both. Verified on 5.3 via a
  full uninstall/reinstall through Moodle's own plugin manager, not just continued
  operation after a core upgrade.

## Installation

Install the plugin like any other Moodle block plugin, in `blocks/exportquizquestions`:

```sh
git clone https://github.com/germanbobadilla/moodle-block_exportquizquestions.git blocks/exportquizquestions
```

Then log in as an admin and visit *Site administration > Notifications*, or run:

```sh
php admin/cli/upgrade.php
```

## Usage

1. Open a course and turn editing on.
2. Add the **Export Quiz Questions** block.
3. Choose **a specific quiz** or **all quizzes in this course**, and a format. If any
   quiz involved has random question slots, choose whether to export only as many
   questions as the quiz uses or every question in their source categories, then
   select **Export**.

## Capabilities

| Capability | Description |
| --- | --- |
| `block/exportquizquestions:addinstance` | Add the block to a course (editing teacher, manager). |
| `block/exportquizquestions:myaddinstance` | Add the block to the Dashboard. |

## Support

Please use the [GitHub issue tracker](https://github.com/germanbobadilla/moodle-block_exportquizquestions/issues)
to report bugs or request features.

## License

This program is free software: you can redistribute it and/or modify it under the
terms of the GNU General Public License as published by the Free Software Foundation,
either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the [GNU General Public License](https://www.gnu.org/licenses/gpl-3.0.html)
for more details.
