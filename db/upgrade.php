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

    if ($oldversion < 2026092401) {
        // Privacy provider now scopes ratings to module contexts and declares the
        // Datacurso AI external location; db/events.php registers cleanup observers.
        // db/access.php adds local/datacurso_ratings:rate, moves the AI analysis
        // capabilities to course/module context and drops the unused
        // viewgeneralreport and generateanalysisgeneral capabilities.
        // No schema change: the savepoint refreshes the privacy metadata, event and
        // capability caches.
        upgrade_plugin_savepoint(true, 2026092401, 'local', 'datacurso_ratings');
    }

    if ($oldversion < 2026100700) {
        // 1.1.0-wp joins two schemas. A site that ran 1.0.4-wp has tenant_id on the ratings and the
        // feedback phrases, but a fresh install of it declared the column NOT NULL without a
        // default, so code that does not set it cannot insert. A site that ran the plugin without
        // Workplace (main, 1.1.0) has no tenant_id at all and one course setting per course. Both
        // end with tenant_id NOT NULL DEFAULT 0, which also serves as the shared tenant.
        $tenantfields = [
            'local_datacurso_ratings' => 'userid',
            'local_datacurso_ratings_feedback' => 'type',
        ];
        foreach ($tenantfields as $tablename => $previous) {
            $table = new xmldb_table($tablename);
            $field = new xmldb_field('tenant_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', $previous);
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_default($table, $field);
            } else {
                $dbman->add_field($table, $field);
            }
        }

        // Course settings: one per course and tenant, as on Workplace.
        $table = new xmldb_table('local_datacurso_ratings_course_settings');
        $field = new xmldb_field('tenant_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'courseid');
        if (!$dbman->field_exists($table, $field)) {
            $oldkey = new xmldb_key('courseid_uix', XMLDB_KEY_UNIQUE, ['courseid']);
            $dbman->drop_key($table, $oldkey);
            $dbman->add_field($table, $field);
            $dbman->add_key($table, new xmldb_key('courseid_tenant_uix', XMLDB_KEY_UNIQUE, ['courseid', 'tenant_id']));
        }

        // Ratings saved without a tenant belong to the tenant of the person who rated, when the
        // site has tenants. Feedback phrases and course settings saved without one stay shared.
        if ($dbman->table_exists('tool_tenant_user')) {
            $DB->execute("
                UPDATE {local_datacurso_ratings}
                   SET tenant_id = (SELECT MAX(tu.tenantid)
                                      FROM {tool_tenant_user} tu
                                     WHERE tu.userid = {local_datacurso_ratings}.userid)
                 WHERE tenant_id = 0
                   AND EXISTS (SELECT 1 FROM {tool_tenant_user} tu2 WHERE tu2.userid = {local_datacurso_ratings}.userid)
            ");
        }

        upgrade_plugin_savepoint(true, 2026100700, 'local', 'datacurso_ratings');
    }

    return true;
}
