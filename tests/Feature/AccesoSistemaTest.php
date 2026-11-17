<?php

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\UsuariosSeeder;

beforeEach(function (): void {
    $this->seed(PermissionsSeeder::class);
    $this->seed(UsuariosSeeder::class);
});

it('muestra la pantalla de acceso al sistema', function (): void {
    $this->get(route('acceso.sistema'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/AccesoSistema'));
});

it('inicia sesión de secretaria y entra al sistema', function (): void {
    $email = (string) config('sistema.acceso.secretaria.email');
    $password = (string) config('sistema.acceso.secretaria.password');

    $this->post(route('acceso.sistema.store'), [
        'email' => $email,
        'password' => $password,
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    expect(auth()->user()?->email)->toBe($email);
});

it('inicia sesión de presidente y entra al sistema', function (): void {
    $email = (string) config('sistema.acceso.presidente.email');
    $password = (string) config('sistema.acceso.presidente.password');

    $this->post(route('acceso.sistema.store'), [
        'email' => $email,
        'password' => $password,
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    expect(auth()->user()?->email)->toBe($email);
});

it('rechaza credenciales de acceso inválidas', function (): void {
    $this->from(route('acceso.sistema'))
        ->post(route('acceso.sistema.store'), [
            'email' => (string) config('sistema.acceso.secretaria.email'),
            'password' => 'incorrecta',
        ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('permite abrir el inicio de sesión normal por separado', function (): void {
    $this->get(route('login'))->assertOk();
});

it('permite iniciar sesión normal con correo', function (): void {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
});
