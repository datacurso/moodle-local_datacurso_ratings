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
 * Tests that the plugin state comes from the Datacurso AI provider, per tenant on Workplace.
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
     * Load the plugin library and the course form fixtures under test.
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
     * S6: the plugin stays active for a course while the provider keeps it enabled.
     *
     * @covers ::local_datacurso_ratings_is_enabled_for_course
     * @return void
     */
    public function test_course_is_enabled_when_enabled_in_provider(): void {
        $this->require_provider();
        $this->resetAfterTest();
        plugin_settings::set_enabled('local_datacurso_ratings', true);
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
        plugin_settings::set_enabled('local_datacurso_ratings', true);

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
        plugin_settings::set_enabled('local_datacurso_ratings', true);

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
     * Workplace: the provider switches the plugin off for tenant A while tenant B keeps it on.
     *
     * @covers ::local_datacurso_ratings_is_enabled_for_course
     * @return void
     */
    public function test_tenant_a_disabled_while_tenant_b_enabled(): void {
        $this->require_provider();
        if (!class_exists(\tool_tenant\tenancy::class)) {
            $this->markTestSkipped('Tenants exist only on Moodle Workplace.');
        }
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $tenants = $gen->get_plugin_generator('tool_tenant');
        $tenanta = (int)$tenants->create_tenant(['name' => 'Tenant A'])->id;
        $tenantb = (int)$tenants->create_tenant(['name' => 'Tenant B'])->id;
        $usera = $gen->create_user();
        $tenants->allocate_user((int)$usera->id, $tenanta);
        $userb = $gen->create_user();
        $tenants->allocate_user((int)$userb->id, $tenantb);
        $course = $gen->create_course();

        plugin_settings::set_enabled('local_datacurso_ratings', true);
        plugin_settings::set_enabled('local_datacurso_ratings', false, $tenanta);
        plugin_settings::set_enabled('local_datacurso_ratings', true, $tenantb);

        $this->assertFalse(plugin_settings::is_enabled('local_datacurso_ratings', $tenanta));
        $this->assertTrue(plugin_settings::is_enabled('local_datacurso_ratings', $tenantb));

        $this->setUser($usera);
        $this->assertFalse(local_datacurso_ratings_is_enabled_for_course((int)$course->id));

        $this->setUser($userb);
        $this->assertTrue(local_datacurso_ratings_is_enabled_for_course((int)$course->id));
    }

    /**
     * The settings page keeps the comment limit and has no switch of its own: the switch is in the provider.
     *
     * @return void
     */
    public function test_settings_page_has_no_switch_of_its_own(): void {
        $this->require_provider();
        $this->resetAfterTest();
        $this->setAdminUser();

        $root = admin_get_root(true, true);
        $page = $root->locate('local_datacurso_ratings_settings');

        $this->assertInstanceOf(\admin_settingpage::class, $page);
        $this->assertTrue(property_exists($page->settings, 'local_datacurso_ratingsmaxcommentlength'));
        $this->assertFalse(property_exists($page->settings, 'local_datacurso_ratingsenabled'));
        $this->assertNull($root->locate('local_datacurso_ratings_sitesettings'));
    }
}
