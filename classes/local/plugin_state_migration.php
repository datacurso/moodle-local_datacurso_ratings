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

use aiprovider_datacurso\local\plugin_settings;
use aiprovider_datacurso\local\tenant_config;

/**
 * Moves the old "enabled" switch of the plugin into the Datacurso AI provider.
 *
 * Up to 1.1.1-wp the plugin had its own switch: a site setting (config_plugins) that tenants followed
 * until they saved their own value in the tenant configuration of the provider. The provider now
 * owns the only switch, per tenant. This copies every stored value into the provider, without
 * overwriting a value the provider already has (except a disabled old site value, which always wins over
 * the provider site value), and then deletes the old values.
 *
 * @package    local_datacurso_ratings
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_state_migration {
    /** @var string Component whose switch is migrated. */
    public const COMPONENT = 'local_datacurso_ratings';

    /** @var string Name of the old switch, in config_plugins and in the tenant configuration of the provider. */
    public const OLD_NAME = 'enabled';

    /** @var string Component that stores the switch of the provider. */
    private const PROVIDER = 'aiprovider_datacurso';

    /**
     * Copy the old switch values into the provider and delete them.
     *
     * @return void
     */
    public static function migrate(): void {
        if (class_exists(plugin_settings::class)) {
            self::copy_to_provider();
        }
        self::delete_old_values();
    }

    /**
     * Copy the site value and every stored tenant value into the provider.
     *
     * @return void
     */
    private static function copy_to_provider(): void {
        $tenantvalues = [];
        foreach (self::get_tenant_ids() as $tenantid) {
            $value = self::get_old_tenant_value($tenantid);
            if ($value !== null) {
                $tenantvalues[$tenantid] = $value;
            }
        }

        // Without tenancy the stored value of the implicit tenant 0 was the effective one, above the site setting.
        $sitevalue = $tenantvalues[tenancy::NO_TENANT] ?? get_config(self::COMPONENT, self::OLD_NAME);
        unset($tenantvalues[tenancy::NO_TENANT]);

        if ($sitevalue !== false && $sitevalue !== null && $sitevalue !== '') {
            // A disabled old site value always wins: the provider site value may only have been seeded ('1') by
            // the provider's own upgrade (plugin_settings::migrate_legacy_flags()) before an administrator switched
            // the old setting off, and keeping it would turn the plugin back on (RATINGS-MIG-001). An enabled old
            // value never overwrites a site value the provider already has.
            $providerhasvalue = get_config(self::PROVIDER, self::provider_name()) !== false;
            if (!$providerhasvalue || !(bool)$sitevalue) {
                plugin_settings::set_enabled(self::COMPONENT, (bool)$sitevalue);
            }
        }

        foreach ($tenantvalues as $tenantid => $value) {
            if (!self::provider_has_tenant_value($tenantid, (bool)$value)) {
                plugin_settings::set_enabled(self::COMPONENT, (bool)$value, $tenantid);
            }
        }
    }

    /**
     * Delete the old site setting and the old value of every tenant.
     *
     * @return void
     */
    private static function delete_old_values(): void {
        unset_config(self::OLD_NAME, self::COMPONENT);

        if (class_exists(tenant_config::class) && method_exists(tenant_config::class, 'delete_names')) {
            tenant_config::delete_names(self::COMPONENT, [self::OLD_NAME]);
        }
    }

    /**
     * Tenants that may have stored the old switch: the implicit tenant 0 and every Workplace tenant.
     *
     * @return int[]
     */
    private static function get_tenant_ids(): array {
        global $DB;

        $tenantids = [tenancy::NO_TENANT];
        if ($DB->get_manager()->table_exists('tool_tenant')) {
            $tenantids = array_merge($tenantids, array_map('intval', $DB->get_fieldset_sql('SELECT id FROM {tool_tenant}')));
        }

        return array_values(array_unique($tenantids));
    }

    /**
     * Old switch value stored for a tenant.
     *
     * @param int $tenantid
     * @return string|null The stored value, or null when the tenant stored none.
     */
    private static function get_old_tenant_value(int $tenantid): ?string {
        if (!class_exists(tenant_config::class) || !method_exists(tenant_config::class, 'get_stored')) {
            return null;
        }

        return tenant_config::get_stored(self::COMPONENT, $tenantid, self::OLD_NAME);
    }

    /**
     * Whether the provider already has a value for a tenant, which the migration must not overwrite.
     *
     * Without a way to read the stored tenant value, only a switched-off value is copied: the provider
     * keeps a plugin enabled by default, so copying an enabled value changes nothing.
     *
     * @param int $tenantid
     * @param bool $value The old value about to be copied.
     * @return bool
     */
    private static function provider_has_tenant_value(int $tenantid, bool $value): bool {
        if (class_exists(tenant_config::class) && method_exists(tenant_config::class, 'get_stored')) {
            return tenant_config::get_stored(self::PROVIDER, $tenantid, self::provider_name()) !== null;
        }

        return $value;
    }

    /**
     * Name of the switch of this plugin in the provider.
     *
     * @return string
     */
    private static function provider_name(): string {
        return 'plugin_enabled_' . self::COMPONENT;
    }
}
