<?php

use App\Http\Controllers\Api\CamionController;
use App\Http\Controllers\Api\SerStandardsController;
use App\Http\Controllers\Api\SerDynamiqueController;
use App\Http\Controllers\Api\ComparateurRegistreController;
use App\Http\Controllers\Api\CourbesController;
use App\Http\Controllers\Api\ProjectionController;
use App\Http\Controllers\Api\SuggestionController;
use App\Http\Controllers\Api\PrixCarburantController;
use App\Http\Controllers\Api\SerNormeRefHistoriqueController;
use App\Http\Controllers\Api\PositionTempsReelController;
use App\Http\Controllers\Api\PositionTempsReelSourceBController;
use App\Http\Controllers\Api\PositionHistoriqueController;
use App\Http\Controllers\Api\PositionHistoriqueSourceBController;
use App\Http\Controllers\Api\ExportCsvController;
use App\Http\Controllers\Api\SanteAutomatisationsController;
use App\Http\Controllers\Api\UtilisateurController;
use Illuminate\Support\Facades\Route;

Route::get('/camions', [CamionController::class, 'index']);
Route::get('/camions/{id}/mesures', [CamionController::class, 'mesures'])->where('id', '[0-9]+');
Route::get('/flotte/resume', [CamionController::class, 'resumeFlotte']);
Route::get('/alertes', [CamionController::class, 'alertes']);
Route::get('/alertes/types', [CamionController::class, 'typesAlertes']);
Route::get('/comparateur-sites', [CamionController::class, 'comparateurSites']);
Route::get('/meteo', [CamionController::class, 'meteo']);
Route::get('/prix-carburant', [PrixCarburantController::class, 'index']);

Route::get('/ser-standards', [SerStandardsController::class, 'index']);
Route::get('/ser-historique/{camion_id}', [SerNormeRefHistoriqueController::class, 'index'])->where('camion_id', '[0-9]+');
Route::get('/analyses-correctives/{camion_id}', [CamionController::class, 'analysesCorrectives'])->where('camion_id', '[0-9]+');
Route::get('/consommation-source_b/{camion_id}', [CamionController::class, 'consommationSourceB'])->where('camion_id', '[0-9]+');
Route::get('/kilometrage-source_b/{camion_id}', [CamionController::class, 'kilometrageSourceB'])->where('camion_id', '[0-9]+');
Route::get('/ser-dynamique', [SerDynamiqueController::class, 'index']);
Route::get('/comparateur-registre/{id}', [ComparateurRegistreController::class, 'show'])->where('id', '[0-9]+');
Route::get('/courbes/metriques', [CourbesController::class, 'metriquesDisponibles']);
Route::get('/courbes/{id}/mensuel/{metrique}', [CourbesController::class, 'mensuel'])->where('id', '[0-9]+');
Route::get('/courbes/{id}/annuel/{metrique}', [CourbesController::class, 'annuel'])->where('id', '[0-9]+');
Route::get('/projection/{camion_id}/{metrique}', [ProjectionController::class, 'index'])->where('camion_id', '[0-9]+');
Route::get('/suggestions', [SuggestionController::class, 'index']);
Route::get('/positions-temps-reel', [PositionTempsReelController::class, 'index']);
Route::get('/positions-temps-reel-source_b', [PositionTempsReelSourceBController::class, 'index']);
Route::get('/positions-historique/{camion_id}', [PositionHistoriqueController::class, 'index'])->where('camion_id', '[0-9]+');
Route::get('/positions-historique-source_b/{camion_id}', [PositionHistoriqueSourceBController::class, 'index'])->where('camion_id', '[0-9]+');

Route::get('/export/fiche-camion/{id}', [ExportCsvController::class, 'ficheCamion'])->where('id', '[0-9]+');
Route::get('/export/courbes/{id}', [ExportCsvController::class, 'courbes'])->where('id', '[0-9]+');

Route::get('/sante-automatisations', [SanteAutomatisationsController::class, 'index']);

// `acces.partage` (authentification) est déjà appliqué globalement au groupe `api`
// (bootstrap/app.php) : `role.admin` s'ajoute ici, pas à sa place.
Route::middleware('role.admin')->group(function () {
    Route::get('/utilisateurs', [UtilisateurController::class, 'index']);
    Route::post('/utilisateurs', [UtilisateurController::class, 'store']);
    Route::patch('/utilisateurs/{id}', [UtilisateurController::class, 'update'])->where('id', '[0-9]+');
});