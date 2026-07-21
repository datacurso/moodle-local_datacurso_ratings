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
 * Tests for get_courses_by_category external function.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_datacurso_ratings\external\get_courses_by_category
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/external_testcase.php');

use local_datacurso_ratings\external\get_courses_by_category;

/**
 * Test suite for the get_courses_by_category web service.
 */
class get_courses_by_category_test extends externallib_advanced_testcase {

    /**
     * Courses in a category are listed and the site course (ID 1) is never included.
     *
     * MDL-INT-007 step 1
     */
    public function test_lists_courses_in_category_excluding_site_course(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen = $this->getDataGenerator();
        $category = $gen->create_category();

        // Create two visible courses in the category.
        $course1 = $gen->create_course(['category' => $category->id, 'visible' => 1]);
        $course2 = $gen->create_course(['category' => $category->id, 'visible' => 1]);

        $result = get_courses_by_category::execute((int)$category->id);

        $ids = array_map('intval', array_column($result, 'id'));

        // Both real courses must appear.
        $this->assertContains((int)$course1->id, $ids);
        $this->assertContains((int)$course2->id, $ids);

        // The site course (SITEID = 1) must never appear.
        $this->assertNotContains(SITEID, $ids);

        // Basic field check.
        foreach ($result as $entry) {
            $this->assertArrayHasKey('fullname', $entry);
            $this->assertArrayHasKey('shortname', $entry);
            $this->assertArrayHasKey('categoryid', $entry);
            $this->assertEquals((int)$category->id, $entry['categoryid']);
        }
    }

    /**
     * Requesting a non-existent category raises a dml_missing_record_exception (MUST_EXIST).
     *
     * MDL-INT-007 step 2
     */
    public function test_nonexistent_category_raises_exception(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $this->expectException(\dml_missing_record_exception::class);
        get_courses_by_category::execute(999999);
    }
}
