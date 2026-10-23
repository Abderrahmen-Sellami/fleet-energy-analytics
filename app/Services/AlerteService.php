<?php

namespace App\Services;

use App\Models\Alerte;
use App\Models\Camion;
use App\Models\Mesure;
use Carbon\Carbon;

class AlerteService
{
    // Types d'alertes que la détection peut produire (type stocké dans alertes.type => libellé affiché). SOURCE
    // UNIQUE : exposée par GET /api/alertes/types pour le filtre « Type d'alerte » du tableau de l'Accueil, et
    // verifier() REFUSE tout type absent de cette liste — un nouveau type ajouté à la détection sans passer par ici
    // échoue bruyamment (LogicException) au lieu de rester invisible du filtre.
    public const TYPES = [
        'eco_score_faible' => 'Eco Score faible',
        'conso_elevee' => 'Consommation élevée',
        'ipe_rouge' => 'Statut IPÉ norme interne rouge',
        'ecart_registre_source_a' => 'Écart Registre / Télématique A divergent',
        'donnees_manquantes_prolongees' => 'Données manquantes prolongées',
        'usage_norme_ref_manquant' => 'Usage norme interne non renseigné',
    ];

    private NormeRefStatutService $norme_ref;

    // Paramètre optionnel : les tests (et d'anciens appelants) font encore new AlerteService().
    public function __construct(?NormeRefStatutService $norme_ref = null)
    {
        $this->norme_ref = $norme_ref ?? new NormeRefStatutService();
    }

    public function detecterEtEnregistrer(): void
    {
        $camions = Camion::with(['mesures', 'registreMesures'])->get();

        $activesDetectees = [];

        foreach ($camions as $c) {
            // !$m->est_partielle : une mesure du mois en cours (provisoire) ne
            // doit jamais entrer dans une moyenne mélangée à des mois clos.
            $exploitables = $c->mesures->filter(fn($m) => Mesure::estValeurConsoExploitable($m->consommation_l100) && !$m->est_partielle);
            $ecoScore = round($exploitables->where('ecoscore', '>', 0)->avg('ecoscore') ?? 0, 2);
            $conso = round($exploitables->avg('consommation_l100') ?? 0, 2);

            $this->verifier($c->id, 'eco_score_faible', $ecoScore > 0 && $ecoScore < 7, $ecoScore, 7, 'warning',
                "{$c->immatriculation} — Eco Score faible : {$ecoScore}/10", $activesDetectees);

            $this->verifier($c->id, 'conso_elevee', $conso > 45, $conso, 45, 'critique',
                "{$c->immatriculation} — consommation élevée : {$conso} L/100km", $activesDetectees);

            $this->verifierIpeRouge($c, $activesDetectees);
            $this->verifierEcartRegistreSourceA($c, $activesDetectees);
            $this->verifierDonneesManquantesProlongees($c, $exploitables, $activesDetectees);

            // Uniquement pour la flotte auditée norme interne (source_principale='SOURCE_A')
            $this->verifier($c->id, 'usage_norme_ref_manquant', $c->source_principale === 'SOURCE_A' && empty($c->usage_norme_ref), null, null, 'warning',
                "{$c->immatriculation} — usage norme interne non renseigné en base", $activesDetectees);
        }

        Alerte::where('resolue', false)
            ->whereNotIn('id', $activesDetectees)
            ->update(['resolue' => true, 'date_resolution' => now()]);
    }

    // Utilise DIRECTEMENT NormeRefStatutService au lieu de répliquer sa logique : l'ancienne copie
    // comparait un L/100km (pe_l100) à une SER en L/h pour les véhicules sans heures, sans le
    // garde-fou d'unité du service — une divergence silencieuse entre l'alerte et le statut
    // affiché.
    private function verifierIpeRouge(Camion $c, array &$activesDetectees): void
    {
        if (!$c->ser_norme_ref || $c->ser_norme_ref_incertitude === null) return;

        $derniere = $this->norme_ref->derniersMoisExploitables($c, 1)->first();
        if (!$derniere) return;

        $statut = $this->norme_ref->calculerStatut($c, $derniere);
        if ($statut['statut'] === 'DONNEES_INSUFFISANTES') return;

        $ipe = $statut['ipe'];
        $ser1x = round($statut['ser1x'], 2);
        $unite = $statut['unite'] === 'l/h' ? 'L/h' : 'L/100km';

        $this->verifier($c->id, 'ipe_rouge', $statut['statut'] === 'ROUGE', $ipe, $ser1x, 'critique',
            "{$c->immatriculation} — statut IPÉ norme interne : ROUGE (IPE {$ipe} {$unite} > SER×1,x% {$ser1x} {$unite})",
            $activesDetectees);
    }

    // Réplique la logique de recoupement mensuel de ComparateurRegistreController pour ne
    // considérer que le dernier mois commun entre les deux sources.
    private function verifierEcartRegistreSourceA(Camion $c, array &$activesDetectees): void
    {
        // Exclut le mois en cours (est_partielle) : une comparaison registre manuel/Télématique A
        // sur une fenêtre partielle ne peut pas être jugée fiable.
        $sourceAParMois = $c->mesures->filter(fn($m) => !$m->est_partielle)->keyBy(fn($m) => Carbon::parse($m->date)->format('Y-m'));
        $registreParMois = $c->registreMesures->keyBy(fn($m) => Carbon::parse($m->date)->format('Y-m'));
        $dernierMois = $sourceAParMois->keys()->merge($registreParMois->keys())->unique()->sort()->last();
        if (!$dernierMois) return;

        $opti = $sourceAParMois->get($dernierMois);
        $solu = $registreParMois->get($dernierMois);
        $consoOpti = $opti->consommation_l100 ?? null;
        $consoSolu = $solu && $solu->km > 0 ? round(($solu->litres / $solu->km) * 100, 2) : null;
        $ecartPct = ($consoOpti && $consoSolu) ? round((($consoOpti - $consoSolu) / $consoSolu) * 100, 1) : null;
        $fiabilite = $ecartPct === null ? 'DONNEE_MANQUANTE'
            : (abs($ecartPct) <= 10 ? 'COHERENT' : (abs($ecartPct) <= 25 ? 'A_VERIFIER' : 'DIVERGENT'));

        $moisLisible = Carbon::parse($dernierMois)->format('m/Y');
        $this->verifier($c->id, 'ecart_registre_source_a', $fiabilite === 'DIVERGENT', $ecartPct, 25, 'warning',
            "{$c->immatriculation} — écart Registre/Télématique A divergent sur {$moisLisible} : " . ($ecartPct > 0 ? '+' : '') . "{$ecartPct}%",
            $activesDetectees);
    }

    // Ne remonte l'alerte que si le camion est actif depuis assez longtemps pour qu'une
    // absence de mesure soit anormale (cf. source_a_actif_depuis — un onboarding récent
    // n'a légitimement pas encore de données).
    private function verifierDonneesManquantesProlongees(Camion $c, $exploitables, array &$activesDetectees): void
    {
        if (!$c->source_a_actif_depuis) return;
        $activeDepuis = Carbon::parse($c->source_a_actif_depuis);
        if ($activeDepuis->gt(now()->subMonths(3))) return;

        $derniereDate = $exploitables->max('date');
        $moisEcoules = $derniereDate ? (int) floor(Carbon::parse($derniereDate)->diffInMonths(now())) : null;
        $condition = is_null($derniereDate) || $moisEcoules >= 3;

        $message = $derniereDate
            ? "{$c->immatriculation} — aucune mesure exploitable depuis {$moisEcoules} mois (dernière : " . Carbon::parse($derniereDate)->format('m/Y') . ")"
            : "{$c->immatriculation} — aucune mesure exploitable enregistrée depuis l'activation Télématique A";

        $this->verifier($c->id, 'donnees_manquantes_prolongees', $condition, $moisEcoules, 3, 'warning', $message, $activesDetectees);
    }

    private function verifier(int $camionId, string $type, bool $condition, ?float $valeur, ?float $seuil, string $severite, string $message, array &$activesDetectees): void
    {
        if (!isset(self::TYPES[$type])) {
            throw new \LogicException("Type d'alerte inconnu « {$type} » : l'ajouter à AlerteService::TYPES.");
        }
        if (!$condition) return;

        $alerte = Alerte::firstOrCreate(
            ['camion_id' => $camionId, 'type' => $type, 'resolue' => false],
            ['message' => $message, 'valeur' => $valeur, 'seuil' => $seuil, 'severite' => $severite]
        );

        $alerte->update(['valeur' => $valeur, 'message' => $message]);
        $activesDetectees[] = $alerte->id;
    }
}
