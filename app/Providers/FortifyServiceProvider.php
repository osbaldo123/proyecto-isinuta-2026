<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\TwoFactorLoginResponse;
use App\Models\User;
use App\Support\EmailVerificationPrompt;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        LoginResponseContract::class => LoginResponse::class,
        TwoFactorLoginResponseContract::class => TwoFactorLoginResponse::class,
        CreatesNewUsers::class => CreateNewUser::class,
        ResetsUserPasswords::class => ResetUserPassword::class,
        UpdatesUserPasswords::class => UpdateUserPassword::class,
        UpdatesUserProfileInformation::class => UpdateUserProfileInformation::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));
        Fortify::requestPasswordResetLinkView(fn () => Inertia::render('auth/ForgotPassword'));
        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));
        Fortify::verifyEmailView(fn (Request $request) => Inertia::render(
            'auth/VerifyEmail',
            EmailVerificationPrompt::renderProps($request),
        ));
        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));

        // Cada nuevo login debe poder disparar un envío automático del código.
        Event::listen(Login::class, fn () => EmailVerificationPrompt::forgetAutoSendFlag());

        Fortify::authenticateUsing(function (Request $request) {
            $login = trim((string) $request->input('email', ''));
            $password = (string) $request->input('password', '');

            if ($login === '' || $password === '') {
                return null;
            }

            // Mientras los usuarios de afiliados estén desactivados, sus cuentas no pueden ingresar.
            $soloPersonal = ! config('sistema.usuarios_afiliados');

            // Correo normal, o CI de 7–10 dígitos (consulta de afiliado).
            $user = User::query()
                ->where('email', $login)
                ->when($soloPersonal, fn ($query) => $query->whereNull('afiliado_id'))
                ->first();
            if (! $user && preg_match('/^\d{7,10}$/', $login) === 1) {
                $user = User::query()
                    ->where('ci', $login)
                    ->when($soloPersonal, fn ($query) => $query->whereNull('afiliado_id'))
                    ->first();
            }

            if ($user && Hash::check($password, $user->password)) {
                if (! $user->is_active) {
                    throw ValidationException::withMessages([
                        Fortify::username() => 'Su cuenta se encuentra inactiva. Contacte al administrador.',
                    ]);
                }

                return $user;
            }

            return null;
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = str($request->input('email'))
                ->lower()
                ->append('|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
