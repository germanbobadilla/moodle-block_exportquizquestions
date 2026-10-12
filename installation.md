---
layout: page
title: Installation
---

## Requirements

Moodle 4.0 or later (`$plugin->requires = 2022041900`). Verified on Moodle 4.5 LTS,
5.2, and 5.3 LTS: question categories live in the course context on 4.x and in a
question bank activity on 5.x, and the block handles both automatically.

## Install

Clone the plugin straight into `blocks/exportquizquestions`:

```sh
git clone https://github.com/germanbobadilla/moodle-block_exportquizquestions.git blocks/exportquizquestions
```

Then log in as an admin and visit **Site administration > Notifications**, or run:

```sh
php admin/cli/upgrade.php
```

## Adding the block

1. Open a course and turn editing on.
2. Add the **Export Quiz Questions** block.

See [Usage](usage.html) for how to export once it's added.

## Capabilities

| Capability | Description |
| --- | --- |
| `block/exportquizquestions:addinstance` | Add the block to a course (editing teacher, manager). |
| `block/exportquizquestions:myaddinstance` | Add the block to the Dashboard. |

Only users who can manage the question bank (`moodle/question:managecategory`) see the
block and can export.

## Support

Report bugs or request features on the
[GitHub issue tracker](https://github.com/germanbobadilla/moodle-block_exportquizquestions/issues).

## License

GNU GPL v3 or later. See the
[full license text](https://www.gnu.org/licenses/gpl-3.0.html).
