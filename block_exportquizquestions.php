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
 * Export Quiz Questions block: lists the course's quizzes and exports each one's questions.
 *
 * @package    block_exportquizquestions
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_exportquizquestions\quiz_question_set;

/**
 * Export Quiz Questions block.
 *
 * @package    block_exportquizquestions
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_exportquizquestions extends block_base {
    /**
     * Set the block title.
     *
     * @return void
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_exportquizquestions');
    }

    /**
     * The block appears on course pages and quiz pages.
     *
     * @return array
     */
    public function applicable_formats() {
        return ['course-view' => true, 'mod-quiz' => true];
    }

    /**
     * Only one instance per course is needed.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Build the list of quizzes and their export forms.
     *
     * Only users who manage the question bank see the block's content.
     *
     * @return stdClass
     */
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        $course = $this->page->course;
        if (empty($course->id) || $course->id == SITEID) {
            return $this->content;
        }
        $coursecontext = context_course::instance($course->id);
        if (!has_capability('moodle/question:managecategory', $coursecontext)) {
            return $this->content;
        }

        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        $quizzes = get_fast_modinfo($course)->get_instances_of('quiz');
        $formats = get_import_export_formats('export');
        $defaultformat = array_key_exists('xml', $formats) ? 'xml' : array_key_first($formats);

        $options = [];
        foreach ($quizzes as $cm) {
            if ($cm->uservisible) {
                $options[$cm->id] = $cm->get_formatted_name();
            }
        }

        if (empty($options)) {
            $html = get_string('noquizzes', 'block_exportquizquestions');
        } else {
            $html = html_writer::start_tag('form', [
                'method' => 'post',
                'action' => (new moodle_url('/blocks/exportquizquestions/export.php'))->out(false),
                'class' => 'block_exportquizquestions_form',
            ]);
            $html .= html_writer::tag('label', get_string('quizzes', 'block_exportquizquestions'),
                ['for' => 'block_exportquizquestions_quiz', 'class' => 'form-label small mb-1']);
            $html .= html_writer::select($options, 'cmid', array_key_first($options), false,
                ['id' => 'block_exportquizquestions_quiz', 'class' => 'custom-select mb-2']);
            $html .= html_writer::tag('label', get_string('format', 'block_exportquizquestions'),
                ['for' => 'block_exportquizquestions_format', 'class' => 'form-label small mb-1']);
            $html .= html_writer::select($formats, 'format', $defaultformat, false,
                ['id' => 'block_exportquizquestions_format', 'class' => 'custom-select mb-2']);
            $html .= html_writer::tag('div', get_string('randommode', 'block_exportquizquestions'),
                ['class' => 'form-label small mb-1']);
            $html .= html_writer::start_tag('div', ['class' => 'form-check mb-1']);
            $html .= html_writer::empty_tag('input', ['type' => 'radio', 'name' => 'randommode', 'value' => 'sample',
                'id' => 'block_exportquizquestions_randommode_sample', 'class' => 'form-check-input', 'checked' => 'checked']);
            $html .= html_writer::tag('label', get_string('randommode_sample', 'block_exportquizquestions'),
                ['for' => 'block_exportquizquestions_randommode_sample', 'class' => 'form-check-label small']);
            $html .= html_writer::end_tag('div');
            $html .= html_writer::start_tag('div', ['class' => 'form-check mb-2']);
            $html .= html_writer::empty_tag('input', ['type' => 'radio', 'name' => 'randommode', 'value' => 'full',
                'id' => 'block_exportquizquestions_randommode_full', 'class' => 'form-check-input']);
            $html .= html_writer::tag('label', get_string('randommode_full', 'block_exportquizquestions'),
                ['for' => 'block_exportquizquestions_randommode_full', 'class' => 'form-check-label small']);
            $html .= html_writer::end_tag('div');
            $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            $html .= html_writer::tag('button', get_string('export', 'block_exportquizquestions'),
                ['type' => 'submit', 'class' => 'btn btn-secondary btn-sm']);
            $html .= html_writer::end_tag('form');
        }

        $this->content->text = $html;
        $this->content->footer = get_string('subcategoryhelp', 'block_exportquizquestions');

        return $this->content;
    }
}
