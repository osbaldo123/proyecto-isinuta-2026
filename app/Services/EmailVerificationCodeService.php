<?php

namespace App\Services;

use App\Mail\AfiliadoVerificacionMail;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmailVerificationCodeService
{
    public const CODE_LENGTH = 6;

    public const CODE_TTL_MINUTES = 30;

    /**
     * Genera un código de un solo uso, lo guarda hasheado y envía el correo.
     */
    public function issue(User $user, ?string $plainPassword = null): string
    {
        $code = $this->generateCode();

        $user->forceFill([
            'email_verification_code' => Hash::make($code),
            'email_verification_code_expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ])->save();

        try {
            Mail::to($user->email)->send(new AfiliadoVerificacionMail(
                user: $user,
                code: $code,
                plainPassword: $plainPassword,
            ));
        } catch (Throwable $e) {
            Log::error('No se pudo enviar el correo de verificación.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            // No bloquear el registro/actualización del afiliado por fallo de correo.
            // El código queda guardado para reenvío posterior.
        }

        return $code;
    }

    public function verify(User $user, string $code): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        $code = trim($code);

        if (
            ! $user->email_verification_code
            || ! $user->email_verification_code_expires_at
            || $user->email_verification_code_expires_at->isPast()
            || ! Hash::check($code, $user->email_verification_code)
        ) {
            throw ValidationException::withMessages([
                'code' => 'El código de verificación no es válido o ha expirado.',
            ]);
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_code_expires_at' => null,
        ])->save();

        event(new Verified($user));
    }

    public function clear(User $user): void
    {
        $user->forceFill([
            'email_verification_code' => null,
            'email_verification_code_expires_at' => null,
        ])->save();
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }
}
