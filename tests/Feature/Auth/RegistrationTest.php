<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'email' => 'test@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'consent' => '1', 'locale' => 'it',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));
        $this->assertSame(User::ROLE_CUSTOMER, User::where('email', 'test@example.com')->firstOrFail()->role);
    }

    public function test_registration_requires_consent(): void
    {
        $this->post('/register', [
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'email' => 'test@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertSessionHasErrors('consent');

        $this->assertGuest();
    }
}
