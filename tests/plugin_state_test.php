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

use aiprovider_datacurso\local\plugin_settings;
use local_datacurso_ratings\fixtures\provider_dependency_trait;
use local_datacurso_ratings\hook\course_form_hook;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/provider_dependency_trait.php');

/**
 * Tests that the plugin state comes from the Datacurso AI provider instead of the plugin's own setting.
 *
 * @package    local_datacurso_ratings
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_datacurso_ratings\hook\course_form_hook
 * @covers     \local_datacurso_ratings\hook_callbacks
 */
final class plugin_state_test extends \advanced_testcase {
    use provider_dependency_trait;

    /**
     * Load the plugin library, the course form fixtures and the upgrade code under test.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/local/datacurso_ratings/courselib.php');
        require_once($CFG->dirroot . '/course/edit_form.php');
        require_once($CFG->dirroot . '/course/tests/fixtures/testable_course_edit_form.php');
        require_once($CFG->libdir . '/adminlib.php');
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/local/datacurso_ratings/db/upgrade.php');
    }

    /**
     * Restore the SERVER superglobals that the widget test changes.
     *
     * @return void
     */
    protected function tearDown(): void {
        $_SERVER['SCRIPT_NAME'] = '/phpunit.php';
        $_SERVER['REQUEST_URI'] = '/phpunit.php';
        parent::tearDown();
    }

    /**
     * Run the course edit form definition hook as admin and return the resulting form.
     *
     * @return \MoodleQuickForm The course edit form after the plugin extended it.
     */
    private function define_course_form(): \MoodleQuickForm {
        global $COURSE, $DB;
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $COURSE = $course;

        $args = [
            'course' => $course,
            'category' => $DB->get_record('course_categories', ['id' => $course->category]),
            'editoroptions' => ['context' => \context_course::instance($course->id), 'subdirs' => 0],
            'returnto' => new \moodle_url('/'),
            'returnurl' => new \moodle_url('/'),
        ];
        $courseform = new \testable_course_edit_form(null, $args);
        $mform = $courseform->get_quick_form();
        course_form_hook::after_form_definition(new \core_course\hook\after_form_definition($courseform, $mform));
        return $mform;
    }

    /**
     * Render the footer hook for an enrolled student on a page activity.
     *
     * @return string The HTML the plugin added to the footer.
     */
    private function render_widget_for_student(): string {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('page', $page->id, $course->id);

        $this->setUser($student);
        $PAGE->set_context(\context_module::instance($cm->id));
        $PAGE->set_cm($cm, $course);
        $PAGE->set_pagelayout('incourse');
        $_SERVER['SCRIPT_NAME'] = '/mod/page/view.php';
        $_SERVER['REQUEST_URI'] = '/mod/page/view.php?id=' . $cm->id;

        $hook = new \core\hook\output\before_footer_html_generation($PAGE->get_renderer('core'));
        hook_callbacks::before_footer_html_generation($hook);
        return $hook->get_output();
    }

    /**
     * S6: the plugin stays active for a course while the provider keeps it enabled, whatever the former setting says.
     *
     * @covers ::local_datacurso_ratings_is_enabled_for_course
     * @return void
     */
    public function test_course_is_enabled_when_enabled_in_provider(): void {
        $this->require_provider();
        $this->resetAfterTest();
        set_config('enabled', '0', 'local_datacurso_ratings');
        $course = $this->getDataGenerator()->create_course();

        $this->assertTrue(local_datacurso_ratings_is_enabled_for_course((int)$course->id));
    }

    /**
     * S6: the plugin is inactive in every course when the provider disables it.
     *
     * @covers ::local_datacurso_ratings_is_enabled_for_course
     * @return void
     */
    public function test_course_is_disabled_when_disabled_in_provider(): void {
        $this->require_provider();
        $this->resetAfterTest();
        plugin_settings::set_enabled('local_datacurso_ratings', false);
        $course = $this->getDataGenerator()->create_course();
        local_datacurso_ratings_set_course_enabled((int)$course->id, true);

        $this->assertFalse(local_datacurso_ratings_is_enabled_for_course((int)$course->id));
    }

    /**
     * S6: the rating widget is injected while the provider keeps the plugin enabled.
     *
     * @return void
     */
    public function test_widget_is_injected_when_enabled_in_provider(): void {
        $this->require_provider();
        $this->resetAfterTest();
        set_config('enabled', '0', 'local_datacurso_ratings');

        $this->assertNotEmpty($this->render_widget_for_student());
    }

    /**
     * S6: the course form shows the plugin section while the provider keeps the plugin enabled.
     *
     * @return void
     */
    public function test_course_form_has_plugin_section_when_enabled_in_provider(): void {
        $this->require_provider();
        $this->resetAfterTest();
        set_config('enabled', '0', 'local_datacurso_ratings');

        $this->assertTrue($this->define_course_form()->elementExists('local_datacurso_ratings_enabled'));
    }

    /**
     * S6: the course form has no plugin section when the provider disables the plugin.
     *
     * @return void
     */
    public function test_course_form_has_no_plugin_section_when_disabled_in_provider(): void {
        $this->require_provider();
        $this->resetAfterTest();
        plugin_settings::set_enabled('local_datacurso_ratings', false);

        $this->assertFalse($this->define_course_form()->elementExists('local_datacurso_ratings_enabled'));
    }

    /**
     * S7: the general settings page no longer defines the plugin's own "Enabled" setting.
     *
     * @return void
     */
    public function test_settings_page_has_no_enabled_setting(): void {
        $this->require_provider();
        $this->resetAfterTest();
        $this->setAdminUser();

        $page = admin_get_root(true, true)->locate('local_datacurso_ratings_settings');

        $this->assertInstanceOf(\admin_settingpage::class, $page);
        $this->assertTrue(property_exists($page->settings, 'local_datacurso_ratingsmaxcommentlength'));
        $this->assertFalse(property_exists($page->settings, 'local_datacurso_ratingsenabled'));
    }

    /**
     * S7: the upgrade step removes the former "Enabled" setting.
     *
     * @covers ::xmldb_local_datacurso_ratings_upgrade
     * @return void
     */
    public function test_upgrade_removes_the_former_enabled_setting(): void {
        $this->resetAfterTest();
        set_config('enabled', '1', 'local_datacurso_ratings');
        set_config('version', 2026103000, 'local_datacurso_ratings');

        $this->assertTrue(xmldb_local_datacurso_ratings_upgrade(2026103000));

        $this->assertFalse(get_config('local_datacurso_ratings', 'enabled'));
        $this->assertEquals(2026110500, get_config('local_datacurso_ratings', 'version'));
    }
}
