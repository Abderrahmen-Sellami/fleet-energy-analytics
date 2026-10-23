<?php

namespace App\Services;

use App\Models\Camion;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Logique norme interne partagée entre SerDynamiqueController, SerStandardsController,
 * AlerteService et SuggestionService — pour que "la dernière mesure exploitable" et le calcul de
 * l'IPE restent identiques partout et ne divergent jamais entre deux écrans.
 */
class NormeRefStatutService
{
    // Usages dont la SER est exprimée en L/100km. Tout autre usage (y compris usage inconnu) reste en L/h.
    public const USAGES_EN_L100KM = ['Transport — catégorie 2', 'Transport — catégorie 1'];

    // Unité de la SER / du seuil / de la cible d'un véhicule — 'l/100km' ou 'l/h' (même graphie que uniteIpe()).
    public function uniteSer(Camion $camion): string
    {
        return in_array($camion->usage_norme_ref, self::USAGES_EN_L100KM, true) ? 'l/100km' : 'l/h';
    }

    // Les N dernières mesures registre manuel où l'IPE est réellement calculable DANS L'UNITÉ DU
    // VÉHICULE : - usage en L/100km : km>0 avec litres renseignés, ou pe_l100 renseigné ; - usage
    // en L/h : heures>0 avec litres renseignés.
    public function derniersMoisExploitables(Camion $camion, int $n = 1): Collection
    {
        $enL100km = $this->uniteSer($camion) === 'l/100km';

        return $camion->registreMesures
            ->filter(fn ($m) => $enL100km
                ? (($m->km !== null && $m->km > 0 && $m->litres !== null) || $m->pe_l100 !== null)
                : (($m->heures !== null && $m->heures > 0 && $m->litres !== null) || $m->pe_l100 !== null))
            ->sortByDesc('date')
            ->take($n)
            ->values();
    }

    // IPE dans l'unité de la SER du véhicule. Usage en L/100km : litres ÷ km × 100 (recalculé, comme la formule
    // du formulaire norme interne — sinon pe_l100 tel que saisi). Usage en L/h : litres ÷ heures si les heures sont
    // renseignées, sinon pe_l100 (une valeur en L/100km, jamais comparable à une SER en L/h — cf. calculerStatut()).
    public function calculerIpe(Camion $camion, $mesure): ?float
    {
        if ($this->uniteSer($camion) === 'l/100km') {
            return ($mesure->km > 0 && $mesure->litres !== null)
                ? $mesure->litres / $mesure->km * 100
                : ($mesure->pe_l100 !== null ? (float) $mesure->pe_l100 : null);
        }

        $utiliseHeures = $mesure->heures && $mesure->heures > 0;

        return $utiliseHeures ? ($mesure->litres / $mesure->heures) : ($mesure->pe_l100 !== null ? (float) $mesure->pe_l100 : null);
    }

    // Unité de la valeur renvoyée par calculerIpe() : celle de la SER du véhicule, sauf pour un usage en L/h sans
    // heures saisies, où l'on retombe sur un L/100km (donnée saisie mais incomparable à la SER).
    public function uniteIpe(Camion $camion, $mesure): string
    {
        if ($this->uniteSer($camion) === 'l/100km') {
            return 'l/100km';
        }

        return ($mesure->heures && $mesure->heures > 0) ? 'l/h' : 'l/100km';
    }

    // Code couleur officiel norme interne — mêmes seuils que les règles de mise en forme conditionnelle du formulaire :
    // rouge si IPE > SER×1,x% ; orange si SER < IPE ≤ SER×1,x% ; vert clair si Cible < IPE ≤ SER ; vert foncé sinon.
    public function calculerStatut(Camion $camion, $mesure): array
    {
        $uniteSer = $this->uniteSer($camion);
        $ipe = $this->calculerIpe($camion, $mesure);
        $unite = $this->uniteIpe($camion, $mesure);
        $ser = $camion->ser_norme_ref;
        $ser1x = $ser * (1 + $camion->ser_norme_ref_incertitude);
        $cible = $camion->ser_norme_ref_cible;

        // Usage en L/h : la SER/seuil/cible sont en L/h. Si la mesure n'a pas d'heures exploitables,
        // calculerIpe() est retombé sur pe_l100 (L/100km) — une unité différente qu'on ne peut pas comparer à
        // la SER sans fausser le statut. Cohérent avec la consigne de fiabilité du projet (jamais un statut
        // positif/négatif sur une donnée dont on n'est pas sûr) : on refuse de trancher plutôt que d'afficher
        // une couleur potentiellement fausse. Ce garde-fou ne concerne plus les usages Transport, dont l'IPE est
        // par construction en L/100km, l'unité de leur SER.
        if ($uniteSer === 'l/h' && !($mesure->heures && $mesure->heures > 0)) {
            return ['statut' => 'DONNEES_INSUFFISANTES', 'raison' => 'UNITE_INCOMPATIBLE', 'ipe' => $ipe !== null ? round($ipe, 2) : null, 'ser1x' => $ser1x, 'unite' => $unite, 'unite_ser' => $uniteSer];
        }

        if ($ipe === null) {
            return ['statut' => 'DONNEES_INSUFFISANTES', 'raison' => 'MOIS_NON_SAISI', 'ipe' => null, 'ser1x' => $ser1x, 'unite' => $unite, 'unite_ser' => $uniteSer];
        }

        if ($ipe > $ser1x) {
            $statut = 'ROUGE';
        } elseif ($ipe > $ser) {
            $statut = 'ORANGE';
        } elseif ($ipe > $cible) {
            $statut = 'VERT_CLAIR';
        } else {
            $statut = 'VERT_FONCE';
        }

        return ['statut' => $statut, 'ipe' => round($ipe, 2), 'ser1x' => $ser1x, 'unite' => $unite, 'unite_ser' => $uniteSer];
    }

    // Raison explicite quand SerDynamiqueController n'a pas pu calculer de statut couleur —
    // distingue "abonnement Télématique A trop récent pour avoir un historique registre manuel" de
    // "mois pas encore saisi", pour que le frontend affiche le bon message sans deviner à partir
    // d'une date.
    public function raisonDonneesInsuffisantes(Camion $camion, $derniere): ?string
    {
        if ($derniere) {
            return $camion->ser_norme_ref === null ? 'SER_NORME_REF_NON_DEFINIE' : null;
        }

        if ($camion->source_a_actif_depuis && Carbon::parse($camion->source_a_actif_depuis)->gt(now()->subMonths(2))) {
            return 'ABONNEMENT_RECENT';
        }

        return 'MOIS_NON_SAISI';
    }
}
