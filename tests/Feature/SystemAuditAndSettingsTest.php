<?php

namespace Tests\Feature;

use App\Core\Context\AppContext;
use App\Core\Enums\AuditAction;
use App\Core\Enums\CommonStatus;
use App\Modules\Auth\Models\User;
use App\Modules\Rbac\Services\RbacService;
use App\Modules\System\Models\AuditLog;
use App\Modules\System\Models\Setting;
use App\Modules\System\Services\SettingService;
use App\Modules\Tenant\Models\Store;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Models\TenantUser;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class SystemAuditAndSettingsTest extends TestCase
{
    use DatabaseTransactions;

    protected RbacService $rbacService;
    protected SettingService $settingService;
    protected Tenant $tenant;
    protected Store $store1;
    protected User $owner;
    protected User $cashier;
    protected array $roles;

    protected function setUp(): void
    {
        parent::setUp();

        AppContext::clear();
        $this->rbacService = app(RbacService::class);
        $this->settingService = app(SettingService::class);
        $this->seed(RbacSeeder::class);

        // Create Tenant
        $this->tenant = Tenant::create([
            'id' => (string) Str::ulid(),
            'name' => 'Kopi Surya Pagi',
            'slug' => 'surya-' . Str::random(4),
            'email' => 'surya_' . Str::random(5) . '@tenant.com',
            'status' => 'ACTIVE',
        ]);

        // Create Store
        $this->store1 = Store::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Outlet Senopati',
            'code' => 'SNP-' . Str::random(3),
            'timezone' => 'Asia/Jakarta',
            'status' => CommonStatus::ACTIVE,
        ]);

        $this->roles = $this->rbacService->createDefaultTenantRoles($this->tenant);

        // Owner (has all permissions including tenant.settings and audit.view)
        $this->owner = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Owner Surya',
            'email' => strtolower('owner_' . Str::random(5) . '@tenant.com'),
            'password' => 'secret123',
            'is_internal' => false,
            'is_active' => true,
        ]);

        TenantUser::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
        ]);

        $this->rbacService->assignRole($this->owner, $this->roles['Owner'], $this->tenant->id, null);

        // Cashier (restricted permissions)
        $this->cashier = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Kasir Surya',
            'email' => 'kasir_' . Str::random(5) . '@tenant.com',
            'password' => 'secret123',
            'is_internal' => false,
            'is_active' => true,
        ]);

        TenantUser::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->cashier->id,
        ]);

        $this->rbacService->assignRole($this->cashier, $this->roles['Cashier'], $this->tenant->id, $this->store1->id);
    }

    public function test_login_and_logout_auto_records_audit_log(): void
    {
        // Login
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $this->owner->email,
            'password' => 'secret123',
            'device_name' => 'pos-terminal-01',
        ]);

        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('data.token');

        // Verify LOGIN audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->owner->id,
            'action' => AuditAction::LOGIN->value,
            'module' => 'auth',
        ]);

        // Logout
        $logoutResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/logout');

        $logoutResponse->assertStatus(200);

        // Verify LOGOUT audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->owner->id,
            'action' => AuditAction::LOGOUT->value,
            'module' => 'auth',
        ]);
    }

    public function test_list_audit_logs_under_tenant_context(): void
    {
        // Record test audit log
        AuditLog::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
            'action' => AuditAction::LOGIN,
            'module' => 'auth',
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->getJson('/api/system/audit-logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'action', 'module', 'user_id', 'ip_address', 'created_at'],
                ],
                'pagination' => ['total', 'current_page', 'per_page'],
            ]);

        $this->assertGreaterThanOrEqual(1, $response->json('pagination.total'));
    }

    public function test_hierarchical_settings_resolution_decision_8(): void
    {
        // 1. System level default
        $this->settingService->set('tax_rate', '10.0', null, null);

        // System level resolution
        $resolved = $this->settingService->get('tax_rate', null, null);
        $this->assertEquals('10.0', $resolved);

        // 2. Tenant level override
        $this->settingService->set('tax_rate', '11.0', $this->tenant->id, null);

        // Tenant level resolution -> should override System
        $resolvedTenant = $this->settingService->get('tax_rate', null, $this->tenant->id);
        $this->assertEquals('11.0', $resolvedTenant);

        // 3. Store level override
        $this->settingService->set('tax_rate', '12.0', $this->tenant->id, $this->store1->id);

        // Store level resolution -> should override Tenant and System
        $resolvedStore = $this->settingService->get('tax_rate', $this->store1->id, $this->tenant->id);
        $this->assertEquals('12.0', $resolvedStore);

        // Non-existent key with default
        $defaultResult = $this->settingService->get('non_existent_key', $this->store1->id, $this->tenant->id, 'default_val');
        $this->assertEquals('default_val', $defaultResult);
    }

    public function test_upsert_and_show_setting_via_api(): void
    {
        // Set setting
        $response = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/system/settings', [
                'key' => 'receipt_footer',
                'value' => 'Terima kasih atas kunjungan Anda!',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.key', 'receipt_footer')
            ->assertJsonPath('data.value', 'Terima kasih atas kunjungan Anda!');

        // Query setting
        $showResponse = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->getJson('/api/system/settings/receipt_footer');

        $showResponse->assertStatus(200)
            ->assertJsonPath('data.value', 'Terima kasih atas kunjungan Anda!');
    }

    public function test_batch_update_settings_via_api(): void
    {
        $response = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/system/settings', [
                'settings' => [
                    'currency' => 'IDR',
                    'tax_inclusive' => 'true',
                    'service_charge_percentage' => '5.0',
                ],
            ]);

        $response->assertStatus(200);

        // List settings
        $listResponse = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->getJson('/api/system/settings');

        $listResponse->assertStatus(200)
            ->assertJsonPath('data.currency.value', 'IDR')
            ->assertJsonPath('data.tax_inclusive.value', 'true')
            ->assertJsonPath('data.service_charge_percentage.value', '5.0');
    }

    public function test_settings_forbidden_for_user_without_permission(): void
    {
        // Cashier lacks tenant.settings permission
        $response = $this->actingAs($this->cashier)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->getJson('/api/system/settings');

        $response->assertStatus(403);
    }
}
