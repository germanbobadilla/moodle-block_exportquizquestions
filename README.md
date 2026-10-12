# Export Quiz Questions

**Export Quiz Questions** is a Moodle block that exports a quiz's questions — or every
quiz's questions in a course at once — in any installed question format (Moodle XML,
GIFT, Aiken, XHTML, and others), preserving question bank structure so a re-import
recreates it automatically.

📖 **[Full documentation](https://germanbobadilla.github.io/moodle-block_exportquizquestions/)**
— installation, usage, and the changelog.

## Quick install

```sh
git clone https://github.com/germanbobadilla/moodle-block_exportquizquestions.git blocks/exportquizquestions
```

Then log in as an admin and visit *Site administration > Notifications*, or run
`php admin/cli/upgrade.php`.

Requires Moodle 4.0 or later. Verified on Moodle 4.5 LTS, 5.2, and 5.3 LTS.

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
