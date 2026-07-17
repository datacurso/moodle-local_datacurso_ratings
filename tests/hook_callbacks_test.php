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

/**
 * Tests for hook_callbacks and course_form_hook.
 *
 * @package    local_datacurso_ratings
 * @category   test
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_datacurso_ratings\hook_callbacks
 * @covers \local_datacurso_ratings\hook\course_form_hook
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/datacurso_ratings/courselib.php');
require_once($CFG->dirroot . '/course/edit_form.php');
require_once($CFG->dirroot . '/course/tests/fixtures/testable_course_edit_form.php');

/**
 * Tests for widget visibility guards (INT-011) and course form hook (INT-012).
 */
class hook_callbacks_test extends \advanced_testcase {

    /**
     * Configure PAGE context and globals to simulate a module page for a supported module.
     *
     * @param object $course   The course record.
     * @param object $cm       The course module record (must have ->id, ->course, ->modname).
     */
    private function set_up_module_page(object $course, object $cm): void {
        global $PAGE;

        $context = \context_module::instance($cm->id);
        $PAGE->set_context($context);
        $PAGE->set_cm($cm, $course);
        $PAGE->set_pagelayout('incourse');
        $_SERVER['SCRIPT_NAME']  = '/mod/page/view.php';
        $_SERVER['REQUEST_URI']  = '/mod/page/view.php?id=' . $cm->id;
    }

    /**
     * Restore SERVER superglobals after each test that touches them.
     */
    protected function tearDown(): void {
        $_SERVER['SCRIPT_NAME']  = '/phpunit.php';
        $_SERVER['REQUEST_URI']  = '/phpunit.php';
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // INT-011 — Widget visibility guards
    // -----------------------------------------------------------------------

    /**
     * Verify that the widget is injected for an authenticated non-guest enrolled student
     * when the plugin is enabled globally and for the course.
     *
     * @spec MDL-INT-011 step 1
     */
    public function test_widget_appears_for_authenticated_enrolled_student(): void {
        $this->resetAfterTest(true);

        set_config('enabled', 1, 'local_datacurso_ratings');

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);

        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $cm   = get_coursemodule_from_instance('page', $page->id, $course->id);

        $this->setUser($student);
        $this->set_up_module_page($course, $cm);

        global $PAGE;
        $hook = new \core\hook\output\before_footer_html_generation($PAGE->get_renderer('core'));
        \local_datacurso_ratings\hook_callbacks::before_footer_html_generation($hook);

        $html = $hook->get_output();
        $this->assertNotEmpty($html, 'Widget HTML must be injected for an enrolled authenticated student.');
    }

    /**
     * Verify that the widget is NOT injected when the current page is an editing page
     * (modedit.php, edit.php, mod_form.php, action=edit, action=editsection).
     *
     * @spec MDL-INT-011 step 2
     */
    public function test_widget_absent_on_edit_pages(): void {
        $this->resetAfterTest(true);

        set_config('enabled', 1, 'local_datacurso_ratings');

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);

        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $cm   = get_coursemodule_from_instance('page', $page->id, $course->id);

        $this->setUser($student);
        $this->set_up_module_page($course, $cm);

        global $PAGE;

        $editscripts = ['modedit.php', 'edit.php', 'mod_form.php'];
        foreach ($editscripts as $script) {
            $_SERVER['SCRIPT_NAME'] = '/course/' . $script;
            $_SERVER['REQUEST_URI'] = '/mod/page/' . $script . '?id=' . $cm->id;

            $hook = new \core\hook\output\before_footer_html_generation($PAGE->get_renderer('core'));
            \local_datacurso_ratings\hook_callbacks::before_footer_html_generation($hook);

            $this->assertEmpty(
                $hook->get_output(),
                "Widget must NOT be injected on edit page: {$script}"
            );
        }
    }

    /**
     * Verify that the widget is NOT injected when the plugin is globally disabled.
     *
     * @spec MDL-INT-011 step 3
     */
    public function test_widget_absent_when_globally_disabled(): void {
        $this->resetAfterTest(true);

        set_config('enabled', 0, 'local_datacurso_ratings');

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);

        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $cm   = get_coursemodule_from_instance('page', $page->id, $course->id);

        $this->setUser($student);
        $this->set_up_module_page($course, $cm);

        global $PAGE;
        $hook = new \core\hook\output\before_footer_html_generation($PAGE->get_renderer('core'));
        \local_datacurso_ratings\hook_callbacks::before_footer_html_generation($hook);

        $this->assertEmpty($hook->get_output(), 'Widget must NOT be injected when plugin is globally disabled.');
    }

    /**
     * Verify that the widget is NOT injected for unsupported module types (label, subsection).
     *
     * @spec MDL-INT-011 step 4
     */
    public function test_widget_absent_for_unsupported_module_types(): void {
        $this->resetAfterTest(true);

        set_config('enabled', 1, 'local_datacurso_ratings');

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);

        $label = $this->getDataGenerator()->create_module('label', ['course' => $course->id]);
        $cm    = get_coursemodule_from_instance('label', $label->id, $course->id);

        $this->setUser($student);

        global $PAGE;
        $context = \context_module::instance($cm->id);
        $PAGE->set_context($context);
        $PAGE->set_cm($cm, $course);
        $PAGE->set_pagelayout('incourse');
        $_SERVER['SCRIPT_NAME'] = '/mod/label/view.php';
        $_SERVER['REQUEST_URI'] = '/mod/label/view.php?id=' . $cm->id;

        $hook = new \core\hook\output\before_footer_html_generation($PAGE->get_renderer('core'));
        \local_datacurso_ratings\hook_callbacks::before_footer_html_generation($hook);

        $this->assertEmpty($hook->get_output(), 'Widget must NOT be injected for the label module type.');
    }

    /**
     * Verify that the widget is NOT injected for guest users.
     *
     * @spec MDL-INT-011 step 5
     */
    public function test_widget_absent_for_guest_user(): void {
        $this->resetAfterTest(true);

        set_config('enabled', 1, 'local_datacurso_ratings');

        $course = $this->getDataGenerator()->create_course();
        $page   = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $cm     = get_coursemodule_from_instance('page', $page->id, $course->id);

        $this->setGuestUser();
        $this->set_up_module_page($course, $cm);

        global $PAGE;
        $hook = new \core\hook\output\before_footer_html_generation($PAGE->get_renderer('core'));
        \local_datacurso_ratings\hook_callbacks::before_footer_html_generation($hook);

        $this->assertEmpty($hook->get_output(), 'Widget must NOT be injected for guest users.');
    }

    /**
     * Verify that the widget is NOT injected when the plugin is disabled at the course level,
     * even if it is enabled globally.
     *
     * @spec MDL-INT-011 step 6
     */
    public function test_widget_absent_when_course_disabled(): void {
        $this->resetAfterTest(true);

        set_config('enabled', 1, 'local_datacurso_ratings');

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);

        // Disable the plugin for this specific course.
        local_datacurso_ratings_set_course_enabled($course->id, false);

        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $cm   = get_coursemodule_from_instance('page', $page->id, $course->id);

        $this->setUser($student);
        $this->set_up_module_page($course, $cm);

        global $PAGE;
        $hook = new \core\hook\output\before_footer_html_generation($PAGE->get_renderer('core'));
        \local_datacurso_ratings\hook_callbacks::before_footer_html_generation($hook);

        $this->assertEmpty($hook->get_output(), 'Widget must NOT be injected when the plugin is disabled for the course.');
    }

    // -----------------------------------------------------------------------
    // INT-012 — Course form hook
    // -----------------------------------------------------------------------

    /**
     * Verify that the course edit form includes the plugin section when the plugin is globally enabled.
     *
     * @spec MDL-INT-012 step 1
     */
    public function test_course_form_includes_plugin_section_when_globally_enabled(): void {
        $this->resetAfterTest(true);

        set_config('enabled', 1, 'local_datacurso_ratings');

        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();

        global $COURSE, $DB;
        $COURSE = $course;

        $category = $DB->get_record('course_categories', ['id' => $course->category]);
        $args = [
            'course'        => $course,
            'category'      => $category,
            'editoroptions' => ['context' => \context_course::instance($course->id), 'subdirs' => 0],
            'returnto'      => new \moodle_url('/'),
            'returnurl'     => new \moodle_url('/'),
        ];
        $courseform = new \testable_course_edit_form(null, $args);
        $mform = $courseform->get_quick_form();
        $hook  = new \core_course\hook\after_form_definition($courseform, $mform);
        \local_datacurso_ratings\hook\course_form_hook::after_form_definition($hook);

        $this->assertTrue(
            $mform->elementExists('local_datacurso_ratings_enabled'),
            'The plugin toggle element must be present in the course form when globally enabled.'
        );
    }

    /**
     * Verify that the default value is enabled (1) when no prior course configuration exists.
     *
     * @spec MDL-INT-012 step 2
     */
    public function test_course_form_default_is_enabled_when_no_prior_config(): void {
        $this->resetAfterTest(true);

        set_config('enabled', 1, 'local_datacurso_ratings');

        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();

        global $COURSE, $DB;
        $COURSE = $course;

        $category = $DB->get_record('course_categories', ['id' => $course->category]);
        $args = [
            'course'        => $course,
            'category'      => $category,
            'editoroptions' => ['context' => \context_course::instance($course->id), 'subdirs' => 0],
            'returnto'      => new \moodle_url('/'),
            'returnurl'     => new \moodle_url('/'),
        ];
        $courseform = new \testable_course_edit_form(null, $args);
        $mform = $courseform->get_quick_form();
        $hook  = new \core_course\hook\after_form_definition($courseform, $mform);
        \local_datacurso_ratings\hook\course_form_hook::after_form_definition($hook);

        $defaults = $mform->exportValues();
        $this->assertEquals(
            1,
            (int)$defaults['local_datacurso_ratings_enabled'],
            'Default value must be 1 (enabled) when no prior course config exists.'
        );
    }

    /**
     * Verify that submitting the course form persists the chosen enabled/disabled value.
     *
     * @spec MDL-INT-012 step 3
     */
    public function test_course_form_submission_persists_config(): void {
        global $DB;
        $this->resetAfterTest(true);

        set_config('enabled', 1, 'local_datacurso_ratings');

        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();

        global $COURSE;
        $COURSE = $course;

        // Simulate form submission data with plugin disabled.
        $data                                    = new \stdClass();
        $data->id                                = $course->id;
        $data->local_datacurso_ratings_enabled   = 0;

        $hook = new \core_course\hook\after_form_submission($data);
        \local_datacurso_ratings\hook\course_form_hook::after_form_submission($hook);

        $record = $DB->get_record(
            'local_datacurso_ratings_course_settings',
            ['courseid' => $course->id],
            'enabled',
            MUST_EXIST
        );
        $this->assertSame(0, (int)$record->enabled, 'Submitted disabled value must be persisted in the DB.');
    }

    /**
     * Verify that submitting the course form does NOT persist config when the plugin is globally disabled.
     *
     * @spec MDL-INT-012 step 4
     */
    public function test_course_form_submission_skips_save_when_globally_disabled(): void {
        global $DB;
        $this->resetAfterTest(true);

        set_config('enabled', 0, 'local_datacurso_ratings');

        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();

        global $COURSE;
        $COURSE = $course;

        $data                                    = new \stdClass();
        $data->id                                = $course->id;
        $data->local_datacurso_ratings_enabled   = 1;

        $hook = new \core_course\hook\after_form_submission($data);
        \local_datacurso_ratings\hook\course_form_hook::after_form_submission($hook);

        $exists = $DB->record_exists('local_datacurso_ratings_course_settings', ['courseid' => $course->id]);
        $this->assertFalse($exists, 'No course config must be saved when the plugin is globally disabled.');
    }
}
