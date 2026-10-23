<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Console\Commands\Concerns\VehiculesSourceB;
use App\Http\Controllers\Controller;
use App\Models\Camion;
use App\Models\MesureSourceB;

/**
 * Dernière position Télématique B archivée (mesures_source_b,
 * source='realtime'), PAS un appel API Télématique B en direct — contrairement à
 * PositionTempsReelController (Télématique A), qui interroge constructeur à la demande avec un cache
 * de 45s. Choix cohérent avec l'architecture déjà construite pour Télématique B (import périodique via
 * commande planifiée, jamais d'appel API synchrone déclenché par une requête utilisateur) : la
 * fraîcheur affichée est donc celle du dernier passage réussi de la commande (jusqu'à 15 min),
 * jamais plus vieille que ça si le scheduler tourne normalement.
 */
class PositionTempsReelSourceBController extends Controller
{
    public function index()
    {
        $plaques = array_keys(VehiculesSourceB::DEVICE_PAR_PLAQUE);
        $camions = Camion::whereIn('immatriculation', $plaques)->get()->keyBy('immatriculation');

        // Dernière ligne 'realtime' par camion — jamais 'mileage' (distance journalière, sans
        // position instantanée exploitable pour ce marqueur).
        $dernieresParCamion = MesureSourceB::where('source', 'realtime')
            ->whereIn('camion_id', $camions->pluck('id'))
            ->get(['camion_id', 'latitude', 'longitude', 'vitesse', 'horodatage_record', 'donnees_brutes'])
            ->groupBy('camion_id')
            ->map(fn ($lignes) => $lignes->sortByDesc('horodatage_record')->first());

        // Chaque véhicule mappé reste dans la liste même sans position encore archivée (jamais
        // exécuté, ou échec API la dernière fois) — latitude/longitude explicitement null plutôt
        // qu'un véhicule silencieusement absent, même discipline que PositionTempsReelController.
        $positions = $camions->values()->map(function (Camion $camion) use ($dernieresParCamion) {
            $derniere = $dernieresParCamion->get($camion->id);

            return [
                'camion_id' => $camion->id,
                'immatriculation' => $camion->immatriculation,
                'latitude' => $derniere?->latitude,
                'longitude' => $derniere?->longitude,
                'vitesse' => $derniere?->vitesse,
                'horodatage_position' => $derniere?->horodatage_record?->toIso8601String(),
                'adresse' => $this->extraireAdresse($derniere),
            ];
        })->values()->all();

        return response()->json(['positions' => $positions]);
    }

    // `address` est un JSON encodé en string DANS le JSON de donnees_brutes (cf. réponse brute
    // GET /realTimeRecords) — décodage nécessaire ici, jamais fait au moment de l'archivage pour
    // garder donnees_brutes fidèle à la réponse API telle quelle.
    private function extraireAdresse(?MesureSourceB $derniere): ?string
    {
        $brut = $derniere?->donnees_brutes['address'] ?? null;

        if (!$brut) {
            return null;
        }

        $decode = json_decode($brut, true);

        return $decode['gc'] ?? null;
    }
}
