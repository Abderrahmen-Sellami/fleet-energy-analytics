<?php

namespace App\Console\Commands;

use App\Services\SuggestionService;
use Illuminate\Console\Command;

/**
 * Détecte et enregistre les suggestions (SuggestionService::detecterEtEnregistrer) — planifiée
 * (cf. routes/console.php), ne s'exécute plus à chaque GET /api/suggestions.
 */
class DetecterSuggestions extends Command
{
    protected $signature = 'suggestions:detecter';
    protected $description = "Détecte et enregistre les suggestions (SuggestionService) ; GET /api/suggestions ne fait que les lire";

    public function handle(SuggestionService $service): int
    {
        $resultat = $service->detecterEtEnregistrer();
        $this->info(count($resultat) . ' suggestion(s) active(s) après détection.');
        return Command::SUCCESS;
    }
}
