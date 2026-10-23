<?php

namespace App\Http\Controllers\Api;

use App\Console\Commands\Concerns\VehiculesSourceB;
use App\Http\Controllers\Controller;
use App\Models\Camion;
use App\Models\ConsommationSourceB;
use App\Services\KilometrageSourceBSource;

class ComparateurRegistreController extends Controller
{
    public function show(int $id)
    {
        $camion = Camion::with(['mesures', 'registreMesures'])->findOrFail($id);

        // Indexer les deux sources par mois (format Y-m) pour les recouper
        $sourceAParMois = $camion->mesures->keyBy(fn($m) => \Carbon\Carbon::parse($m->date)->format('Y-m'));
        $registreParMois = $camion->registreMesures->keyBy(fn($m) => \Carbon\Carbon::parse($m->date)->format('Y-m'));

        $tousLesMois = $sourceAParMois->keys()->merge($registreParMois->keys())->unique()->sort();

        // Comparée à registre manuel (référence officielle norme interne), même sens que la
        // comparaison Télématique A vs registre manuel déjà en place — pas de comparaison
        // Télématique B vs Télématique A en plus, une seule 3e colonne demandée, pas 3
        // comparaisons croisées.
        $sourceBParMois = $this->sourceBParMois($camion->id);
        $sourceBDisponible = $sourceBParMois->isNotEmpty();

        $comparaison = $tousLesMois->map(function ($mois) use ($sourceAParMois, $registreParMois, $sourceBParMois) {
            $opti = $sourceAParMois->get($mois);
            $solu = $registreParMois->get($mois);

            $consoOpti = $opti->consommation_l100 ?? null;
            $consoSolu = $solu && $solu->km > 0 ? round(($solu->litres / $solu->km) * 100, 2) : null;
            $compL100 = $this->comparer($consoOpti, $consoSolu);

            // Même logique que L/100km ci-dessus, en L/h — recalculé depuis litres/heures plutôt que
            // de faire confiance à un éventuel pe_lh déjà stocké, pour rester cohérent avec le choix
            // déjà fait côté L/100km (recalcul depuis litres/km, jamais pe_l100 directement).
            $consoOptiLh = $opti->conso_horaire ?? null;
            $consoSoluLh = $solu && $solu->heures > 0 && $solu->litres !== null ? round($solu->litres / $solu->heures, 2) : null;
            $compLh = $this->comparer($consoOptiLh, $consoSoluLh);

            $consoSourceB = $sourceBParMois->get($mois);
            $compSourceB = $this->comparer($consoSourceB, $consoSolu, 'Télématique B');

            return [
                'statut_plausibilite_source_b' => ConsommationSourceB::statutValeurL100km($consoSourceB),
                'mois' => $mois,
                'source_a_l100' => $consoOpti,
                'registre_l100' => $consoSolu,
                'ecart_pct' => $compL100['ecart_pct'],
                'fiabilite' => $compL100['fiabilite'],
                'remarque' => $compL100['remarque'],
                'source_a_lh' => $consoOptiLh,
                'registre_lh' => $consoSoluLh,
                'ecart_pct_lh' => $compLh['ecart_pct'],
                'fiabilite_lh' => $compLh['fiabilite'],
                'remarque_lh' => $compLh['remarque'],
                'source_b_l100' => $consoSourceB,
                'ecart_pct_source_b' => $compSourceB['ecart_pct'],
                'fiabilite_source_b' => $compSourceB['fiabilite'],
                'remarque_source_b' => $compSourceB['remarque'],
            ];
        })->values();

        // Une source n'est comptée (titre « N sources », légende du graphique) que si elle a
        // réellement au moins un point pour CE véhicule — jamais un fantôme à 0 point.
        $sources = [
            'source_a' => $comparaison->contains(fn($l) => $l['source_a_l100'] !== null || $l['source_a_lh'] !== null),
            'registre' => $comparaison->contains(fn($l) => $l['registre_l100'] !== null || $l['registre_lh'] !== null),
            'source_b' => $sourceBDisponible,
        ];

        return response()->json([
            'camion' => $camion->immatriculation,
            'groupe' => $camion->groupe,
            'source_b_disponible' => $sourceBDisponible,
            'sources' => $sources,
            'nb_sources' => count(array_filter($sources)),
            'source_b_motif_indisponible' => $sourceBDisponible ? null : $this->motifSourceBIndisponible($camion),
            'source_b_boitier_mappe' => isset(VehiculesSourceB::DEVICE_PAR_PLAQUE[$camion->immatriculation]),
            // Avertissement kilométrage Télématique B corrompu — informatif, affiché à côté des
            // valeurs Télématique B.
            'kilometrage_source_b' => KilometrageSourceBSource::pourCamion($camion),
            'comparaison' => $comparaison,
        ]);
    }

    // Pourquoi il n'y a pas de consommation Télématique B pour ce véhicule — deux situations à ne pas confondre :
    // aucun boîtier Télématique B (structurel, ex. les 6 SITE B) vs boîtier présent mais consommation non calculée.
    private function motifSourceBIndisponible(Camion $camion): string
    {
        if (!isset(VehiculesSourceB::DEVICE_PAR_PLAQUE[$camion->immatriculation])) {
            return 'Télématique B indisponible pour ce véhicule';
        }
        if (in_array($camion->immatriculation, VehiculesSourceB::PLAQUES_CONSOMMATION_EXCLUES, true)) {
            return 'Consommation Télématique B non calculée (capteur carburant jugé non fiable, véhicule exclu du calcul)';
        }
        if (!ConsommationSourceB::where('camion_id', $camion->id)->exists()) {
            return 'Consommation Télématique B non calculable (aucun événement de plein détecté)';
        }
        return 'Consommation Télématique B non calculable (aucun intervalle plein-à-plein avec une distance exploitable)';
    }

    // Consommation Télématique B moyenne PONDÉRÉE par mois calendaire (somme volume / somme
    // distance sur les intervalles plein-à-plein dont plein_suivant_date tombe dans ce mois)
    private function sourceBParMois(int $camionId)
    {
        return ConsommationSourceB::where('camion_id', $camionId)
            ->whereNotNull('consommation_l100km')
            ->whereNotNull('plein_suivant_date')
            ->get()
            ->groupBy(fn($c) => $c->plein_suivant_date->format('Y-m'))
            ->map(function ($intervalles) {
                $sommeVolume = $intervalles->sum('volume_l');
                $sommeDistance = $intervalles->sum('distance_km');
                return $sommeDistance > 0 ? round($sommeVolume / $sommeDistance * 100, 2) : null;
            })
            ->filter(fn($v) => $v !== null);
    }

    // Écart%/fiabilité/remarque — même calcul pour L/100km, L/h et Télématique B (cf. show() ci-dessus),
    // factorisé ici pour ne jamais diverger entre les trois. ($a && $b) plutôt que !== null : 0 est
    // traité comme une valeur absente ici, cohérent avec le comportement déjà en place avant l'ajout
    // du L/h puis de Télématique B. $sourceA nomme la source comparée (Télématique A ou Télématique B) dans la remarque
    // DIVERGENT, pour ne jamais accuser "un capteur Télématique A" d'un écart qui vient en réalité de Télématique B.
    private function comparer(?float $a, ?float $b, string $sourceA = 'Télématique A'): array
    {
        $ecartPct = ($a && $b) ? round((($a - $b) / $b) * 100, 1) : null;

        $fiabilite = $ecartPct === null ? 'DONNEE_MANQUANTE'
            : (abs($ecartPct) <= 10 ? 'COHERENT' : (abs($ecartPct) <= 25 ? 'A_VERIFIER' : 'DIVERGENT'));

        // DIVERGENT sur Télématique B : ne pas répéter le message générique "vérifier une anomalie de capteur"
        // (qui laisserait croire à un problème ponctuel de ce mois précis) — l'écart Télématique B vs
        // Télématique A/registre manuel est un biais systémique déjà documenté (cf. VehiculesSourceB::
        // AVERTISSEMENT_DIVERGENCE_SOURCE_B), pas une anomalie à investiguer mois par mois.
        $remarque = match (true) {
            $fiabilite === 'DIVERGENT' && $sourceA === 'Télématique B' => VehiculesSourceB::AVERTISSEMENT_DIVERGENCE_SOURCE_B,
            $fiabilite === 'DIVERGENT' => "Écart important ({$ecartPct}%) — vérifier une saisie Registre en retard ou une anomalie de capteur {$sourceA} ce mois-ci.",
            $fiabilite === 'A_VERIFIER' => "Écart notable, à surveiller si récurrent.",
            default => null,
        };

        return ['ecart_pct' => $ecartPct, 'fiabilite' => $fiabilite, 'remarque' => $remarque];
    }
}
