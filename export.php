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
 * Exports one quiz's questions, or every quiz's questions in a course, in the chosen format.
 *
 * @package    block_exportquizquestions
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/questionlib.php');

$mode = optional_param('mode', 'single', PARAM_ALPHA);
$format = required_param('format', PARAM_ALPHANUMEXT);
$randommode = optional_param('randommode', 'sample', PARAM_ALPHA);
require_sesskey();

if ($mode === 'bulk') {
    $courseid = required_param('courseid', PARAM_INT);
    $course = get_course($courseid);
    require_login($course);
    $coursecontext = context_course::instance($course->id);
    require_capability('moodle/question:managecategory', $coursecontext);

    $formats = get_import_export_formats('export');
    if (!array_key_exists($format, $formats)) {
        throw new moodle_exception('errorformat', 'block_exportquizquestions', '', $format);
    }

    $result = \block_exportquizquestions\quiz_question_set::build_all_subcategories($course, $randommode === 'full');
    $exportcategory = $result['category'];
    $filenamebase = $course->shortname . ' - Quizzes';
} else {
    $cmid = required_param('cmid', PARAM_INT);
    $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
    $course = get_course($cm->course);
    require_login($course, false, $cm);
    $coursecontext = context_course::instance($course->id);
    require_capability('moodle/question:managecategory', $coursecontext);

    $formats = get_import_export_formats('export');
    if (!array_key_exists($format, $formats)) {
        throw new moodle_exception('errorformat', 'block_exportquizquestions', '', $format);
    }

    $exportcategory = \block_exportquizquestions\quiz_question_set::build_subcategory($cm, $course, $randommode === 'full');
    $filenamebase = $cm->name;
}

require_once($CFG->dirroot . "/question/format/{$format}/format.php");
$classname = 'qformat_' . $format;
$qformat = new $classname();
// On Moodle 5.x the exported category lives in a question bank activity's own module context,
// not the course context, so question_edit_contexts must be built from that context (its parent
// chain still includes the course context above it) or core's category-path building cannot
// resolve it. See quiz_question_set::resolve_bank_context().
//
// setContexts() must get a plain array, not the question_edit_contexts object itself: every real
// core caller (question/bank/exporttoxml/exportone.php and friends) passes an array, because
// context_to_string_translator's constructor does a bare foreach over whatever it is given, and
// question_edit_contexts has no Iterator/IteratorAggregate implementation and only a *protected*
// $allcontexts property. Passing the object directly silently iterates zero times, every category
// lookup comes back undefined, and core emits a PHP warning per category instead of a fatal error
// (so the export still "succeeds" with malformed category paths) - which is what broke this
// plugin's exports wherever warnings are displayed to the page.
$bankcontext = \block_exportquizquestions\quiz_question_set::resolve_bank_context($course);
$qformat->setContexts((new \core_question\local\bank\question_edit_contexts($bankcontext))->all());
$qformat->setCourse($course);
$qformat->setCategory($exportcategory);
// Bulk exports embed category markers, so re-importing the file (even into a course with no
// existing question bank structure) recreates the "[shortname] | Quizzes" / quiz-name tree.
// A single quiz's export leaves them out: Moodle's own import screen defaults "Get category
// from file" to checked, which would otherwise silently redirect the import away from
// whatever category someone picks, even though they are just re-importing one quiz's worth
// of questions, not rebuilding a whole course's structure.
$qformat->setCattofile($mode === 'bulk');
$qformat->setContexttofile(false);

// Core's own category-path building (used when cattofile is on) can emit PHP warnings on
// messy real-world data, e.g. a category whose context no longer matches any course it
// belongs to. PHP keeps going after a warning, so the export itself still completes, but a
// warning printed straight to the page before send_file() can set its headers breaks the
// download outright: the browser gets the raw warning text and file content inline instead
// of a file. Buffering here means a stray warning can never corrupt the actual HTTP response.
ob_start();
$preprocessok = $qformat->exportpreprocess();
$content = $preprocessok ? $qformat->exportprocess(true) : false;
$straywarnings = ob_get_clean();
if ($straywarnings !== '') {
    debugging('block_exportquizquestions: discarded unexpected output during export: ' . $straywarnings,
        DEBUG_DEVELOPER);
}

if (!$preprocessok || !$content) {
    send_file_not_found();
}

$filename = clean_filename($filenamebase) . $qformat->export_file_extension();
send_file($content, $filename, 0, 0, true, true, $qformat->mime_type());
