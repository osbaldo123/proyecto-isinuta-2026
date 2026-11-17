<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tras el login se va al panel (o al inicio), no a una URL "intended"
 * que el rol podría no poder abrir (p. ej. /usuarios → 403).
 */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        // No reutilizar url.intended (p. ej. /usuarios): el rol podría no tener permiso → 403.
        $request->session()->forget('url.intended');

        $redirectTo = route('dashboard');

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false], 200)
            : redirect()->to($redirectTo);
    }
}
