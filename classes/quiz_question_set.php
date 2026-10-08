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
     * A random slot has no single fixed question, so its whole source pool (every question its
     * filter would ever draw from: the category, including subcategories and tags if the filter
     * uses them) is included, using core's own random_question_loader to resolve that pool rather
     * than re-implementing category/tag filter matching.
     *
     * @param object $cm The quiz course module (cm_info or record).
     * @return array With 'questionids' (questionid => category id), 'categories' (count of distinct
     *               subcategories) and 'randomslots' (count of random question slots found).
     */
    public static function analyse(object $cm): array {
        global $DB;

        $quizcontextid = \context_module::instance($cm->id)->id;
        $questionids = [];
        $randomfilters = [];
        $randomslots = 0;
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

            $setrefs = $DB->get_records('question_set_references', [
                'usingcontextid' => $quizcontextid,
                'component' => 'mod_quiz',
                'questionarea' => 'slot',
                'itemid' => $slot->id,
            ]);
            foreach ($setrefs as $setref) {
                $randomslots++;
                $filter = self::normalise_filter($setref->filtercondition);
                if ($filter === null) {
                    continue;
                }
                // Several slots commonly share the same filter (e.g. "5 random questions from
                // category X"); key by the filter itself so that pool is only resolved once.
                $randomfilters[sha1(json_encode($filter))] = $filter;
            }
        }

        foreach ($randomfilters as $filter) {
            foreach (self::questions_for_filter($filter) as $questionid => $categoryid) {
                if (!isset($questionids[$questionid])) {
                    $questionids[$questionid] = $categoryid;
                }
            }
        }

        return [
            'questionids' => $questionids,
            'categories' => count(array_unique(array_values($questionids))),
            'randomslots' => $randomslots,
        ];
    }

    /**
     * Normalise a question_set_references.filtercondition value into its 'filter' array.
     *
     * Handles both the pre-4.3 flat format and the current structured format, the same way
     * \mod_quiz\question\bank\qbank_helper::get_question_structure() does.
     *
     * @param string|null $filtercondition The raw filtercondition column value.
     * @return array|null The normalised filter, or null if there is nothing usable to decode.
     */
    private static function normalise_filter(?string $filtercondition): ?array {
        if (!$filtercondition) {
            return null;
        }
        $decoded = json_decode($filtercondition, true);
        if (!is_array($decoded)) {
            return null;
        }
        $normalised = \core_question\question_reference_manager::convert_legacy_set_reference_filter_condition($decoded);
        return $normalised['filter'] ?? null;
    }

    /**
     * Resolve every question a random slot's filter could draw from.
     *
     * Drains \core_question\local\bank\random_question_loader completely instead of asking for
     * one random pick, so this returns the filter's whole pool (the entire source category or
     * categories, including subcategories and tags if the filter uses them), not a sample.
     *
     * @param array $filter A normalised filter, as returned by normalise_filter().
     * @return array questionid => category id.
     */
    private static function questions_for_filter(array $filter): array {
        global $CFG, $DB;

        // Qubaid_list is a legacy class, not autoloaded; engine/lib.php pulls in datalib.php
        // along with the other engine files it depends on, in the order they need.
        require_once($CFG->dirroot . '/question/engine/lib.php');

        $loader = new \core_question\local\bank\random_question_loader(new \qubaid_list([]));
        $found = [];
        while (($questionid = $loader->get_next_filtered_question_id($filter)) !== null) {
            $categoryid = $DB->get_field_sql(
                "SELECT qbe.questioncategoryid
                   FROM {question_versions} qv
                   JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  WHERE qv.questionid = :questionid",
                ['questionid' => $questionid]
            );
            if ($categoryid !== false) {
                $found[$questionid] = (int) $categoryid;
            }
        }
        return $found;
    }

    /**
     * Pick the version a quiz slot uses: the pinned version, or the latest non-draft one.
     *
     * Matches the COALESCE(qr.version, usableversion, anyversion) logic in core's
     * \mod_quiz\question\bank\qbank_helper::get_question_structure(): a question that is
     * 'hidden' (soft-deleted from the bank but still in use by this slot) is a usable
     * version, same as 'ready'. Only 'draft' versions are skipped, and only when a
     * non-draft version exists to prefer instead.
     *
     * @param \stdClass $ref The question reference row for the slot.
     * @param int $entryid The question bank entry id.
     * @return \stdClass|null The question version, or null if none exists.
     */
    private static function version_for(\stdClass $ref, int $entryid): ?\stdClass {
        global $DB;

        if ($ref->version !== null) {
            $version = $DB->get_record('question_versions',
                ['questionbankentryid' => $entryid, 'version' => $ref->version]);
            return $version ?: null;
        }
        $versions = $DB->get_records('question_versions', ['questionbankentryid' => $entryid], 'version DESC');
        if (!$versions) {
            return null;
        }
        foreach ($versions as $version) {
            if ($version->status !== question_version_status::QUESTION_STATUS_DRAFT) {
                return $version;
            }
        }
        return reset($versions);
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

        // Core's qformat_default::exportprocess() silently skips any question whose status is
        // 'hidden' (its own built-in assumption that only bank-visible questions get exported). A
        // slot can legitimately use a 'hidden' version (it is current for the quiz, just no
        // longer addable to new quizzes elsewhere), so force 'ready' on this copy: it is about
        // to become a fresh entry in the quiz's own subcategory, not a re-export of the original.
        $questions = [];
        foreach (array_keys($analysis['questionids']) as $questionid) {
            $questiondata = \question_bank::load_question_data($questionid);
            $questiondata->status = question_version_status::QUESTION_STATUS_READY;
            $questions[] = $questiondata;
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
