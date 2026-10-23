<?php

namespace App\Console\Commands;

use App\Services\SanteAutomatisationsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

// Cette commande ne fait que journaliser un WARNING explicite par tâche en retard — la lecture "à
// la demande" (dashboard, /api/sante-automatisations) passe par le même
// SanteAutomatisationsService, donc jamais de divergence entre ce que log cette commande et ce
// qu'affiche le badge.
class VerifierSanteAutomatisations extends Command
{
    protected $signature = 'automatisation:verifier-sante';

    protected $description = "Vérifie que chaque tâche planifiée critique a bien tourné avec succès récemment (marge ×2 sur sa fréquence attendue) et journalise un WARNING pour toute tâche en retard";

    public function handle(SanteAutomatisationsService $service): int
    {
        $statuts = $service->verifierToutes();
        $enRetard = array_filter($statuts, fn (array $s) => $s['en_retard']);

        if (!$enRetard) {
            $this->info('Toutes les tâches planifiées critiques sont à jour.');
            return Command::SUCCESS;
        }

        foreach ($enRetard as $s) {
            $derniere = $s['derniere_execution_reussie'] ?? 'jamais exécutée avec succès';
            $message = "automatisation:verifier-sante — tâche en retard : {$s['tache']} (dernier succès : {$derniere}, seuil : {$s['seuil_retard_minutes']} min)."
                . (!empty($s['motif']) ? " {$s['motif']}" : '');

            $this->warn($message);
            Log::warning($message, $s);
        }

        $this->warn(count($enRetard) . ' tâche(s) en retard sur ' . count($statuts) . '.');

        // Toujours Command::SUCCESS, même avec des tâches en retard : le rôle de cette commande est
        // de journaliser/exposer le retard (log + /api/sante-automatisations), pas de faire échouer
        // bruyamment le cron de CETTE vérification elle-même — la détection a bien fonctionné.
        return Command::SUCCESS;
    }
}
