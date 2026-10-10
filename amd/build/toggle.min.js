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
 * Disables the quiz dropdown on the export form while "all quizzes" is selected.
 *
 * @module     block_exportquizquestions/toggle
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    return {
        /**
         * Wire up the quiz select to follow the download-mode radio buttons.
         *
         * @param {String} selectId The id of the quiz <select> element.
         * @param {String} radioName The name shared by the mode radio inputs.
         */
        init: function(selectId, radioName) {
            var select = document.getElementById(selectId);
            var radios = document.getElementsByName(radioName);
            if (!select || !radios.length) {
                return;
            }

            var update = function() {
                var bulk = false;
                radios.forEach(function(radio) {
                    if (radio.checked && radio.value === 'bulk') {
                        bulk = true;
                    }
                });
                select.disabled = bulk;
            };

            radios.forEach(function(radio) {
                radio.addEventListener('change', update);
            });
            update();
        }
    };
});
