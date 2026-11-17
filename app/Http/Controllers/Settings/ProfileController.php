<?php

namespace App\Http\Controllers\Settings;

use App\Enums\SouthAmericaPhoneCountry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'perfil' => [
                'name' => $user->name,
                'email' => $user->email,
                'ci' => (string) ($user->ci ?? ''),
                'telefono_codigo' => (string) ($user->telefono_codigo ?: '+591'),
                'telefono' => (string) ($user->telefono ?? ''),
                'role_label' => $user->roleDisplayLabel(),
            ],
            'paisesTelefono' => SouthAmericaPhoneCountry::options(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'ci' => $validated['ci'],
            'telefono_codigo' => $validated['telefono_codigo'],
            'telefono' => $validated['telefono'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return to_route('profile.edit')->with('success', 'Perfil actualizado correctamente.');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($user->isPresidente() || $user->isInstitutionalOperator() || $user->is_system_account) {
            return back()->with('error', 'Esta cuenta del sistema no puede eliminarse.');
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
