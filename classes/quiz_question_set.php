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
 * Finds a quiz's questions and copies them into a subcategory named after the quiz.
 *
 * @package    block_exportquizquestions
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_exportquizquestions;

use core_question\local\bank\question_version_status;

/**
 * Finds a quiz's questions and copies them into a subcategory named after the quiz.
 *
 * The copy is made by exporting the questions to Moodle XML and importing that file into the
 * subcategory, so the result is the same as using the regular import.
 *
 * @package    block_exportquizquestions
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class quiz_question_set {
    /**
     * Summarise the questions a quiz uses, without changing anything.
     *
     * @param object $cm The quiz course module (cm_info or record).
     * @return array With 'questionids' (questionid => category id), 'categories' (count of distinct
     *               subcategories) and 'randomslots' (count of random question slots, which are not copied).
     */
    public static function analyse(object $cm): array {
        global $DB;

        $quizcontextid = \context_module::instance($cm->id)->id;
        $questionids = [];
        $slots = $DB->get_records('quiz_slots', ['quizid' => $cm->instance], 'slot ASC', 'id');
        foreach ($slots as $slot) {
            $refs = $DB->get_records('question_references', [
                'usingcontextid' => $quizcontextid,
                'component' => 'mod_quiz',
                'questionarea' => 'slot',
                'itemid' => $slot->id,
            ]);
            foreach ($refs as $ref) {
                $entry = $DB->get_record('question_bank_entries', ['id' => $ref->questionbankentryid]);
                if (!$entry) {
                    continue;
                }
                $version = self::version_for($ref, (int) $entry->id);
                if ($version) {
                    $questionids[(int) $version->questionid] = (int) $entry->questioncategoryid;
                }
            }
        }

        $randomslots = $DB->count_records_sql(
            "SELECT COUNT(*)
               FROM {question_set_references} qsr
               JOIN {quiz_slots} s ON s.id = qsr.itemid
              WHERE qsr.usingcontextid = :contextid
                AND qsr.component = 'mod_quiz'
                AND qsr.questionarea = 'slot'
                AND s.quizid = :quizid",
            ['contextid' => $quizcontextid, 'quizid' => $cm->instance]
        );

        return [
            'questionids' => $questionids,
            'categories' => count(array_unique(array_values($questionids))),
            'randomslots' => (int) $randomslots,
        ];
    }

    /**
     * Pick the version a quiz slot uses: the pinned version, or the latest ready one.
     *
     * @param \stdClass $ref The question reference row for the slot.
     * @param int $entryid The question bank entry id.
     * @return \stdClass|null The question version, or null if none is usable.
     */
    private static function version_for(\stdClass $ref, int $entryid): ?\stdClass {
        global $DB;

        if ($ref->version !== null) {
            $version = $DB->get_record('question_versions',
                ['questionbankentryid' => $entryid, 'version' => $ref->version]);
            return $version ?: null;
        }
        $versions = $DB->get_records('question_versions', ['questionbankentryid' => $entryid], 'version DESC');
        foreach ($versions as $version) {
            if ($version->status === question_version_status::QUESTION_STATUS_READY) {
                return $version;
            }
        }
        return null;
    }

    /**
     * Create or refresh the quiz's subcategory and copy the quiz's questions into it.
     *
     * @param object $cm The quiz course module (cm_info or record).
     * @param \stdClass $course The course the quiz belongs to.
     * @return \stdClass The subcategory, with the copied questions in it.
     * @throws \moodle_exception If there are no questions to copy, or the copy fails.
     */
    public static function build_subcategory(object $cm, \stdClass $course): \stdClass {
        global $CFG, $DB;

        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/question/engine/bank.php');
        require_once($CFG->dirroot . '/question/format/xml/format.php');

        $analysis = self::analyse($cm);
        if (empty($analysis['questionids'])) {
            throw new \moodle_exception('errornoquestions', 'block_exportquizquestions');
        }

        $coursecontext = \context_course::instance($course->id);
        if (class_exists('\core_question\local\bank\question_bank_helper')) {
            // Moodle 5.0 and later keep question categories in question bank activities.
            $bank = \core_question\local\bank\question_bank_helper::get_default_open_instance_system_type($course, true);
            $bankcontext = \context_module::instance($bank->id);
        } else {
            // Moodle 4.x keeps question categories in the course context.
            $bankcontext = $coursecontext;
        }
        $top = question_get_top_category($bankcontext->id, true);
        $subcategory = self::find_or_create_subcategory($cm->name, $bankcontext->id, (int) $top->id);
        self::empty_subcategory($subcategory);

        $questions = [];
        foreach (array_keys($analysis['questionids']) as $questionid) {
            $questions[] = \question_bank::load_question_data($questionid);
        }

        $xmlformat = new \qformat_xml();
        $xmlformat->setQuestions($questions);
        $xmlformat->setCourse($course);
        $xmlformat->setCattofile(false);
        $xmlformat->setContexttofile(false);
        $xmlformat->setContexts(new \core_question\local\bank\question_edit_contexts($coursecontext));
        $xml = $xmlformat->exportprocess(false);
        if (!$xml) {
            throw new \moodle_exception('errorimport', 'block_exportquizquestions');
        }

        $dir = make_temp_directory('block_exportquizquestions');
        $path = $dir . '/' . uniqid('quizset', true) . '.xml';
        file_put_contents($path, $xml);

        try {
            $importer = new \qformat_xml();
            $importer->setCategory($subcategory);
            $importer->setContexts(new \core_question\local\bank\question_edit_contexts($coursecontext));
            $importer->setCourse($course);
            $importer->setFilename($path);
            $importer->setRealfilename(basename($path));
            $importer->setCatfromfile(false);
            $importer->setContextfromfile(false);
            $importer->setStoponerror(true);
            $importer->setMatchgrades('error');
            $importer->set_display_progress(false);
            if (!$importer->importpreprocess() || !$importer->importprocess()) {
                throw new \moodle_exception('errorimport', 'block_exportquizquestions');
            }
            $importer->importpostprocess();
        } finally {
            @unlink($path);
        }

        return $DB->get_record('question_categories', ['id' => $subcategory->id], '*', MUST_EXIST);
    }

    /**
     * Find the quiz's subcategory under the course top category, or create it.
     *
     * @param string $name The quiz name, used as the subcategory name.
     * @param int $contextid The question bank's module context id.
     * @param int $parentid The top category id.
     * @return \stdClass
     */
    private static function find_or_create_subcategory(string $name, int $contextid, int $parentid): \stdClass {
        global $DB;

        $name = shorten_text($name, 250, true);
        $existing = $DB->get_record('question_categories',
            ['contextid' => $contextid, 'parent' => $parentid, 'name' => $name]);
        if ($existing) {
            return $existing;
        }

        $category = new \stdClass();
        $category->name = $name;
        $category->contextid = $contextid;
        $category->info = '';
        $category->infoformat = FORMAT_MOODLE;
        $category->stamp = make_unique_id_code();
        $category->parent = $parentid;
        $category->sortorder = 999;
        $category->idnumber = null;
        $category->id = $DB->insert_record('question_categories', $category);
        return $category;
    }

    /**
     * Delete the questions in a subcategory so it can be refilled.
     *
     * @param \stdClass $category The subcategory.
     * @return void
     */
    private static function empty_subcategory(\stdClass $category): void {
        global $DB;

        $entries = $DB->get_records('question_bank_entries', ['questioncategoryid' => $category->id], '', 'id');
        foreach ($entries as $entry) {
            $versions = $DB->get_records('question_versions', ['questionbankentryid' => $entry->id], '', 'questionid');
            foreach ($versions as $version) {
                question_delete_question((int) $version->questionid);
            }
        }
    }
}
