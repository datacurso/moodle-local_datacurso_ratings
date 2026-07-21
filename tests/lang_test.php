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
 * Language file existence and key-string tests.
 *
 * These are pure filesystem checks — no DB access required.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Test suite for language file coverage across all supported locales.
 */
class lang_test extends \basic_testcase {

    /**
     * Absolute path to the plugin's lang directory.
     *
     * @return string
     */
    private function lang_dir(): string {
        global $CFG;
        return $CFG->dirroot . '/local/datacurso_ratings/lang';
    }

    /**
     * All seven supported language codes declared by the plugin.
     *
     * @return array
     */
    private function supported_locales(): array {
        return ['es', 'en', 'de', 'fr', 'id', 'pt_br', 'ru'];
    }

    /**
     * Minimum set of string keys that every lang file must define.
     *
     * @return array
     */
    private function required_string_keys(): array {
        return [
            'pluginname',
            'likes',
            'dislikes',
            'rateactivity',
        ];
    }

    /**
     * Each of the 7 supported locales has a lang file at the expected path.
     *
     * MDL-INT-021 step 1
     */
    public function test_lang_files_exist_for_all_supported_locales(): void {
        $langdir = $this->lang_dir();

        foreach ($this->supported_locales() as $locale) {
            $filepath = $langdir . '/' . $locale . '/local_datacurso_ratings.php';
            $this->assertFileExists(
                $filepath,
                "Language file for locale '{$locale}' is missing: {$filepath}"
            );
        }
    }

    /**
     * Each lang file defines the required key strings (pluginname at minimum).
     *
     * The test loads each file in isolation, reads the $string array it populates,
     * and asserts that every required key is present and non-empty.
     *
     * MDL-INT-021 step 2
     */
    public function test_required_string_keys_exist_in_all_lang_files(): void {
        $langdir = $this->lang_dir();

        foreach ($this->supported_locales() as $locale) {
            $filepath = $langdir . '/' . $locale . '/local_datacurso_ratings.php';

            if (!file_exists($filepath)) {
                // Already caught by the previous test; skip here to avoid duplicate failures.
                $this->markTestIncomplete("Lang file missing for '{$locale}' — see test_lang_files_exist_for_all_supported_locales.");
            }

            // Load the lang file into an isolated $string array.
            $string = [];
            include $filepath;

            foreach ($this->required_string_keys() as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $string,
                    "String key '{$key}' not found in lang file for locale '{$locale}'."
                );
                $this->assertNotEmpty(
                    $string[$key],
                    "String key '{$key}' is empty in lang file for locale '{$locale}'."
                );
            }
        }
    }
}
