<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsommationSourceB extends Model
{
    protected $table = 'consommations_source_b';

    protected $guarded = [];

    protected $casts = [
        'plein_precedent_date' => 'datetime',
        'plein_suivant_date' => 'datetime',
        'volume_l' => 'float',
        'distance_km' => 'float',
        'consommation_l100km' => 'float',
        'est_partielle' => 'boolean',
    ];

    public function camion()
    {
        return $this->belongsTo(Camion::class);
    }

    /**
     * Résumé affichable (Fiche camion / Synthèse camion) — moyenne PONDÉRÉE (somme volume / somme
     * distance), jamais une moyenne simple des intervalles.
     */
    public static function resumePourCamion(int $camionId): ?array
    {
        return self::resumer(self::where('camion_id', $camionId)->whereNotNull('consommation_l100km')->get());
    }

    /**
     * Même résumé que resumePourCamion(), mais par device_id Télématique B.
     */
    public static function resumePourDevice(int $deviceId): ?array
    {
        return self::resumer(self::where('device_id', $deviceId)->whereNotNull('consommation_l100km')->get());
    }

    private static function resumer($intervalles): ?array
    {
        if ($intervalles->isEmpty()) {
            return null;
        }

        $sommeVolume = $intervalles->sum('volume_l');
        $sommeDistance = $intervalles->sum('distance_km');
        $triees = $intervalles->pluck('consommation_l100km')->sort()->values();
        $mediane = $triees[intdiv($triees->count(), 2)];

        return [
            'consommation_l100km' => round($sommeVolume / $sommeDistance * 100, 1),
            'mediane_l100km' => round($mediane, 1),
            'nombre_intervalles' => $intervalles->count(),
            'distance_totale_km' => round($sommeDistance),
            'periode_debut' => $intervalles->min('plein_precedent_date')->format('Y-m-d'),
            'periode_fin' => $intervalles->max('plein_suivant_date')->format('Y-m-d'),
            'partielle_incluse' => $intervalles->contains('est_partielle', true),
        ];
    }

    /**
     * Filtre de plausibilité AUTOMATIQUE : plus grossier que l'investigation manuelle véhicule par
     * véhicule menée sur les 6 premiers camions Télématique B.
     */
    private const PLAUSIBLE_MIN_L100KM = 5.0;
    private const PLAUSIBLE_MAX_L100KM = 80.0;
    private const MIN_INTERVALLES = 5;
    private const MIN_DISTANCE_KM = 1000;

    public static function statutPlausibilite(array $resume): string
    {
        if ($resume['nombre_intervalles'] < self::MIN_INTERVALLES || $resume['distance_totale_km'] < self::MIN_DISTANCE_KM) {
            return 'DONNEES_INSUFFISANTES';
        }

        $conso = $resume['consommation_l100km'];

        if ($conso < self::PLAUSIBLE_MIN_L100KM || $conso > self::PLAUSIBLE_MAX_L100KM) {
            return 'IMPLAUSIBLE_HORS_BORNES';
        }

        return 'PLAUSIBLE';
    }

    /**
     * Même bornes [5, 80] que statutPlausibilite() ci-dessus, mais appliquées à UNE seule valeur
     * L/100km déjà calculée (pas à un résumé véhicule complet avec ses propres nombre_intervalles/
     * distance_totale_km) — utilisé pour badger chaque valeur mensuelle affichée sur Comparaison
     * des sources.
     */
    public static function statutValeurL100km(?float $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }

        return ($valeur < self::PLAUSIBLE_MIN_L100KM || $valeur > self::PLAUSIBLE_MAX_L100KM)
            ? 'IMPLAUSIBLE_HORS_BORNES'
            : 'PLAUSIBLE';
    }

    /**
     * Bornes [min, max] L/100km de plausibilité, exposées pour l'affichage (carte « Qualité des données »).
     */
    public static function bornesPlausiblesL100km(): array
    {
        return [self::PLAUSIBLE_MIN_L100KM, self::PLAUSIBLE_MAX_L100KM];
    }

    /**
     * Intervalle plein-à-plein « exploitable » pour le décompte « mesures exploitables » : une
     * consommation calculée ET comprise dans les mêmes bornes [5, 80] L/100km (inclusives) que
     * statutPlausibilite()/statutValeurL100km() — jusque-là ces bornes ne jugeaient que la moyenne
     * pondérée d'un véhicule ou une valeur mensuelle.
     */
    public function scopeExploitable($query)
    {
        return $query->whereBetween('consommation_l100km', [self::PLAUSIBLE_MIN_L100KM, self::PLAUSIBLE_MAX_L100KM]);
    }
}
