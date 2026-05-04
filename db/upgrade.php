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
 * Upgrade steps for Datacurso Ratings.
 *
 * @package    local_datacurso_ratings
 * @category   upgrade
 * @copyright  2025 Buendata
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Executes upgrade steps for the Datacurso Ratings plugin.
 *
 * @param int $oldversion The old version number.
 * @return bool True on success.
 */
function xmldb_local_datacurso_ratings_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // Upgrade step: Add courseid and categoryid fields to the ratings table.
    if ($oldversion < 2025100100) {
        $table = new xmldb_table('local_datacurso_ratings');

        // Add courseid field.
        $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'cmid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add categoryid field.
        $field = new xmldb_field('categoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'courseid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add indexes to improve query performance.
        $index1 = new xmldb_index('courseid_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        if (!$dbman->index_exists($table, $index1)) {
            $dbman->add_index($table, $index1);
        }

        $index2 = new xmldb_index('categoryid_idx', XMLDB_INDEX_NOTUNIQUE, ['categoryid']);
        if (!$dbman->index_exists($table, $index2)) {
            $dbman->add_index($table, $index2);
        }

        // Savepoint after structure modification.
        upgrade_plugin_savepoint(true, 2025100100, 'local', 'datacurso_ratings');
    }

    if ($oldversion < 2025101500) {
        // Define table local_datacurso_ratings_feedback to be created.
        $table = new xmldb_table('local_datacurso_ratings_feedback');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('feedbacktext', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Savepoint after table creation.
        upgrade_plugin_savepoint(true, 2025101500, 'local', 'datacurso_ratings');
    }

    if ($oldversion < 2025101504) {
        $table = new xmldb_table('local_datacurso_ratings_feedback');
        $field = new xmldb_field('type', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'like');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        } else {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2025101504, 'local', 'datacurso_ratings');
    }

    if ($oldversion < 2025122910) {
        $table = new xmldb_table('local_datacurso_ratings_feedback');
        $field = new xmldb_field('tenant_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'type');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2025122910, 'local', 'datacurso_ratings');
    }

    if ($oldversion < 2025122920) {
        $table = new xmldb_table('local_datacurso_ratings');
        $field = new xmldb_field('tenant_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'userid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2025122920, 'local', 'datacurso_ratings');
    }

    if ($oldversion < 2026050400) {
        $table = new xmldb_table('local_datacurso_ratings_course_settings');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('tenant_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid_tenant_uix', XMLDB_KEY_UNIQUE, ['courseid', 'tenant_id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026050400, 'local', 'datacurso_ratings');
    }

    return true;
}
