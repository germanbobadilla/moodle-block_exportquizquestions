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
 * Exports a quiz's questions, copied into its subcategory, in the chosen format.
 *
 * @package    block_exportquizquestions
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/questionlib.php');

$cmid = required_param('cmid', PARAM_INT);
$format = required_param('format', PARAM_ALPHANUMEXT);
require_sesskey();

$cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, false, $cm);
$coursecontext = context_course::instance($course->id);
require_capability('moodle/question:managecategory', $coursecontext);

$formats = get_import_export_formats('export');
if (!array_key_exists($format, $formats)) {
    throw new moodle_exception('errorformat', 'block_exportquizquestions', '', $format);
}

$subcategory = \block_exportquizquestions\quiz_question_set::build_subcategory($cm, $course);

require_once($CFG->dirroot . "/question/format/{$format}/format.php");
$classname = 'qformat_' . $format;
$qformat = new $classname();
$qformat->setContexts(new \core_question\local\bank\question_edit_contexts($coursecontext));
$qformat->setCourse($course);
$qformat->setCategory($subcategory);
$qformat->setCattofile(false);
$qformat->setContexttofile(false);

if (!$qformat->exportpreprocess()) {
    send_file_not_found();
}
$content = $qformat->exportprocess(true);
if (!$content) {
    send_file_not_found();
}

$filename = clean_filename($cm->name) . $qformat->export_file_extension();
send_file($content, $filename, 0, 0, true, true, $qformat->mime_type());
