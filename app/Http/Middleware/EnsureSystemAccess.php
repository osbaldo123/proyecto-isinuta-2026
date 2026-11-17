<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSystemAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            return $next($request);
        }

        if ($request->session()->get('sistema_acceso') === true) {
            return $next($request);
        }

        return redirect()->route('acceso.sistema');
    }
}
