<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English strings for the Export Quiz Questions block.
 *
 * @package    block_exportquizquestions
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['errorformat'] = 'The export format "{$a}" is not available on this site.';
$string['errorimport'] = 'The quiz questions could not be copied into the subcategory.';
$string['errornoquestions'] = 'This quiz has no questions to export.';
$string['errornoquestionsbulk'] = 'None of this course\'s quizzes have questions to export.';
$string['export'] = 'Export';
$string['exportquizquestions:addinstance'] = 'Add an Export Quiz Questions block';
$string['exportquizquestions:myaddinstance'] = 'Add an Export Quiz Questions block to the Dashboard';
$string['format'] = 'Format';
$string['mode'] = 'Download';
$string['mode_bulk'] = 'All quizzes in this course';
$string['mode_help'] = 'Questions are copied into "[shortname] | Quizzes" in the question bank, in a subcategory named after each quiz. Re-importing the exported file into another course recreates this structure automatically.';
$string['mode_single'] = 'A specific quiz';
$string['noquizzes'] = 'This course has no quizzes.';
$string['pluginname'] = 'Export Quiz Questions';
$string['privacy:metadata'] = 'The Export Quiz Questions block stores no personal data.';
$string['quizzes'] = 'Quiz';
$string['randommode'] = 'Random question slots';
$string['randommode_full'] = 'All questions from their source categories';
$string['randommode_help'] = '"All questions from their source categories" can be very large if a quiz draws from a big shared pool.';
$string['randommode_sample'] = 'Only as many questions as the quiz uses';
$string['randomwarning'] = '{$a} random question slots were found; every question in their source categories was included.';
$string['subcategoryhelp'] = 'Exporting rebuilds the subcategory first, so it always matches the quiz\'s current questions.';
