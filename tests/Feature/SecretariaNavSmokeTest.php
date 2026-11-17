<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretariaNavSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretaria_can_open_main_menu_pages(): void
    {
        $this->seed();

        $user = User::query()
            ->where('email', (string) config('sistema.acceso.secretaria.email'))
            ->firstOrFail();

        $paths = [
            '/dashboard',
            '/reportes',
            '/otb',
            '/servicios',
            '/configuracion',
            '/afiliados',
            '/pagos',
            '/multas',
        ];

        foreach ($paths as $path) {
            $this->actingAs($user)
                ->get($path)
                ->assertOk();
        }

        $this->actingAs($user)->get('/roles')->assertForbidden();
        $this->actingAs($user)->get('/usuarios')->assertForbidden();
    }
}
