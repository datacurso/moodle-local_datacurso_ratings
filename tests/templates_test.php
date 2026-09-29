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
 * Tests for the plugin Mustache templates.
 *
 * @package    local_datacurso_ratings
 * @category   test
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_datacurso_ratings;

/**
 * Rendering tests for templates that receive untrusted content.
 *
 * @covers \local_datacurso_ratings\external\get_ai_analysis_comments
 */
final class templates_test extends \advanced_testcase {
    /**
     * The AI analysis error branch must escape the message (it may echo remote API output)
     * and must not leak stray characters into the rendered markup.
     */
    public function test_ai_analysis_error_message_is_escaped(): void {
        global $OUTPUT;
        $this->resetAfterTest(true);

        $html = $OUTPUT->render_from_template('local_datacurso_ratings/ai_analysis_response', [
            'loading' => false,
            'success' => false,
            'message' => '<script>x</script>',
        ]);

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        // Backtick written as chr(96): moodle-cs forbids literal backticks in strings.
        $this->assertStringNotContainsString(chr(96), $html);
    }

    /**
     * The success branch keeps escaping the message as well.
     */
    public function test_ai_analysis_success_message_is_escaped(): void {
        global $OUTPUT;
        $this->resetAfterTest(true);

        $html = $OUTPUT->render_from_template('local_datacurso_ratings/ai_analysis_response', [
            'loading' => false,
            'success' => true,
            'message' => '<b>bold</b>',
        ]);

        $this->assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>bold</b>', $html);
    }
}
