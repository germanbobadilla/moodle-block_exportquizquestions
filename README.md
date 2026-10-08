# Export Quiz Questions

**Export Quiz Questions** is a Moodle block that exports a quiz's questions in any
installed question format (Moodle XML, GIFT, Aiken, XHTML, and others), even when
those questions come from several different question bank subcategories.

## Description

The block is added inside a course. It lists the course's quizzes in a dropdown.
When you export a quiz:

1. The quiz's questions are copied into a subcategory named after the quiz, in the
   course's question bank. Questions are taken from whichever subcategories the quiz
   uses, so the copy works even when they are spread across several subcategories,
   banks, or contexts.
2. That subcategory is exported in the format you chose. The file contains every
   question in the subcategory, so it can be imported into another course with the
   regular import.

Each export rebuilds the subcategory, so it always matches the quiz's current questions.

Random question slots are not copied; the block shows a warning when a quiz has any.

Only users who can manage the question bank (editing teachers and managers) see the
block and can export.

## Requirements

* Moodle 4.0 or later (`$plugin->requires = 2022041900`). Tested on Moodle 4.5 LTS
  and Moodle 5.2: question categories live in the course context on 4.x and in a
  question bank activity on 5.x, and the block handles both.

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
3. Choose a quiz and a format, then select **Export**.

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
