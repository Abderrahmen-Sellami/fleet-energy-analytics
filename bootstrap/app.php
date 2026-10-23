<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Ajout explicite (cookies chiffrés avant StartSession, dans cet ordre) puis du middleware
        // d'authentification lui-même, qui répond en JSON 401 sur /api/* (jamais une redirection
        // HTML)
        $middleware->alias([
            'acces.partage' => \App\Http\Middleware\Authentifie::class,
            'role.admin' => \App\Http\Middleware\RequiertAdmin::class,
        ]);
        $middleware->api(prepend: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
        ]);
        $middleware->api(append: [\App\Http\Middleware\Authentifie::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Empêche findOrFail() de laisser remonter la trace de debug Laravel sur les endpoints
        // API.: toute erreur de ressource introuvable doit renvoyer un JSON explicite.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            if ($e->getPrevious() instanceof ModelNotFoundException) {
                $prev = $e->getPrevious();
                $modele = class_basename($prev->getModel());
                $ids = implode(', ', $prev->getIds());

                return response()->json([
                    'message' => "{$modele} introuvable (id: {$ids}).",
                ], 404);
            }

            return response()->json([
                'message' => 'Route ou identifiant invalide.',
            ], 404);
        });

        // 405 (méthode non permise, ex: POST sur une route en lecture seule) : même traitement que
        // le 404 — sans ce callback, le rendu JSON par défaut de Laravel en APP_DEBUG=true
        // renvoyait exception/file/ line/trace (≈10 Ko, chemins vendor\ du serveur)
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => "Méthode {$request->method()} non permise pour cette route.",
            ], 405, array_intersect_key($e->getHeaders(), ['Allow' => true]));
        });
    })->create();
