<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create([
            'email' => 'demo@isinuta.test',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_authenticate_with_email_and_password_only()
    {
        $user = User::factory()->create([
            'name' => 'Maria Lopez Demo',
            'email' => 'maria.demo@isinuta.test',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'maria.demo@isinuta.test',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create([
            'email' => 'clave.incorrecta@isinuta.test',
            'is_active' => true,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_inactive_users_cannot_authenticate()
    {
        $user = User::factory()->create([
            'email' => 'inactivo@isinuta.test',
            'is_active' => true,
        ]);
        $user->forceFill(['is_active' => false])->save();

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => 'Su cuenta se encuentra inactiva. Contacte al administrador.',
        ]);
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
