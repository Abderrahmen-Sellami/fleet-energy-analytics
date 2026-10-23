<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate du rôle Admin — appliqué EN PLUS de `acces.partage` (jamais seul : suppose qu'Auth::user()
 * existe déjà), sur les routes de gestion des utilisateurs — la seule vraie surface d'action.
 */
class RequiertAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->estAdmin()) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => 'Accès réservé aux administrateurs.'], 403);
        }

        abort(403, 'Accès réservé aux administrateurs.');
    }
}
