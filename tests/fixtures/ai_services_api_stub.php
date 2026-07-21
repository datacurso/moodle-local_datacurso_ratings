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
 * Stub class for ai_services_api, used when aiprovider_datacurso is not installed.
 *
 * This file is loaded conditionally in tests only when the real class does not exist
 * (e.g. in CI environments where the aiprovider_datacurso plugin is not present).
 *
 * @package    local_datacurso_ratings
 * @category   test
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace aiprovider_datacurso\httpclient;

/**
 * Minimal stub matching the interface used by external function tests.
 */
class ai_services_api {
    /**
     * Stub for the HTTP request method.
     *
     * @param string $method  HTTP method (GET, POST, etc.).
     * @param string $path    API endpoint path.
     * @param array  $body    Request body.
     * @return array|null
     */
    public function request(string $method, string $path, array $body = []): ?array {
        return null;
    }
}
