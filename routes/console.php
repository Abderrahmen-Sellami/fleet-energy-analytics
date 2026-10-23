<?php

use App\Services\SanteAutomatisationsService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Les tâches critiques enregistrent leur dernier succès (->onSuccess()) pour le badge de santé
// (SanteAutomatisationsService). Appel direct à app(...) dans chaque closure : ce fichier peut être
// rechargé plusieurs fois dans un même process de tests, une fonction globale serait redéclarée.

// Météo du jour par site ; idempotente, ne rappelle pas l'API si les deux sites sont déjà archivés.
Schedule::command('meteo:archiver-jour')->cron('55 3-23/4 * * *')->onSuccess(fn () => app(SanteAutomatisationsService::class)->enregistrerSucces('meteo:archiver-jour'));

// Purge hebdomadaire des alertes vides et résolues.
Schedule::command('alertes:purger-vides')->weekly();

// Détection des alertes et suggestions : calculées ici, les endpoints ne font que lire l'état enregistré.
Schedule::command('alertes:detecter')->hourly();
Schedule::command('suggestions:detecter')->hourly();

// Synchronisation du registre tableur (onglet DATA) ; idempotente (updateOrCreate camion_id + date).
// Plusieurs créneaux le dimanche : un créneau manqué n'est jamais rejoué par le scheduler.
Schedule::command('sheets:sync-registre')->cron('0 8,11,14,17 * * 0')->onSuccess(fn () => app(SanteAutomatisationsService::class)->enregistrerSucces('sheets:sync-registre'));

// Sauvegarde de la base (config/backup.php) puis nettoyage : backup:clean ne garde que la plus récente
// de chaque jour.
Schedule::command('backup:run')->cron('30 3-23/4 * * *')->onSuccess(fn () => app(SanteAutomatisationsService::class)->enregistrerSucces('backup:run'));
Schedule::command('backup:clean')->cron('45 3-23/4 * * *');

// Vérifie chaque heure que les tâches critiques ci-dessus ont réussi récemment (marge ×2) et journalise
// un WARNING sinon ; même service que GET /api/sante-automatisations.
Schedule::command('automatisation:verifier-sante')->hourly();
