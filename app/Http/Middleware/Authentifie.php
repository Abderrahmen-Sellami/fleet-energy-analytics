<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Comportement HTTP inchangé pour l'appelant : JSON 401 explicite sur /api/* (jamais une
 * redirection HTML que fetch() suivrait silencieusement en recevant une page de login à la place
 * d'un JSON), redirection vers /connexion avec la page d'origine en paramètre `retour` pour les
 * pages web.
 */
class Authentifie
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->actif) {
            return $next($request);
        }

        if ($user && !$user->actif) {
            Auth::logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
            }
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => 'Authentification requise.'], 401);
        }

        $cible = $request->isMethod('GET')
            ? '/connexion?retour='.urlencode($request->getRequestUri())
            : '/connexion';

        return redirect($cible);
    }
}
