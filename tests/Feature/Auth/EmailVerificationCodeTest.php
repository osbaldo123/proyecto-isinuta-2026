<?php

use App\Mail\AfiliadoVerificacionMail;
use App\Models\User;
use App\Services\EmailVerificationCodeService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

it('verifica el correo con un código válido una sola vez', function (): void {
    Mail::fake();
    Event::fake([Verified::class]);

    $user = User::factory()->unverified()->create([
        'email' => 'afiliado@example.com',
    ]);

    $code = app(EmailVerificationCodeService::class)->issue($user);

    $this->actingAs($user)
        ->post(route('verification.code'), ['code' => $code])
        ->assertRedirect();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and($user->fresh()->email_verification_code)->toBeNull();

    Event::assertDispatched(Verified::class);

    $this->actingAs($user->fresh())
        ->post(route('verification.code'), ['code' => $code])
        ->assertRedirect(route('dashboard'));
});

it('rechaza un código inválido o expirado', function (): void {
    Mail::fake();

    $user = User::factory()->unverified()->create();
    app(EmailVerificationCodeService::class)->issue($user);

    $this->actingAs($user)
        ->post(route('verification.code'), ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('permite reenviar el código de verificación', function (): void {
    Mail::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post(route('verification.code.send'))
        ->assertRedirect();

    Mail::assertSent(AfiliadoVerificacionMail::class);
});

it('envía el código automáticamente al abrir la pantalla de verificación', function (): void {
    Mail::fake();

    $user = User::factory()->unverified()->create([
        'email' => 'verificar@example.com',
    ]);

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/VerifyEmail')
            ->where('email', 'verificar@example.com')
            ->where('status', 'verification-code-sent'));

    Mail::assertSent(AfiliadoVerificacionMail::class, 1);

    // Un refresh no debe reenviar (evita spam).
    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk();

    Mail::assertSent(AfiliadoVerificacionMail::class, 1);
});
