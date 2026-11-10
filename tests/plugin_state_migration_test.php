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

namespace local_datacurso_ratings;

use aiprovider_datacurso\local\plugin_settings;
use aiprovider_datacurso\local\tenant_config;
use local_datacurso_ratings\fixtures\provider_dependency_trait;
use local_datacurso_ratings\local\plugin_state_migration;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/provider_dependency_trait.php');

/**
 * Tests the upgrade step that moves the old "enabled" switch into the Datacurso AI provider.
 *
 * @package    local_datacurso_ratings
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_datacurso_ratings\local\plugin_state_migration
 */
final class plugin_state_migration_test extends \advanced_testcase {
    use provider_dependency_trait;

    /**
     * Skip when the provider, or its tenant configuration, is not installed.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->require_provider();
        if (!class_exists(tenant_config::class)) {
            $this->markTestSkipped('Needs the tenant configuration of the Workplace provider.');
        }
    }

    /**
     * The value a tenant saved is copied to that tenant in the provider, then the old values are deleted.
     *
     * @return void
     */
    public function test_upgrade_copies_tenant_values_into_the_provider(): void {
        if (!class_exists(\tool_tenant\tenancy::class)) {
            $this->markTestSkipped('Tenants exist only on Moodle Workplace.');
        }
        $this->resetAfterTest();
        $tenants = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenanta = (int)$tenants->create_tenant(['name' => 'Tenant A'])->id;
        $tenantb = (int)$tenants->create_tenant(['name' => 'Tenant B'])->id;
        $tenantc = (int)$tenants->create_tenant(['name' => 'Tenant C'])->id;
        unset_config('plugin_enabled_local_datacurso_ratings', 'aiprovider_datacurso');
        set_config('enabled', 1, 'local_datacurso_ratings');
        tenant_config::set('local_datacurso_ratings', $tenanta, 'enabled', 0);
        tenant_config::set('local_datacurso_ratings', $tenantb, 'enabled', 1);

        plugin_state_migration::migrate();

        $this->assertFalse(plugin_settings::is_enabled('local_datacurso_ratings', $tenanta));
        $this->assertTrue(plugin_settings::is_enabled('local_datacurso_ratings', $tenantb));
        $this->assertTrue(plugin_settings::is_enabled('local_datacurso_ratings', $tenantc), 'Tenant C follows the site value.');
        $this->assertFalse(get_config('local_datacurso_ratings', 'enabled'));
        $this->assertNull(tenant_config::get_stored('local_datacurso_ratings', $tenanta, 'enabled'));
        $this->assertNull(tenant_config::get_stored('local_datacurso_ratings', $tenantb, 'enabled'));
    }

    /**
     * A site that switched the plugin off keeps it off for every tenant that did not save its own value.
     *
     * @return void
     */
    public function test_upgrade_copies_the_site_value_as_the_provider_default(): void {
        $this->resetAfterTest();
        unset_config('plugin_enabled_local_datacurso_ratings', 'aiprovider_datacurso');
        set_config('enabled', 0, 'local_datacurso_ratings');

        plugin_state_migration::migrate();

        $this->assertFalse(plugin_settings::is_enabled('local_datacurso_ratings', 4242));
        $this->assertFalse(get_config('local_datacurso_ratings', 'enabled'));
    }

    /**
     * A value the provider already has for a tenant is not overwritten by the old value.
     *
     * @return void
     */
    public function test_upgrade_does_not_overwrite_a_provider_tenant_value(): void {
        if (!class_exists(\tool_tenant\tenancy::class)) {
            $this->markTestSkipped('Tenants exist only on Moodle Workplace.');
        }
        $this->resetAfterTest();
        $tenants = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenanta = (int)$tenants->create_tenant(['name' => 'Tenant A'])->id;
        plugin_settings::set_enabled('local_datacurso_ratings', true, $tenanta);
        tenant_config::set('local_datacurso_ratings', $tenanta, 'enabled', 0);

        plugin_state_migration::migrate();

        $this->assertTrue(plugin_settings::is_enabled('local_datacurso_ratings', $tenanta));
        $this->assertNull(tenant_config::get_stored('local_datacurso_ratings', $tenanta, 'enabled'));
    }

    /**
     * RATINGS-MIG-001: a disabled old site value wins over a provider site value, which may only have been
     * seeded ('1') by the provider's own upgrade, so tenants without their own value stay disabled.
     *
     * @return void
     */
    public function test_upgrade_disabled_site_value_wins_over_the_provider_site_value(): void {
        $this->resetAfterTest();
        set_config('plugin_enabled_local_datacurso_ratings', '1', 'aiprovider_datacurso');
        set_config('enabled', '0', 'local_datacurso_ratings');

        plugin_state_migration::migrate();

        $this->assertFalse(plugin_settings::is_enabled('local_datacurso_ratings', 4242));
        $this->assertSame('0', get_config('aiprovider_datacurso', 'plugin_enabled_local_datacurso_ratings'));
        $this->assertFalse(get_config('local_datacurso_ratings', 'enabled'));
    }

    /**
     * An enabled old site value does not overwrite a site value the provider already has.
     *
     * @return void
     */
    public function test_upgrade_enabled_site_value_does_not_overwrite_the_provider_site_value(): void {
        $this->resetAfterTest();
        plugin_settings::set_enabled('local_datacurso_ratings', false);
        set_config('enabled', '1', 'local_datacurso_ratings');

        plugin_state_migration::migrate();

        $this->assertFalse(plugin_settings::is_enabled('local_datacurso_ratings', 4242));
        $this->assertFalse(get_config('local_datacurso_ratings', 'enabled'));
    }
}
