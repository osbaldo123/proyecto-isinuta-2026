<?php

namespace Tests\Feature\Settings;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);
    }

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create([
            'ci' => '12345678',
            'telefono_codigo' => '+591',
            'telefono' => '70000001',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/settings/profile');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/Profile')
                ->has('perfil')
                ->has('paisesTelefono')
            );
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create([
            'ci' => '12345678',
            'telefono_codigo' => '+591',
            'telefono' => '70000001',
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'ci' => '87654321',
                'telefono_codigo' => '+591',
                'telefono' => '71112233',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertSame('87654321', $user->ci);
        $this->assertSame('71112233', $user->telefono);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create([
            'ci' => '12345678',
            'telefono_codigo' => '+591',
            'telefono' => '70000001',
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'name' => 'Test User',
                'email' => $user->email,
                'ci' => '12345678',
                'telefono_codigo' => '+591',
                'telefono' => '70000001',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_presidente_can_update_own_profile_data()
    {
        $user = User::factory()->create([
            'name' => 'Enrique Sergio Nina',
            'email' => 'presidente@example.test',
            'ci' => '11223344',
            'telefono_codigo' => '+591',
            'telefono' => '70001122',
            'role' => Role::PRESIDENTE,
            'email_verified_at' => now(),
        ]);
        $role = Role::query()->where('nombre', Role::PRESIDENTE)->firstOrFail();
        $user->roles()->sync([$role->id]);

        $this->actingAs($user->fresh())
            ->patch('/settings/profile', [
                'name' => 'Enrique Sergio Nina Actualizado',
                'email' => 'presidente@example.test',
                'ci' => '99887766',
                'telefono_codigo' => '+591',
                'telefono' => '77778899',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $user->refresh();

        $this->assertSame('Enrique Sergio Nina Actualizado', $user->name);
        $this->assertSame('99887766', $user->ci);
        $this->assertSame('77778899', $user->telefono);
    }

    public function test_presidente_cannot_delete_own_account()
    {
        $user = User::factory()->create([
            'role' => Role::PRESIDENTE,
            'password' => bcrypt('password'),
        ]);
        $role = Role::query()->where('nombre', Role::PRESIDENTE)->firstOrFail();
        $user->roles()->sync([$role->id]);

        $this->actingAs($user->fresh())
            ->from('/settings/profile')
            ->delete('/settings/profile', [
                'password' => 'password',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotNull($user->fresh());
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create([
            'role' => Role::CONSULTA_PAGOS_MULTAS,
        ]);

        $response = $this
            ->actingAs($user)
            ->delete('/settings/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create([
            'role' => Role::CONSULTA_PAGOS_MULTAS,
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/settings/profile')
            ->delete('/settings/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/settings/profile');

        $this->assertNotNull($user->fresh());
    }
}
