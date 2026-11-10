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

use local_datacurso_ratings\fixtures\provider_dependency_trait;
use local_datacurso_ratings\local\tenancy;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/provider_dependency_trait.php');

/**
 * Tenancy of the ratings: per tenant on Workplace, a single implicit tenant elsewhere.
 *
 * @package    local_datacurso_ratings
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_datacurso_ratings\local\tenancy
 * @covers     \local_datacurso_ratings\recommendations\service
 */
final class tenancy_test extends \advanced_testcase {
    use provider_dependency_trait;

    /**
     * Without tool_tenant every user is in the implicit tenant 0.
     */
    public function test_without_tenancy_every_user_is_in_tenant_zero(): void {
        $this->resetAfterTest();
        if (class_exists(\tool_tenant\tenancy::class)) {
            $this->markTestSkipped('This site has tenants.');
        }

        $user = $this->getDataGenerator()->create_user();

        $this->assertSame(tenancy::NO_TENANT, tenancy::get_tenant_id((int)$user->id));
    }

    /**
     * On Workplace a user is not recommended the courses of another tenant, which they cannot see.
     */
    public function test_recommendations_stay_within_the_courses_of_the_tenant(): void {
        global $DB;
        // Before anything is created, so a skipped test leaves nothing behind.
        if (!class_exists(\tool_tenant\tenancy::class)) {
            $this->markTestSkipped('Tenants exist only on Moodle Workplace.');
        }
        // Creating a course runs the course form hook, which asks the Datacurso AI provider.
        $this->require_provider();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $tenants = $gen->get_plugin_generator('tool_tenant');

        // What a Workplace install does and tool_tenant skips under PHPUnit: regular users lose the
        // course list everywhere and get it back in the category of their tenant only.
        unassign_capability('moodle/category:viewcourselist', $DB->get_field('role', 'id', ['shortname' => 'user']));
        unassign_capability('moodle/category:viewcourselist', $DB->get_field('role', 'id', ['shortname' => 'guest']));
        \tool_tenant\manager::change_core_roles();

        $courses = [];
        $tenantids = [];
        foreach (['A', 'B'] as $name) {
            $tenantid = (int)$tenants->create_tenant(['name' => "Tenant {$name}"])->id;
            $categoryid = (int)$gen->create_category(['name' => "Category {$name}"])->id;
            $tenant = new \tool_tenant\tenant($tenantid);
            $tenant->set('categoryid', $categoryid);
            $tenant->save();
            $courses[$name] = $gen->create_course(['category' => $categoryid]);
            $tenantids[$name] = $tenantid;
        }

        // A user of tenant A who likes the category of tenant A, and a liked course in tenant B.
        $usera = $gen->create_user();
        $tenants->allocate_user((int)$usera->id, $tenantids['A']);
        $userb = $gen->create_user();
        $tenants->allocate_user((int)$userb->id, $tenantids['B']);
        foreach ($tenantids as $name => $tenantid) {
            (new \tool_tenant\manager())->change_tenant_category($tenantid, (int)$courses[$name]->category);
        }
        foreach ([[$usera, 'A'], [$userb, 'B']] as [$user, $name]) {
            $DB->insert_record('local_datacurso_ratings', (object)[
                'cmid' => 0, 'courseid' => $courses[$name]->id, 'categoryid' => $courses[$name]->category,
                'userid' => $user->id, 'tenant_id' => $tenantids[$name], 'rating' => 1, 'feedback' => '',
                'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
        \cache::make('local_datacurso_ratings', 'recommendations')->purge();

        $recommended = array_column(recommendations\service::get_recommendations_for_user((int)$usera->id, 50), 'courseid');

        $this->assertContains((int)$courses['A']->id, $recommended);
        $this->assertNotContains((int)$courses['B']->id, $recommended);
    }
}
