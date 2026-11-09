<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Services\NoShowService;
use App\Support\PortalSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalSettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        $payload = [];

        foreach (PortalSettings::all() as $key => $definition) {
            $payload[PortalSettings::fieldName($key)] = match ($definition['type']) {
                PortalSettings::TYPE_MONEY => number_format($definition['default'] / 100, 2, '.', ''),
                PortalSettings::TYPE_BOOL => '1',
                default => $definition['default'],
            };
        }

        foreach ($overrides as $key => $value) {
            $payload[PortalSettings::fieldName($key)] = $value;
        }

        return $payload;
    }

    public function test_seller_saves_settings_with_money_converted_to_cents(): void
    {
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);

        $this->actingAs($seller)
            ->put(route('admin.settings.update'), $this->payload([
                'orders.no_show_block_threshold' => 3,
                'delivery.cost_cents' => '3,50',
                'delivery.time_hours' => 48,
                'store.name' => 'Bottega Rossi',
                'store.vat' => 'IT01234567890',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(3, Setting::get('orders.no_show_block_threshold'));
        $this->assertSame(350, Setting::get('delivery.cost_cents'));
        $this->assertSame(48, Setting::get('delivery.time_hours'));
        $this->assertSame('Bottega Rossi', Setting::get('store.name'));
        $this->assertTrue(ActivityLog::where('action', 'settings.updated')->exists());
    }

    public function test_changed_threshold_is_used_by_the_no_show_rule(): void
    {
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);
        $this->actingAs($seller)->put(route('admin.settings.update'), $this->payload(['orders.no_show_block_threshold' => 5]));

        $this->assertSame(5, app(NoShowService::class)->threshold());
    }

    public function test_invalid_values_are_rejected(): void
    {
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);

        $this->actingAs($seller)
            ->put(route('admin.settings.update'), $this->payload([
                'security.password_min_length' => 2,
                'notifications.reminder_time' => '25:99',
                'delivery.time_hours' => 36,
                'store.email' => 'non-una-email',
            ]))
            ->assertSessionHasErrors([
                'security__password_min_length',
                'notifications__reminder_time',
                'delivery__time_hours',
                'store__email',
            ]);
    }

    public function test_settings_and_activity_log_are_seller_only(): void
    {
        $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
        $collaborator->permissions()->create(['permission' => 'settings.manage']);

        foreach (['admin.settings.edit', 'admin.activity-log.index'] as $route) {
            $this->actingAs($collaborator)->get(route($route))->assertForbidden();
        }

        $this->actingAs($collaborator)->put(route('admin.settings.update'), $this->payload())->assertForbidden();
    }

    public function test_staff_catalog_changes_are_logged_but_customer_actions_are_not(): void
    {
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);

        $this->actingAs($seller);
        $category = Category::create(['name' => 'Ceramiche', 'slug' => 'ceramiche', 'is_active' => true]);
        $category->update(['name' => 'Ceramica artigianale']);

        $this->assertTrue(ActivityLog::where('action', 'category.created')->where('user_id', $seller->id)->exists());
        $log = ActivityLog::where('action', 'category.updated')->firstOrFail();
        $this->assertContains('name', $log->details['fields']);

        $this->actingAs(User::factory()->create());
        $category->update(['name' => 'Altro nome']);
        $this->assertSame(1, ActivityLog::where('action', 'category.updated')->count());
    }

    public function test_activity_log_page_lists_and_filters(): void
    {
        $seller = User::factory()->create(['role' => User::ROLE_SELLER]);
        ActivityLog::create(['user_id' => $seller->id, 'action' => 'order.status_changed', 'details' => ['to' => 'ready']]);
        ActivityLog::create(['user_id' => $seller->id, 'action' => 'product.created']);

        $this->actingAs($seller)->get(route('admin.activity-log.index', ['action' => 'order']))
            ->assertOk()
            ->assertSee('order.status_changed')
            ->assertDontSee('product.created');
    }
}
