<?php

namespace App\Support;

use App\Services\EmailVerificationCodeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Al mostrar la pantalla de verificación, envía el código automáticamente
 * una vez por sesión de login (sin esperar al botón "Reenviar").
 */
class EmailVerificationPrompt
{
    public const SESSION_KEY = 'verification_code_auto_sent';

    /**
     * @return array{status: string|null, email: string|null, error: string|null}
     */
    public static function renderProps(Request $request): array
    {
        $user = $request->user();
        $status = $request->session()->get('status');
        $error = null;

        if ($user && ! $user->hasVerifiedEmail() && ! $request->session()->get(self::SESSION_KEY)) {
            try {
                app(EmailVerificationCodeService::class)->issue($user);
                $request->session()->put(self::SESSION_KEY, true);
                $status = 'verification-code-sent';
            } catch (ValidationException $e) {
                $error = collect($e->errors())->flatten()->first();
            }
        }

        return [
            'status' => $status,
            'email' => $user?->email,
            'error' => $error,
        ];
    }

    public static function forgetAutoSendFlag(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
