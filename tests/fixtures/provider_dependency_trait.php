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
 * Guard for the tests that reach the Datacurso AI provider.
 *
 * @package    local_datacurso_ratings
 * @category   test
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_datacurso_ratings\fixtures;

/**
 * Skips the current test when the aiprovider_datacurso plugin is not installed.
 *
 * The plugin state (enabled or not) lives in the Datacurso AI provider, and the external
 * functions, the task, the navigation, the settings tree, the footer hook and the course form
 * hooks ask it first. The course form submission hook also runs on every create_course() call,
 * so any test that creates a course reaches the provider. The CI pipeline does not install
 * declared dependencies, so without this guard those tests die with "Class not found" instead
 * of reporting that they could not run.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait provider_dependency_trait {
    /**
     * Skip the current test when the Datacurso AI provider is not installed on this site.
     *
     * @return void
     */
    protected function require_provider(): void {
        if (!class_exists(\aiprovider_datacurso\local\plugin_settings::class)) {
            $this->markTestSkipped('aiprovider_datacurso is not installed.');
        }
    }
}
