<?php

use App\Http\Middleware\EnsureSystemAccess;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'permission' => EnsureUserHasPermission::class,
            'sistema.acceso' => EnsureSystemAccess::class,
        ]);

        $middleware->redirectUsersTo(function (Request $request) {
            $user = $request->user();

            return $user && method_exists($user, 'canAccessPanel') && $user->canAccessPanel()
                ? '/dashboard'
                : '/';
        });

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            return redirect()
                ->route('login')
                ->with('error', 'La sesión expiró. Vuelve a iniciar sesión.');
        });
    })->create();
