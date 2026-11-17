<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccesoSistemaController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user?->canAccessPanel()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('auth/AccesoSistema');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:100'],
        ], [
            'email.required' => 'Debe ingresar el correo de acceso.',
            'email.email' => 'Ingrese un correo válido.',
            'password.required' => 'Debe ingresar la contraseña de acceso.',
        ]);

        $email = mb_strtolower(trim($validated['email']));
        $password = (string) $validated['password'];

        foreach (config('sistema.acceso') as $acceso) {
            $emailOk = hash_equals(mb_strtolower((string) $acceso['email']), $email);
            $claveOk = hash_equals((string) $acceso['password'], $password);

            if (! $emailOk || ! $claveOk) {
                continue;
            }

            $user = User::query()
                ->where('email', $acceso['email'])
                ->first();

            if (! $user || ! $user->is_active) {
                throw ValidationException::withMessages([
                    'email' => 'La cuenta de '.$acceso['etiqueta'].' no está disponible.',
                ]);
            }

            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->forget('url.intended');

            return redirect()
                ->route('dashboard')
                ->with('success', 'Bienvenida/o, '.$acceso['etiqueta'].'.');
        }

        throw ValidationException::withMessages([
            'email' => 'Correo o contraseña de acceso incorrectos.',
        ]);
    }

    public function cambiar(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('acceso.sistema');
    }
}
