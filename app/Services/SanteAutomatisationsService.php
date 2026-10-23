<?php

namespace App\Services;

use App\Models\AutomatisationSante;
use Illuminate\Support\Carbon;

// Surveillance des tâches planifiées critiques : chaque tâche enregistre son dernier succès
// (->onSuccess() dans routes/console.php) et ce service le compare à sa fréquence attendue, avec une
// marge de tolérance ×2 pour ne pas signaler un simple retard d'exécution mineur.
class SanteAutomatisationsService
{
    // Fréquence attendue, en minutes — à garder synchronisée avec routes/console.php.
    public const FREQUENCES_MINUTES = [
        'sheets:sync-registre' => 10080, // hebdomadaire
        'meteo:archiver-jour' => 1440,   // quotidienne
        'backup:run' => 1440,            // quotidienne
    ];

    private const MARGE_TOLERANCE = 2;

    public function enregistrerSucces(string $tache, ?Carbon $quand = null): void
    {
        AutomatisationSante::updateOrCreate(
            ['tache' => $tache],
            ['derniere_execution_reussie' => $quand ?? now()]
        );
    }

    // Un statut par tâche connue ; une tâche qui n'a encore jamais réussi est en retard par construction
    // (jamais un état « sain » faute de donnée).
    public function verifierToutes(): array
    {
        $dernieresExecutions = AutomatisationSante::whereIn('tache', array_keys(self::FREQUENCES_MINUTES))
            ->pluck('derniere_execution_reussie', 'tache');

        return collect(self::FREQUENCES_MINUTES)->map(function (int $frequenceMinutes, string $tache) use ($dernieresExecutions) {
            $derniereExecution = $dernieresExecutions[$tache] ?? null;
            $seuilMinutes = $frequenceMinutes * self::MARGE_TOLERANCE;

            return [
                'tache' => $tache,
                'frequence_attendue_minutes' => $frequenceMinutes,
                'seuil_retard_minutes' => $seuilMinutes,
                'derniere_execution_reussie' => $derniereExecution?->toIso8601String(),
                'minutes_depuis_derniere_execution' => $derniereExecution?->diffInMinutes(now()),
                'en_retard' => $derniereExecution === null || $derniereExecution->lt(now()->subMinutes($seuilMinutes)),
                'motif' => null,
            ];
        })->values()->all();
    }
}
