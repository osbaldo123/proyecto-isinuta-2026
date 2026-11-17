<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EmailVerificationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationCodeController extends Controller
{
    public function __construct(
        private readonly EmailVerificationCodeService $verificationCodes,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $this->verificationCodes->verify($user, $validated['code']);

        return redirect()
            ->intended(route('dashboard', absolute: false).'?verified=1')
            ->with('success', 'Correo verificado correctamente.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        $this->verificationCodes->issue($user);

        return back()->with('status', 'verification-code-sent');
    }
}
