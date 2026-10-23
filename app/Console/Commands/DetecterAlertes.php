<?php

namespace App\Console\Commands;

use App\Services\AlerteService;
use Illuminate\Console\Command;

/**
 * Détecte et enregistre les alertes (AlerteService::detecterEtEnregistrer) — planifiée (cf.
 * routes/console.php), ne s'exécute plus à chaque GET /api/alertes.
 */
class DetecterAlertes extends Command
{
    protected $signature = 'alertes:detecter';
    protected $description = "Détecte et enregistre les alertes (AlerteService) ; GET /api/alertes ne fait que les lire";

    public function handle(AlerteService $service): int
    {
        $service->detecterEtEnregistrer();
        $this->info('Détection des alertes terminée.');
        return Command::SUCCESS;
    }
}
