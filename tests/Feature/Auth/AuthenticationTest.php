<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_after_login_customers_land_on_their_orders_and_staff_on_the_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('account.orders.index'));

        $this->actingAs(User::factory()->create(['role' => User::ROLE_SELLER]))
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard'));

        $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
        $collaborator->permissions()->create(['permission' => 'dashboard.view']);
        $this->actingAs($collaborator)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs(User::factory()->create(['role' => User::ROLE_COLLABORATOR]))
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.orders.index'));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
