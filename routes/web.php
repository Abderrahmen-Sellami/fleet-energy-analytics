<?php

use App\Http\Controllers\ConnexionController;
use Illuminate\Support\Facades\Route;

// Verrou d'accès minimal : écran de connexion hors du groupe protégé.
Route::redirect('/', '/dashboard');
Route::get('/connexion', [ConnexionController::class, 'afficher']);
Route::post('/connexion', [ConnexionController::class, 'connecter']);
Route::post('/deconnexion', [ConnexionController::class, 'deconnecter']);

Route::middleware('acces.partage')->group(function () {
    Route::get('/dashboard', fn () => view('dashboard'));
    Route::get('/direction', fn () => view('direction'));
    Route::get('/audit-iso50001', [App\Http\Controllers\AuditController::class, 'index']);
});

// Gestion des utilisateurs — Admin uniquement, en plus de l'authentification déjà requise
// ci-dessus.
Route::middleware(['acces.partage', 'role.admin'])->group(function () {
    Route::get('/utilisateurs', fn () => view('utilisateurs'));
});
