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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/external_testcase.php');
require_once(__DIR__ . '/fixtures/provider_dependency_trait.php');
require_once($CFG->dirroot . '/local/datacurso_ratings/lib.php');
require_once($CFG->libdir . '/adminlib.php');

use aiprovider_datacurso\local\plugin_settings;
use local_datacurso_ratings\external\get_activity_comments;
use local_datacurso_ratings\external\get_ai_analysis_comments;
use local_datacurso_ratings\external\get_ai_analysis_course;
use local_datacurso_ratings\external\get_ai_analysis_global;
use local_datacurso_ratings\external\get_courses_by_category;
use local_datacurso_ratings\external\get_ratings_report;
use local_datacurso_ratings\external\get_ratings_report_course;
use local_datacurso_ratings\fixtures\provider_dependency_trait;
use local_datacurso_ratings\task\update_recommendations_cache;

/**
 * Tests that the usage entry points stop when the Datacurso AI provider disables the plugin.
 *
 * @package    local_datacurso_ratings
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_datacurso_ratings\external\get_activity_comments
 * @covers     \local_datacurso_ratings\external\get_ai_analysis_comments
 * @covers     \local_datacurso_ratings\external\get_ai_analysis_course
 * @covers     \local_datacurso_ratings\external\get_ai_analysis_global
 * @covers     \local_datacurso_ratings\external\get_courses_by_category
 * @covers     \local_datacurso_ratings\external\get_ratings_report
 * @covers     \local_datacurso_ratings\external\get_ratings_report_course
 * @covers     \local_datacurso_ratings\task\update_recommendations_cache
 * @covers     ::local_datacurso_ratings_extend_navigation_course
 */
final class plugin_disabled_test extends \externallib_advanced_testcase {
    use provider_dependency_trait;

    /**
     * Skip every test of this class when the Datacurso AI provider is not installed.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->require_provider();
    }

    /**
     * Each guarded web service with arguments that pass parameter validation.
     *
     * @return array<string, array{0: callable, 1: array}>
     */
    public static function web_service_provider(): array {
        return [
            'get_ratings_report' => [[get_ratings_report::class, 'execute'], []],
            'get_ratings_report_course' => [[get_ratings_report_course::class, 'execute'], [1]],
            'get_activity_comments' => [[get_activity_comments::class, 'execute'], [1]],
            'get_courses_by_category' => [[get_courses_by_category::class, 'execute'], [1]],
            'get_ai_analysis_comments' => [[get_ai_analysis_comments::class, 'execute'], [1]],
            'get_ai_analysis_course' => [[get_ai_analysis_course::class, 'execute'], [1]],
            'get_ai_analysis_global' => [[get_ai_analysis_global::class, 'execute'], []],
        ];
    }

    /**
     * A disabled plugin rejects every usage web service with the provider message.
     *
     * @dataProvider web_service_provider
     * @param callable $callable The external function.
     * @param array $args Arguments that pass parameter validation.
     * @return void
     */
    public function test_web_service_throws_when_disabled(callable $callable, array $args): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        plugin_settings::set_enabled('local_datacurso_ratings', false);

        try {
            call_user_func_array($callable, $args);
            $this->fail('A moodle_exception was expected.');
        } catch (\moodle_exception $e) {
            $this->assertSame('plugindisabled', $e->errorcode);
            $this->assertSame('aiprovider_datacurso', $e->module);
        }
    }

    /**
     * A site administrator gets the variant with the link to the provider plugins list.
     *
     * @return void
     */
    public function test_web_service_throws_admin_variant_for_site_admin(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        plugin_settings::set_enabled('local_datacurso_ratings', false);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/Datacurso plugins list/');
        get_courses_by_category::execute(1);
    }

    /**
     * An enabled plugin keeps serving the web services.
     *
     * @return void
     */
    public function test_web_service_works_when_enabled(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        plugin_settings::set_enabled('local_datacurso_ratings', true);
        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);

        $result = get_courses_by_category::execute((int)$category->id);

        $this->assertContains((int)$course->id, array_map('intval', array_column($result, 'id')));
    }

    /**
     * The recommendations task skips its work, without throwing, while the plugin is disabled.
     *
     * @return void
     */
    public function test_task_skips_when_disabled(): void {
        $this->resetAfterTest();
        plugin_settings::set_enabled('local_datacurso_ratings', false);
        $user = $this->getDataGenerator()->create_user();
        $cache = \cache::make('local_datacurso_ratings', 'recommendations');
        $cache->set("user_{$user->id}", ['stale']);

        $this->expectOutputString(get_string('task_skipped_plugindisabled', 'local_datacurso_ratings') . "\n");
        (new update_recommendations_cache())->execute();

        $this->assertSame(['stale'], $cache->get("user_{$user->id}"));
    }

    /**
     * The recommendations task runs while the plugin is enabled.
     *
     * @return void
     */
    public function test_task_runs_when_enabled(): void {
        $this->resetAfterTest();
        plugin_settings::set_enabled('local_datacurso_ratings', true);
        $user = $this->getDataGenerator()->create_user();
        $cache = \cache::make('local_datacurso_ratings', 'recommendations');
        $cache->purge();

        $this->expectOutputRegex('/Finished updating cache/');
        (new update_recommendations_cache())->execute();

        $this->assertIsArray($cache->get("user_{$user->id}"));
    }

    /**
     * Run the course navigation callback as an editing teacher and return the report link, if any.
     *
     * @return \navigation_node|false
     */
    private function course_report_link() {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $root = \navigation_node::create('Root', null, \navigation_node::TYPE_ROOTNODE, null, 'root');
        $root->add_node(\navigation_node::create('Reports', null, \navigation_node::TYPE_CONTAINER, null, 'coursereports'));
        local_datacurso_ratings_extend_navigation_course($root, $course, \context_course::instance($course->id));
        return $root->get('coursereports')->get('local_datacurso_ratings_report');
    }

    /**
     * The course report link is not added while the plugin is disabled.
     *
     * @return void
     */
    public function test_course_navigation_link_absent_when_disabled(): void {
        $this->resetAfterTest();
        plugin_settings::set_enabled('local_datacurso_ratings', false);

        $this->assertFalse($this->course_report_link());
    }

    /**
     * The course report link is added while the plugin is enabled.
     *
     * @return void
     */
    public function test_course_navigation_link_present_when_enabled(): void {
        $this->resetAfterTest();
        plugin_settings::set_enabled('local_datacurso_ratings', true);

        $this->assertNotFalse($this->course_report_link());
    }

    /**
     * The admin tree keeps the configuration pages but drops the ratings report while the plugin is disabled.
     *
     * @return void
     */
    public function test_admin_report_page_absent_when_disabled(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        plugin_settings::set_enabled('local_datacurso_ratings', false);

        $root = admin_get_root(true, true);

        $this->assertNull($root->locate('local_datacurso_ratings_report'));
        $this->assertNotNull($root->locate('local_datacurso_ratings_feedback'));
        $this->assertNotNull($root->locate('local_datacurso_ratings_settings'));
    }

    /**
     * The admin tree has the ratings report while the plugin is enabled.
     *
     * @return void
     */
    public function test_admin_report_page_present_when_enabled(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        plugin_settings::set_enabled('local_datacurso_ratings', true);

        $this->assertNotNull(admin_get_root(true, true)->locate('local_datacurso_ratings_report'));
    }
}
