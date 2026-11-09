<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('account.edit'))->assertOk();
    }

    public function test_account_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('account.update'), [
                'current_password' => 'password',
                'first_name' => 'Luca',
                'last_name' => 'Bianchi',
                'email' => $user->email,
                'locale' => 'it',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('account.edit'));

        $this->assertSame('Luca', $user->fresh()->first_name);
    }

    public function test_account_update_requires_correct_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('account.update'), [
                'current_password' => 'sbagliata',
                'first_name' => 'Luca',
                'last_name' => 'Bianchi',
                'email' => $user->email,
                'locale' => 'it',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertNotSame('Luca', $user->fresh()->first_name);
    }
}
