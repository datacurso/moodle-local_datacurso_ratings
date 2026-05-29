<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_datacurso_ratings;

use core\hook\output\before_footer_html_generation;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../courselib.php');

/**
 * Hook callbacks for local_datacurso_ratings.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Inject the rating UI just before the footer is generated.
     *
     * @param before_footer_html_generation $hook
     * @return void
     */
    public static function before_footer_html_generation(before_footer_html_generation $hook): void {
        global $PAGE, $OUTPUT;

        // Skip if installing or in unsupported layouts.
        if (during_initial_install() || in_array($PAGE->pagelayout, ['maintenance', 'print', 'redirect', 'embedded'])) {
            return;
        }

        // Must be in a module context.
        if ($PAGE->context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        // Ensure user and course module are valid.
        if (!$PAGE->cm || !isloggedin() || isguestuser()) {
            return;
        }

        $cm = $PAGE->cm;

        // Check if plugin is enabled globally.
        if (!get_config('local_datacurso_ratings', 'enabled')) {
            return;
        }

        // Check if plugin is enabled for this specific course.
        if (!local_datacurso_ratings_is_enabled_for_course($cm->course)) {
            return;
        }

        // Exclude edition/configuration pages.
        $script = basename($_SERVER['SCRIPT_NAME']);
        $excludedpages = ['modedit.php', 'edit.php', 'mod_form.php'];
        if (in_array($script, $excludedpages)) {
            return;
        }

        // Avoid editing actions or update parameters.
        $action = optional_param('action', '', PARAM_ALPHA);
        $update = optional_param('update', 0, PARAM_INT);
        if ($action === 'editsection' || $action === 'edit' || $update > 0) {
            return;
        }

        // Ensure we're inside a module route (/mod/...).
        if (strpos($_SERVER['REQUEST_URI'], '/mod/') === false) {
            return;
        }

        $coremodules = [
            'resource',
            'folder',
            'page',
            'url',
            'imscp',
            'book',
            'assign',
            'chat',
            'choice',
            'data',
            'feedback',
            'forum',
            'glossary',
            'lesson',
            'quiz',
            'scorm',
            'survey',
            'wiki',
            'workshop',
            'lti',
            'h5pactivity',
            'hvp',
        ];

        if (!in_array($cm->modname, $coremodules)) {
            return;
        }

        $feedbackpagelike = new \local_datacurso_ratings\output\feedback_page('like');
        $feedbackpagedislike = new \local_datacurso_ratings\output\feedback_page('dislike');

        $feedbackdatalike = $feedbackpagelike->export_for_template($OUTPUT);
        $feedbackdatadislike = $feedbackpagedislike->export_for_template($OUTPUT);

        $html = $OUTPUT->render_from_template('local_datacurso_ratings/rate_button', [
            'cmid' => $cm->id,
            'likeItems' => $feedbackdatalike['items'],
            'dislikeItems' => $feedbackdatadislike['items'],
        ]);

        // Inject before footer.
        $hook->add_html($html);

        // Require JS (for rating actions).
        $PAGE->requires->js_call_amd('local_datacurso_ratings/rate', 'init', [$cm->id]);
    }
}
