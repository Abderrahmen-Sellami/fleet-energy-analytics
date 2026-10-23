<?php

namespace App\Services;

use App\Models\Camion;
use App\Models\Suggestion;
use Carbon\Carbon;

class SuggestionService
{
    private const SEUIL_UTILISATION_ABUSIVE = 5;
    private const SEUIL_ARRET = 5;
    private const NB_MOIS_SOUS_SCORES = 6;
    private const SEUIL_MOIS_DIVERGENTS = 2;
    private const NB_MOIS_COMPARATEUR = 6;
    private const SEUIL_CANICULE = 35;
    private const SEUIL_AMELIORATION_CONSOMMATION = 10;

    public function __construct(private WeatherService $weatherService, private NormeRefStatutService $norme_ref)
    {
    }

    /**
     * Calcule les suggestions actuellement déclenchées ET les historise dans la table
     * `suggestions` — même mécanique que AlerteService::detecterEtEnregistrer()/verifier() :
     * firstOrCreate() sur (camion_id, regle_declenchee, resolue=false) pour retrouver une
     * suggestion déjà active plutôt que d'en recréer une à chaque appel (id/date_detection
     * stables tant que la règle reste vraie), puis mise à jour de message/gravite/valeurs pour
     * refléter les dernières valeurs calculées. Toute suggestion active non redéclenchée à ce
     * passage est marquée résolue — auto-résolution identique aux alertes.
     */
    public function detecterEtEnregistrer(): array
    {
        $suggestions = $this->genererSuggestions();
        $camionsParImmat = Camion::pluck('id', 'immatriculation');

        $activesDetectees = [];
        $resultat = [];

        foreach ($suggestions as $s) {
            $camionId = $camionsParImmat[$s['camion']] ?? null;
            if ($camionId === null) {
                continue; // garde-fou, ne devrait pas arriver (immatriculation toujours issue de Camion::with(...)->get())
            }

            $suggestion = Suggestion::firstOrCreate(
                ['camion_id' => $camionId, 'regle_declenchee' => $s['regle_declenchee'], 'resolue' => false],
                ['message' => $s['message'], 'gravite' => $s['gravite'], 'valeurs' => $s['valeurs']]
            );

            $suggestion->update(['message' => $s['message'], 'gravite' => $s['gravite'], 'valeurs' => $s['valeurs']]);

            $activesDetectees[] = $suggestion->id;

            $resultat[] = [
                'id' => $suggestion->id,
                'camion' => $s['camion'],
                'regle_declenchee' => $s['regle_declenchee'],
                'message' => $s['message'],
                'gravite' => $s['gravite'],
                'valeurs' => $s['valeurs'],
                'date_detection' => $suggestion->created_at,
                'resolue' => $suggestion->resolue,
                'date_resolution' => $suggestion->date_resolution,
            ];
        }

        Suggestion::where('resolue', false)
            ->whereNotIn('id', $activesDetectees)
            ->update(['resolue' => true, 'date_resolution' => now()]);

        return $resultat;
    }

    // Calcul pur des règles, sans persistance — inchangé, réutilisé tel quel par
    // detecterEtEnregistrer() ci-dessus.
    public function genererSuggestions(): array
    {
        $meteoParSite = $this->weatherService->getMeteoParSite();
        $camions = Camion::with(['mesures', 'registreMesures'])->get();

        $suggestions = [];
        foreach ($camions as $camion) {
            $suggestions = [
                ...$suggestions,
                ...$this->regleNormeRefRouge($camion),
                ...$this->regleUtilisationAbusive($camion),
                ...$this->regleArret($camion),
                ...$this->regleDivergenceRegistre($camion),
                ...$this->regleUsageNormeRefManquant($camion),
                ...$this->regleCaniculeIpeHausse($camion, $meteoParSite),
                ...$this->regleAmeliorationConstatee($camion),
                ...$this->regleBaisseConsommationDurable($camion),
            ];
        }

        return $suggestions;
    }

    // Règle 1 : statut norme interne = ROUGE sur les 2 derniers mois exploitables consécutifs
    private function regleNormeRefRouge(Camion $camion): array
    {
        if (!$camion->ser_norme_ref) {
            return [];
        }

        $deuxDerniers = $this->norme_ref->derniersMoisExploitables($camion, 2);
        if ($deuxDerniers->count() < 2) {
            return [];
        }

        [$recent, $precedent] = $deuxDerniers->values()->all();

        // Doivent être deux mois calendaires qui se suivent, pas juste les deux dernières lignes
        // exploitables.
        if ((int) abs(Carbon::parse($recent->date)->diffInMonths(Carbon::parse($precedent->date))) !== 1) {
            return [];
        }

        $statutRecent = $this->norme_ref->calculerStatut($camion, $recent);
        $statutPrecedent = $this->norme_ref->calculerStatut($camion, $precedent);

        if ($statutRecent['statut'] !== 'ROUGE' || $statutPrecedent['statut'] !== 'ROUGE') {
            return [];
        }

        return [[
            'camion' => $camion->immatriculation,
            'regle_declenchee' => 'NORME_REF_ROUGE_2MOIS',
            'message' => "Contrôle mécanique ou remise à niveau éco-conduite recommandée pour {$camion->immatriculation}",
            'gravite' => 'urgent',
            'valeurs' => [
                'mois_recent' => Carbon::parse($recent->date)->format('Y-m'),
                'ipe_mois_recent' => $statutRecent['ipe'],
                'mois_precedent' => Carbon::parse($precedent->date)->format('Y-m'),
                'ipe_mois_precedent' => $statutPrecedent['ipe'],
                'ser_norme_ref' => $camion->ser_norme_ref,
                'ser_norme_ref_x' => round($statutRecent['ser1x'], 2),
                'unite' => $statutRecent['unite_ser'],
            ],
        ]];
    }

    // Règle 2 : moyenne du sous-score utilisation_abusive < 5 sur les 6 derniers mois exploitables
    private function regleUtilisationAbusive(Camion $camion): array
    {
        // !$m->est_partielle : le mois en cours n'entre jamais dans une moyenne.
        $mesures = $camion->mesures
            ->filter(fn ($m) => $m->utilisation_abusive !== null && $m->utilisation_abusive > 0 && !$m->est_partielle)
            ->sortByDesc('date')
            ->take(self::NB_MOIS_SOUS_SCORES);

        if ($mesures->isEmpty()) {
            return [];
        }

        $moyenne = round($mesures->avg('utilisation_abusive'), 2);
        if ($moyenne >= self::SEUIL_UTILISATION_ABUSIVE) {
            return [];
        }

        return [[
            'camion' => $camion->immatriculation,
            'regle_declenchee' => 'UTILISATION_ABUSIVE_FAIBLE',
            'message' => "Formation éco-conduite ciblée (surrégime/mode assistance) pour {$camion->immatriculation}",
            'gravite' => 'attention',
            'valeurs' => [
                'utilisation_abusive_moyenne' => $moyenne,
                'seuil' => self::SEUIL_UTILISATION_ABUSIVE,
                'nb_mois_exploitables' => $mesures->count(),
            ],
        ]];
    }

    // Règle 3 : moyenne du sous-score arret < 5 sur les 6 derniers mois exploitables
    private function regleArret(Camion $camion): array
    {
        // !$m->est_partielle : le mois en cours n'entre jamais dans une moyenne.
        $mesures = $camion->mesures
            ->filter(fn ($m) => $m->arret !== null && $m->arret > 0 && !$m->est_partielle)
            ->sortByDesc('date')
            ->take(self::NB_MOIS_SOUS_SCORES);

        if ($mesures->isEmpty()) {
            return [];
        }

        $moyenne = round($mesures->avg('arret'), 2);
        if ($moyenne >= self::SEUIL_ARRET) {
            return [];
        }

        return [[
            'camion' => $camion->immatriculation,
            'regle_declenchee' => 'ARRET_FAIBLE',
            'message' => "Sensibilisation coupure moteur à l'arrêt pour {$camion->immatriculation}",
            'gravite' => 'attention',
            'valeurs' => [
                'arret_moyenne' => $moyenne,
                'seuil' => self::SEUIL_ARRET,
                'nb_mois_exploitables' => $mesures->count(),
            ],
        ]];
    }

    // Règle 4 : au moins 2 mois en fiabilite=DIVERGENT sur les 6 derniers mois du comparateur registre manuel/Télématique A
    // (même logique que ComparateurRegistreController::show, restreinte à une fenêtre calendaire de 6 mois)
    private function regleDivergenceRegistre(Camion $camion): array
    {
        $limite = Carbon::now()->subMonths(self::NB_MOIS_COMPARATEUR)->format('Y-m');

        // Exclut le mois en cours (est_partielle), jamais comparé à des mois clos.
        $sourceAParMois = $camion->mesures->filter(fn ($m) => !$m->est_partielle)->keyBy(fn ($m) => Carbon::parse($m->date)->format('Y-m'));
        $registreParMois = $camion->registreMesures->keyBy(fn ($m) => Carbon::parse($m->date)->format('Y-m'));
        $moisRecents = $sourceAParMois->keys()->merge($registreParMois->keys())->unique()
            ->filter(fn ($mois) => $mois >= $limite);

        $moisDivergents = [];
        foreach ($moisRecents as $mois) {
            $opti = $sourceAParMois->get($mois);
            $solu = $registreParMois->get($mois);

            $consoOpti = $opti->consommation_l100 ?? null;
            $consoSolu = ($solu && $solu->km > 0) ? round(($solu->litres / $solu->km) * 100, 2) : null;
            $ecartPct = ($consoOpti && $consoSolu) ? round((($consoOpti - $consoSolu) / $consoSolu) * 100, 1) : null;
            $fiabilite = $ecartPct === null ? 'DONNEE_MANQUANTE'
                : (abs($ecartPct) <= 10 ? 'COHERENT' : (abs($ecartPct) <= 25 ? 'A_VERIFIER' : 'DIVERGENT'));

            if ($fiabilite === 'DIVERGENT') {
                $moisDivergents[] = $mois;
            }
        }

        if (count($moisDivergents) < self::SEUIL_MOIS_DIVERGENTS) {
            return [];
        }

        return [[
            'camion' => $camion->immatriculation,
            'regle_declenchee' => 'DIVERGENCE_REGISTRE_RECURRENTE',
            'message' => "Vérifier la saisie Registre pour {$camion->immatriculation} — écart récurrent",
            'gravite' => 'attention',
            'valeurs' => [
                'mois_divergents' => $moisDivergents,
                'nb_mois_divergents' => count($moisDivergents),
                'seuil' => self::SEUIL_MOIS_DIVERGENTS,
                'fenetre_mois' => self::NB_MOIS_COMPARATEUR,
            ],
        ]];
    }

    // Règle 5 : usage_norme_ref non renseigné
    private function regleUsageNormeRefManquant(Camion $camion): array
    {
        if ($camion->usage_norme_ref !== null) {
            return [];
        }

        return [[
            'camion' => $camion->immatriculation,
            'regle_declenchee' => 'USAGE_NORME_REF_MANQUANT',
            'message' => "Faire valider l'usage norme interne de {$camion->immatriculation} par le responsable SMÉ",
            'gravite' => 'info',
            'valeurs' => ['usage_norme_ref' => null],
        ]];
    }

    // Règle 6 : badge météo actuel = Canicule (site du camion) ET IPE en hausse sur le dernier mois vs le précédent.
    // Suggestion volontairement prudente : aucune causalité affirmée, juste un point de vigilance à confirmer.
    private function regleCaniculeIpeHausse(Camion $camion, array $meteoParSite): array
    {
        $meteo = $meteoParSite[$camion->groupe] ?? null;
        if (!$meteo || ($meteo['erreur'] ?? false) || $meteo['temperature'] === null) {
            return [];
        }
        if ($meteo['temperature'] < self::SEUIL_CANICULE) {
            return [];
        }

        $deuxDerniers = $this->norme_ref->derniersMoisExploitables($camion, 2);
        if ($deuxDerniers->count() < 2) {
            return [];
        }

        [$recent, $precedent] = $deuxDerniers->values()->all();
        $ipeRecent = $this->norme_ref->calculerIpe($camion, $recent);
        $ipePrecedent = $this->norme_ref->calculerIpe($camion, $precedent);

        if ($ipeRecent <= $ipePrecedent) {
            return [];
        }

        return [[
            'camion' => $camion->immatriculation,
            'regle_declenchee' => 'CANICULE_IPE_HAUSSE',
            'message' => "Point de vigilance : forte chaleur associée à une hausse de consommation ce mois pour {$camion->immatriculation} — à confirmer sur plusieurs mois avant d'en tirer une règle",
            'gravite' => 'info',
            'valeurs' => [
                'site' => $camion->groupe,
                'temperature_actuelle' => $meteo['temperature'],
                'seuil_canicule' => self::SEUIL_CANICULE,
                'mois_recent' => Carbon::parse($recent->date)->format('Y-m'),
                'ipe_mois_recent' => round($ipeRecent, 2),
                'mois_precedent' => Carbon::parse($precedent->date)->format('Y-m'),
                'ipe_mois_precedent' => round($ipePrecedent, 2),
            ],
        ]];
    }

    // Règle 7 : statut norme interne actuel VERT (clair ou foncé) alors que les 2 mois
    // exploitables consécutifs précédents étaient tous les deux ROUGE ou ORANGE — signal positif,
    // pas une alerte.
    private function regleAmeliorationConstatee(Camion $camion): array
    {
        if (!$camion->ser_norme_ref) {
            return [];
        }

        $troisDerniers = $this->norme_ref->derniersMoisExploitables($camion, 3);
        if ($troisDerniers->count() < 3) {
            return [];
        }

        [$recent, $precedent, $precedent2] = $troisDerniers->values()->all();

        // Doivent être 3 mois calendaires qui se suivent, pas juste les 3 dernières lignes
        // exploitables.
        if ((int) abs(Carbon::parse($recent->date)->diffInMonths(Carbon::parse($precedent->date))) !== 1
            || (int) abs(Carbon::parse($precedent->date)->diffInMonths(Carbon::parse($precedent2->date))) !== 1) {
            return [];
        }

        $statutRecent = $this->norme_ref->calculerStatut($camion, $recent);
        $statutPrecedent = $this->norme_ref->calculerStatut($camion, $precedent);
        $statutPrecedent2 = $this->norme_ref->calculerStatut($camion, $precedent2);

        $estVert = fn (string $s) => in_array($s, ['VERT_CLAIR', 'VERT_FONCE'], true);
        $estDifficile = fn (string $s) => in_array($s, ['ROUGE', 'ORANGE'], true);

        if (!$estVert($statutRecent['statut']) || !$estDifficile($statutPrecedent['statut']) || !$estDifficile($statutPrecedent2['statut'])) {
            return [];
        }

        $libelles = ['ROUGE' => 'Rouge', 'ORANGE' => 'Orange', 'VERT_CLAIR' => 'Vert clair', 'VERT_FONCE' => 'Vert foncé'];

        return [[
            'camion' => $camion->immatriculation,
            'regle_declenchee' => 'AMELIORATION_CONSTATEE',
            'message' => "Amélioration constatée pour {$camion->immatriculation} — passé de {$libelles[$statutPrecedent['statut']]} à {$libelles[$statutRecent['statut']]} après 2 mois difficiles consécutifs.",
            'gravite' => 'info',
            'valeurs' => [
                'statut_actuel' => $statutRecent['statut'],
                'mois_actuel' => Carbon::parse($recent->date)->format('Y-m'),
                'ipe_actuel' => $statutRecent['ipe'],
                'statuts_precedents' => [
                    ['mois' => Carbon::parse($precedent->date)->format('Y-m'), 'statut' => $statutPrecedent['statut']],
                    ['mois' => Carbon::parse($precedent2->date)->format('Y-m'), 'statut' => $statutPrecedent2['statut']],
                ],
                'ser_norme_ref' => $camion->ser_norme_ref,
                'ser_norme_ref_incertitude' => $camion->ser_norme_ref_incertitude,
                'ser_norme_ref_x' => round($statutRecent['ser1x'], 2),
                'ser_norme_ref_cible' => $camion->ser_norme_ref_cible,
            ],
        ]];
    }

    // Règle 8 : baisse durable de consommation (L/100km, Télématique A) — moyenne des 3 mois
    // exploitables les plus récents au moins SEUIL_AMELIORATION_CONSOMMATION % sous la moyenne des
    // 3 mois exploitables précédents, sur une fenêtre calendaire de 6 mois CONSÉCUTIFS (pas 6
    // lignes exploitables quelconques). Signal positif complémentaire à AMELIORATION_CONSTATEE
    // (règle 7, basée sur le statut norme interne d'un seul mois) : celui-ci suit une tendance sur
    // consommation_l100 directement, donc utilisable même pour un camion sans SER norme interne confirmée
    // (ser_norme_ref null), contrairement à la règle 7 qui l'exige.
    private function regleBaisseConsommationDurable(Camion $camion): array
    {
        $mesures = $camion->mesures
            ->filter(fn ($m) => $m->conso_exploitable && !$m->est_partielle)
            ->sortByDesc('date')
            ->take(6)
            ->values();

        if ($mesures->count() < 6) {
            return [];
        }

        // 6 mois calendaires qui se suivent, pas juste 6 lignes exploitables — même garde-fou que
        // regleNormeRefRouge()/regleAmeliorationConstatee() ci-dessus (cast (int) obligatoire, Carbon 3
        // renvoie un float sinon et "!== 1" serait toujours vrai).
        for ($i = 0; $i < 5; $i++) {
            if ((int) abs(Carbon::parse($mesures[$i]->date)->diffInMonths(Carbon::parse($mesures[$i + 1]->date))) !== 1) {
                return [];
            }
        }

        $recents3 = $mesures->slice(0, 3);
        $anciens3 = $mesures->slice(3, 3);
        $moyenneRecente = $recents3->avg('consommation_l100');
        $moyenneAncienne = $anciens3->avg('consommation_l100');

        $baissePct = ($moyenneAncienne - $moyenneRecente) / $moyenneAncienne * 100;
        if ($baissePct < self::SEUIL_AMELIORATION_CONSOMMATION) {
            return [];
        }

        $formatMois = fn ($collection) => $collection->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m'))
            ->values()
            ->all();

        return [[
            'camion' => $camion->immatriculation,
            'regle_declenchee' => 'AMELIORATION_CONSOMMATION_SOUTENUE',
            'message' => "Amélioration constatée pour {$camion->immatriculation} — consommation en baisse de "
                . round($baissePct, 1) . "% sur les 3 derniers mois vs les 3 précédents.",
            'gravite' => 'info',
            'valeurs' => [
                'moyenne_3_mois_recents' => round($moyenneRecente, 2),
                'moyenne_3_mois_precedents' => round($moyenneAncienne, 2),
                'baisse_pct' => round($baissePct, 1),
                'seuil_pct' => self::SEUIL_AMELIORATION_CONSOMMATION,
                'mois_recents' => $formatMois($recents3),
                'mois_precedents' => $formatMois($anciens3),
            ],
        ]];
    }

}
