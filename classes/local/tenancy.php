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

namespace local_datacurso_ratings\local;

/**
 * Tenancy of Moodle Workplace, as the ratings see it.
 *
 * Ratings, feedback phrases and course settings are kept per tenant on Workplace. A site without
 * tool_tenant (a plain Moodle install, or the CI site that runs the tests) has a single implicit
 * tenant, 0, and the settings of the site.
 *
 * @package    local_datacurso_ratings
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tenancy {
    /** @var int Tenant of every user on a site without tenancy. */
    public const NO_TENANT = 0;

    /** @var string Component the tenant settings belong to. */
    private const COMPONENT = 'local_datacurso_ratings';

    /**
     * Tenant a user belongs to.
     *
     * @param int|null $userid User to resolve; defaults to the current user.
     * @return int The tenant, or self::NO_TENANT on a site without tenancy.
     */
    public static function get_tenant_id(?int $userid = null): int {
        global $USER;

        if (!class_exists(\tool_tenant\tenancy::class)) {
            return self::NO_TENANT;
        }

        return (int)\tool_tenant\tenancy::get_tenant_id($userid ?? (int)$USER->id);
    }

    /**
     * Whether ratings are switched on for a tenant.
     *
     * The tenant setting wins; a tenant that never saved one follows the setting of the site.
     *
     * @param int|null $tenantid Tenant to answer for; defaults to the tenant of the current user.
     * @return bool
     */
    public static function is_enabled(?int $tenantid = null): bool {
        $tenantid = $tenantid ?? self::get_tenant_id();
        $site = get_config(self::COMPONENT, 'enabled');

        if (!class_exists(\aiprovider_datacurso\local\tenant_config::class)) {
            return (bool)$site;
        }

        return (bool)\aiprovider_datacurso\local\tenant_config::get(self::COMPONENT, $tenantid, 'enabled', $site);
    }

    /**
     * Switch ratings on or off for a tenant.
     *
     * Without the tenant configuration of the provider it is the setting of the site.
     *
     * @param int $tenantid
     * @param bool $enabled
     */
    public static function set_enabled(int $tenantid, bool $enabled): void {
        if (!class_exists(\aiprovider_datacurso\local\tenant_config::class)) {
            set_config('enabled', (int)$enabled, self::COMPONENT);
            return;
        }

        \aiprovider_datacurso\local\tenant_config::set(self::COMPONENT, $tenantid, 'enabled', (int)$enabled);
    }
}
