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
 * Helper functions for course-level settings.
 *
 * @package    local_datacurso_ratings
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Check if the plugin is enabled for a specific course.
 *
 * Checks both global and course-level settings.
 *
 * @param int $courseid The course ID.
 * @return bool True if enabled, false otherwise.
 */
function local_datacurso_ratings_is_enabled_for_course(int $courseid): bool {
    global $DB;

    if (!get_config('local_datacurso_ratings', 'enabled')) {
        return false;
    }

    $record = $DB->get_record('local_datacurso_ratings_course_settings', ['courseid' => $courseid], 'enabled', IGNORE_MISSING);

    if ($record === false) {
        return true;
    }

    return (bool)$record->enabled;
}

/**
 * Set the enabled status for a specific course.
 *
 * @param int $courseid The course ID.
 * @param bool $enabled Whether the plugin should be enabled.
 * @return void
 */
function local_datacurso_ratings_set_course_enabled(int $courseid, bool $enabled): void {
    global $DB;

    $record = $DB->get_record('local_datacurso_ratings_course_settings', ['courseid' => $courseid], 'id', IGNORE_MISSING);

    $now = time();

    if ($record) {
        $DB->update_record('local_datacurso_ratings_course_settings', (object)[
            'id' => $record->id,
            'enabled' => $enabled ? 1 : 0,
            'timemodified' => $now,
        ]);
    } else {
        $DB->insert_record('local_datacurso_ratings_course_settings', (object)[
            'courseid' => $courseid,
            'enabled' => $enabled ? 1 : 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }
}

/**
 * Get the enabled status for a specific course.
 *
 * @param int $courseid The course ID.
 * @return bool|null True if enabled, false if disabled, null if not configured.
 */
function local_datacurso_ratings_get_course_enabled(int $courseid): ?bool {
    global $DB;

    $record = $DB->get_record('local_datacurso_ratings_course_settings', ['courseid' => $courseid], 'enabled', IGNORE_MISSING);

    if ($record === false) {
        return null;
    }

    return (bool)$record->enabled;
}

/**
 * Get the list of module types that support activity ratings.
 *
 * @return string[] Module names (mod plugin short names).
 */
function local_datacurso_ratings_get_supported_modules(): array {
    return [
        'resource',
        'folder',
        'page',
        'url',
        'imscp',
        'book',
        'assign',
        'chat',
        'choice',
        'data',
        'feedback',
        'forum',
        'glossary',
        'lesson',
        'quiz',
        'scorm',
        'survey',
        'wiki',
        'workshop',
        'lti',
        'h5pactivity',
        'hvp',
    ];
}

/**
 * Check whether the given module type supports activity ratings.
 *
 * @param string $modname The module name (for example 'quiz').
 * @return bool True if ratings can be collected for this module type.
 */
function local_datacurso_ratings_is_module_supported(string $modname): bool {
    return in_array($modname, local_datacurso_ratings_get_supported_modules(), true);
}

/**
 * Get the effective maximum length for free-text student comments.
 *
 * The admin setting is a plain integer, so the value is bounded here: anything
 * below 1 falls back to the default and anything above 2000 is capped, keeping
 * the server-side truncation and the widget maxlength attribute in sync.
 *
 * @return int Effective limit, always within 1..2000.
 */
function local_datacurso_ratings_get_max_comment_length(): int {
    $configured = (int) get_config('local_datacurso_ratings', 'maxcommentlength');
    if ($configured < 1) {
        $configured = 200;
    }
    return min(2000, $configured);
}
