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
     * A random slot has no single fixed question. With $expandrandom true, its whole source pool
     * (every question its filter would ever draw from: the category, including subcategories and
     * tags if the filter uses them) is included, using core's own random_question_loader to
     * resolve that pool rather than re-implementing category/tag filter matching. With
     * $expandrandom false, each random slot instead contributes one representative question
     * (distinct per slot, even when several slots share the same filter), matching how many
     * questions the quiz itself actually uses rather than its whole source category.
     *
     * @param object $cm The quiz course module (cm_info or record).
     * @param bool $expandrandom Whether to include every question a random slot could draw from
     *             (true), or just one representative question per random slot (false).
     * @return array With 'questionids' (questionid => category id), 'categories' (count of distinct
     *               subcategories) and 'randomslots' (count of random question slots found).
     */
    public static function analyse(object $cm, bool $expandrandom = true): array {
        global $DB;

        $quizcontextid = \context_module::instance($cm->id)->id;
        $questionids = [];
        $randomfilters = [];
        $slotfilters = [];
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
                if ($expandrandom) {
                    // Several slots commonly share the same filter (e.g. "5 random questions from
                    // category X"); key by the filter itself so that pool is only resolved once.
                    $randomfilters[sha1(json_encode($filter))] = $filter;
                } else {
                    $slotfilters[] = $filter;
                }
            }
        }

        if ($expandrandom) {
            foreach ($randomfilters as $filter) {
                foreach (self::questions_for_filter($filter) as $questionid => $categoryid) {
                    if (!isset($questionids[$questionid])) {
                        $questionids[$questionid] = $categoryid;
                    }
                }
            }
        } else if ($slotfilters) {
            // One shared loader, so slots with an identical filter still each get a distinct
            // question (the loader never returns the same question twice in its lifetime).
            $loader = self::make_random_loader();
            foreach ($slotfilters as $filter) {
                $questionid = $loader->get_next_filtered_question_id($filter);
                if ($questionid !== null && !isset($questionids[$questionid])) {
                    $categoryid = self::category_for_question($questionid);
                    if ($categoryid !== null) {
                        $questionids[$questionid] = $categoryid;
                    }
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
     * Drains a fresh random_question_loader completely instead of asking for one random pick, so
     * this returns the filter's whole pool (the entire source category or categories, including
     * subcategories and tags if the filter uses them), not a sample.
     *
     * @param array $filter A normalised filter, as returned by normalise_filter().
     * @return array questionid => category id.
     */
    private static function questions_for_filter(array $filter): array {
        $loader = self::make_random_loader();
        $found = [];
        while (($questionid = $loader->get_next_filtered_question_id($filter)) !== null) {
            $categoryid = self::category_for_question($questionid);
            if ($categoryid !== null) {
                $found[$questionid] = $categoryid;
            }
        }
        return $found;
    }

    /**
     * Build a random_question_loader that does not exclude any question based on prior usage.
     *
     * @return \core_question\local\bank\random_question_loader
     */
    private static function make_random_loader(): \core_question\local\bank\random_question_loader {
        global $CFG;

        // Qubaid_list is a legacy class, not autoloaded; engine/lib.php pulls in datalib.php
        // along with the other engine files it depends on, in the order they need.
        require_once($CFG->dirroot . '/question/engine/lib.php');

        return new \core_question\local\bank\random_question_loader(new \qubaid_list([]));
    }

    /**
     * Look up the category a question currently belongs to.
     *
     * @param int $questionid The question id.
     * @return int|null The question category id, or null if it could not be resolved.
     */
    private static function category_for_question(int $questionid): ?int {
        global $DB;

        $categoryid = $DB->get_field_sql(
            "SELECT qbe.questioncategoryid
               FROM {question_versions} qv
               JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
              WHERE qv.questionid = :questionid",
            ['questionid' => $questionid]
        );
        return $categoryid === false ? null : (int) $categoryid;
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
     * The subcategory is created under the course's "[shortname] | Quizzes" category (created if
     * needed), not directly under the question bank's top category, so every quiz in a course
     * groups under one shared, questionless parent. Pass $parentcategory when it has already been
     * resolved (as build_all_subcategories() does), to avoid resolving it again per quiz.
     *
     * @param object $cm The quiz course module (cm_info or record).
     * @param \stdClass $course The course the quiz belongs to.
     * @param bool $expandrandom Whether random slots contribute their whole source category
     *             (true), or just one representative question per slot (false). See analyse().
     * @param \stdClass|null $parentcategory The course's "[shortname] | Quizzes" category, if
     *             already resolved. Resolved automatically when not given.
     * @return \stdClass The subcategory, with the copied questions in it.
     * @throws \moodle_exception If there are no questions to copy, or the copy fails.
     */
    public static function build_subcategory(
        object $cm,
        \stdClass $course,
        bool $expandrandom = true,
        ?\stdClass $parentcategory = null
    ): \stdClass {
        global $CFG, $DB;

        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/question/engine/bank.php');
        require_once($CFG->dirroot . '/question/format/xml/format.php');

        $analysis = self::analyse($cm, $expandrandom);
        if (empty($analysis['questionids'])) {
            throw new \moodle_exception('errornoquestions', 'block_exportquizquestions');
        }

        $coursecontext = \context_course::instance($course->id);
        $parentcategory ??= self::find_or_create_course_quizzes_category($course);
        $subcategory = self::find_or_create_subcategory(
            $cm->name, (int) $parentcategory->contextid, (int) $parentcategory->id);
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
     * Rebuild every quiz's subcategory in the course, under one shared "[shortname] | Quizzes"
     * category.
     *
     * A quiz with no exportable questions (see analyse()) is skipped rather than aborting the
     * whole run, so one empty or broken quiz does not block the rest of the course.
     *
     * @param \stdClass $course The course to build every quiz's subcategory for.
     * @param bool $expandrandom Whether random slots contribute their whole source category
     *             (true), or just one representative question per slot (false). See analyse().
     * @return array With 'category' (the course's "[shortname] | Quizzes" category), 'built'
     *               (names of quizzes successfully copied) and 'skipped' (names of quizzes with
     *               nothing to copy).
     * @throws \moodle_exception If no quiz in the course had anything to copy.
     */
    public static function build_all_subcategories(\stdClass $course, bool $expandrandom = true): array {
        $parentcategory = self::find_or_create_course_quizzes_category($course);

        $built = [];
        $skipped = [];
        $modinfo = get_fast_modinfo($course);
        foreach ($modinfo->get_instances_of('quiz') as $cm) {
            try {
                self::build_subcategory($cm, $course, $expandrandom, $parentcategory);
                $built[] = $cm->get_formatted_name();
            } catch (\moodle_exception $e) {
                $skipped[] = $cm->get_formatted_name();
            }
        }

        if (!$built) {
            throw new \moodle_exception('errornoquestionsbulk', 'block_exportquizquestions');
        }

        return ['category' => $parentcategory, 'built' => $built, 'skipped' => $skipped];
    }

    /**
     * Find the course's "[shortname] | Quizzes" category under the question bank's top category,
     * or create it. This category holds no questions of its own; it only groups each quiz's
     * subcategory together.
     *
     * @param \stdClass $course The course.
     * @return \stdClass
     */
    private static function find_or_create_course_quizzes_category(\stdClass $course): \stdClass {
        global $CFG;

        require_once($CFG->libdir . '/questionlib.php');

        $bankcontext = self::resolve_bank_context($course);
        $top = question_get_top_category($bankcontext->id, true);
        $name = $course->shortname . ' | Quizzes';
        return self::find_or_create_subcategory($name, $bankcontext->id, (int) $top->id);
    }

    /**
     * Resolve the context a course's question categories live in.
     *
     * @param \stdClass $course The course.
     * @return \context
     */
    private static function resolve_bank_context(\stdClass $course): \context {
        if (class_exists('\core_question\local\bank\question_bank_helper')) {
            // Moodle 5.0 and later keep question categories in question bank activities.
            $bank = \core_question\local\bank\question_bank_helper::get_default_open_instance_system_type($course, true);
            return \context_module::instance($bank->id);
        }
        // Moodle 4.x keeps question categories in the course context.
        return \context_course::instance($course->id);
    }

    /**
     * Find the quiz's subcategory under its parent category, or create it.
     *
     * @param string $name The subcategory name.
     * @param int $contextid The question bank's module context id.
     * @param int $parentid The parent category id.
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
