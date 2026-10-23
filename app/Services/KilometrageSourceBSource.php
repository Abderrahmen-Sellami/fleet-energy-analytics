<?php

namespace App\Services;

use App\Console\Commands\Concerns\VehiculesSourceB;
use App\Models\Camion;
use App\Models\ConsommationSourceB;
use App\Models\MesureSourceB;
use Carbon\Carbon;

/**
 * Détection des jours de kilométrage Télématique B CORROMPUS dans `mesures_source_b`
 * (source='mileage'), suite à l'investigation de DEMO-001 : un seul jour à distance négative
 * suffisait à écraser la moyenne pondérée plein-à-plein.
 */
class KilometrageSourceBSource
{
    // Distribution réelle des distances journalières positives.
    public const DISTANCE_JOUR_MAX_KM = 1000;

    // Tolérance d'arrondi pour une distance « négative » : −0,0027 km est du bruit numérique, pas
    // un compteur faussé.
    public const TOLERANCE_NEGATIVE_KM = 0.5;

    // Nombre maximal de jours détaillés renvoyés (DEMO-002 en a 9) — le décompte total reste exact.
    private const JOURS_DETAILLES_MAX = 30;

    /**
     * @return array{applicable: bool, corrompu: bool, nb_jours: int, jours: array, mois_jours_aberrants: array,
     *               mois_intervalles_affectes: array, seuil_max_km: int}
     */
    public static function pourCamion(Camion $camion): array
    {
        $base = [
            'applicable' => false,
            'corrompu' => false,
            'nb_jours' => 0,
            'jours' => [],
            'mois_jours_aberrants' => [],
            'mois_intervalles_affectes' => [],
            'seuil_max_km' => self::DISTANCE_JOUR_MAX_KM,
        ];

        // Un véhicule sans boîtier Télématique B mappé n'a pas de kilométrage Télématique B : rien à avertir.
        $deviceId = VehiculesSourceB::DEVICE_PAR_PLAQUE[$camion->immatriculation] ?? null;
        if ($deviceId === null) {
            return $base;
        }

        $jours = MesureSourceB::where('device_id', $deviceId)
            ->where('source', 'mileage')
            ->where(fn ($q) => $q->where('distance_km', '<', -self::TOLERANCE_NEGATIVE_KM)
                ->orWhere('distance_km', '>', self::DISTANCE_JOUR_MAX_KM))
            ->orderBy('horodatage_record')
            ->get(['horodatage_record', 'distance_km']);

        $base['applicable'] = true;
        if ($jours->isEmpty()) {
            return $base;
        }

        $base['corrompu'] = true;
        $base['nb_jours'] = $jours->count();
        $base['jours'] = $jours->take(self::JOURS_DETAILLES_MAX)->map(fn ($j) => [
            'date' => $j->horodatage_record->format('Y-m-d'),
            'distance_km' => round($j->distance_km),
            'type' => $j->distance_km < 0 ? 'negative' : 'excessive',
        ])->values()->all();
        $base['mois_jours_aberrants'] = $jours->map(fn ($j) => $j->horodatage_record->format('Y-m'))->unique()->values()->all();
        $base['mois_intervalles_affectes'] = self::moisIntervallesAffectes($deviceId, $jours->pluck('horodatage_record'));

        return $base;
    }

    /**
     * Mois (AAAA-MM, mois de `plein_suivant_date` — même convention que ComparateurRegistreController::sourceBParMois())
     * des intervalles plein-à-plein dont la fenêtre de distance contient au moins un jour aberrant. La fenêtre
     * va de minuit (heure locale) du jour du plein précédent à minuit (heure locale) du jour du plein suivant,
     * convertis en UTC, bornes incluses ; les dates d'intervalle sont stockées en heure locale.
     */
    private static function moisIntervallesAffectes(int $deviceId, $joursAberrants): array
    {
        $mois = [];

        foreach (ConsommationSourceB::where('device_id', $deviceId)->get(['plein_precedent_date', 'plein_suivant_date']) as $intervalle) {
            $debut = Carbon::createFromFormat('Y-m-d H:i:s', $intervalle->plein_precedent_date->format('Y-m-d H:i:s'), 'Etc/GMT-1')->startOfDay()->utc();
            $fin = Carbon::createFromFormat('Y-m-d H:i:s', $intervalle->plein_suivant_date->format('Y-m-d H:i:s'), 'Etc/GMT-1')->startOfDay()->utc();

            foreach ($joursAberrants as $jour) {
                if ($jour->gte($debut) && $jour->lte($fin)) {
                    $mois[$intervalle->plein_suivant_date->format('Y-m')] = true;
                    break;
                }
            }
        }

        ksort($mois);

        return array_keys($mois);
    }
}
