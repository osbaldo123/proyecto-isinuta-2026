<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        // En el navegador: volver al panel con aviso, en lugar del modal 403 de Inertia.
        if ($request->header('X-Inertia') && $user->canAccessPanel() && ! $request->routeIs('dashboard', 'panel')) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'No tiene permiso para acceder a esa sección.');
        }

        abort(403);
    }
}
