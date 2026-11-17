<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_a_successful_response()
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Welcome'));
    }

    public function test_authenticated_users_are_redirected_to_dashboard()
    {
        $this->seed(PermissionsSeeder::class);

        $user = User::factory()->create(['role' => Role::ADMIN]);
        $role = Role::query()->where('nombre', Role::ADMIN)->firstOrFail();
        $user->roles()->sync([$role->id]);

        $response = $this->actingAs($user->fresh())->get('/');

        $response->assertRedirect(route('dashboard'));
    }
}
