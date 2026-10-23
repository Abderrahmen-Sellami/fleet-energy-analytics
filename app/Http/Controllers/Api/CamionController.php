<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\AlerteService;
use App\Http\Controllers\Controller;
use App\Models\Camion;
use App\Models\Mesure;
use App\Models\Alerte;
use App\Models\AnalyseCorrective;
use App\Models\ConsommationSourceB;
use App\Models\MesureSourceB;
use App\Models\PrixCarburantHistorique;
use App\Console\Commands\Concerns\VehiculesSourceB;
use App\Services\NormeRefStatutService;
use App\Services\KilometrageSourceBSource;
use App\Services\WeatherService;
use App\Support\Concerns\ValideDatesCalendaires;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CamionController extends Controller
{
    use ValideDatesCalendaires;

    // Repli si l'historique de prix (prix_carburant_historique) ne couvre pas une date donnée —
    // même valeur par défaut que le champ éditable de la Fiche camion et que direction.blade.php.
    private const PRIX_DEFAUT_REPLI = 1.985;

    // Injecté plutôt que dupliquer NormeRefStatutService::uniteSer() : même service déjà partagé
    // par SerDynamiqueController/ SerStandardsController/AlerteService/SuggestionService pour que
    // "l'unité de la SER d'un véhicule" ne diverge jamais entre écrans.
    public function __construct(private NormeRefStatutService $norme_ref)
    {
    }

    public function index()
    {
        return Camion::withCount('mesures')
            ->with(['mesures' => fn($q) => $q->orderByDesc('date')->limit(1)])
            ->get();
    }

    public function mesures(int $id)
    {
        $camion = Camion::findOrFail($id);
        // Pas de filtre est_partielle ici : le mois en cours doit rester dans la liste retournée
        // pour que la Fiche camion puisse l'afficher sur la courbe (visuellement distinct) —
        // seules les deux moyennes ci-dessous l'excluent, pour ne jamais mélanger un mois complet
        // et un mois entamé.
        $mesures = Mesure::where('camion_id', $id)->orderBy('date')->get();
        $mesuresConfirmees = $mesures->where('est_partielle', false);

        // null explicite (pas de round(null) qui plante en TypeError) si le camion n'a aucune
        // mesure exploitable.
        return response()->json([
            'camion' => $camion,
            'mesures' => $mesures,
            'moyenne_conso' => $mesuresConfirmees->isNotEmpty() ? round($mesuresConfirmees->avg('consommation_l100'), 2) : null,
            'moyenne_ecoscore' => $mesuresConfirmees->isNotEmpty() ? round($mesuresConfirmees->avg('ecoscore'), 2) : null,
        ]);
    }

    // Sites valides pour le paramètre `site` — mêmes deux valeurs que le <select id="filtreSite">
    // du dashboard (dashboard.blade.php) et COULEURS_SITE/ORDRE_GROUPE_TRI déjà codés en dur côté
    // frontend ailleurs dans ce projet ; pas de requête DISTINCT dynamique ici, cohérent avec ce
    // qui existe déjà plutôt qu'une nouvelle convention.
    private const SITES_VALIDES = ['SITE A', 'SITE B'];

    // Distinction de SOURCE de données uniquement.
    private const SOURCES_VALIDES = ['SOURCE_A', 'SOURCE_B'];

    // Jeton réservé envoyé par le frontend pour un filtre multi-select explicitement vide (toutes
    // les cases décochées) — jamais une vraie valeur de site/usage_norme_ref/matricule, donc jamais en
    // conflit avec la whitelist. Nécessaire car une chaîne vide en query string ("?site=") est
    // normalisée en null par le middleware ConvertEmptyStringsToNull (actif par défaut) avant que ce
    // contrôleur ne la voie — impossible de distinguer autrement "paramètre absent" (pas de filtre)
    // de "paramètre explicitement vide" (filtre sur aucun camion), cf. listeParametreValidee().
    private const AUCUNE_VALEUR = '__AUCUN__';

    public function resumeFlotte(Request $request)
    {
        $depuis = $request->query('depuis');
        $jusquA = $request->query('jusqu_a');

        foreach (['depuis' => $depuis, 'jusqu_a' => $jusquA] as $nom => $valeur) {
            if ($valeur !== null && !$this->estDateCalendaireValide($valeur)) {
                return response()->json(['message' => "Paramètre {$nom} invalide, format attendu AAAA-MM-JJ."], 422);
            }
        }
        if ($depuis !== null && $jusquA !== null && $depuis > $jusquA) {
            return response()->json(['message' => "Le paramètre depuis doit être antérieur ou égal à jusqu_a."], 422);
        }

        // Site/Usage norme interne/Matricule — mêmes 3 "filtres généraux" que la barre du
        // dashboard.
        $site = $this->listeParametreValidee($request, 'site', self::SITES_VALIDES);
        if ($site instanceof JsonResponse) {
            return $site;
        }

        $usage = $this->listeParametreValidee($request, 'usage_norme_ref', Camion::whereNotNull('usage_norme_ref')->distinct()->pluck('usage_norme_ref')->all());
        if ($usage instanceof JsonResponse) {
            return $usage;
        }

        $matricule = $this->listeParametreValidee($request, 'matricule', Camion::pluck('id')->map(fn ($id) => (string) $id)->all());
        if ($matricule instanceof JsonResponse) {
            return $matricule;
        }

        // Marque : même pattern que site/usage_norme_ref/matricule ci-dessus.
        $marque = $this->listeParametreValidee($request, 'marque', Camion::whereNotNull('marque')->distinct()->pluck('marque')->all());
        if ($marque instanceof JsonResponse) {
            return $marque;
        }

        // Filtre "source_principale" — absent en query string : repli explicite sur ['SOURCE_A'],
        // PAS sur "toute la flotte" comme les 3 filtres ci-dessus.
        $sourcePrincipale = $this->listeParametreValidee($request, 'source_principale', self::SOURCES_VALIDES);
        if ($sourcePrincipale instanceof JsonResponse) {
            return $sourcePrincipale;
        }
        $sourcePrincipale ??= ['SOURCE_A'];

        // select() limité aux colonnes réellement lues plus bas (date/consommation_l100/ecoscore/
        // km_parcourus/conso_horaire, camion_id pour le rattachement de la relation)
        $camions = Camion::with(['mesures' => function ($q) {
            $q->exploitable()->select('id', 'camion_id', 'date', 'consommation_l100', 'ecoscore', 'km_parcourus', 'conso_horaire');
        }])
            ->when($site !== null, fn ($q) => $q->whereIn('groupe', $site))
            ->when($usage !== null, fn ($q) => $q->whereIn('usage_norme_ref', $usage))
            ->when($matricule !== null, fn ($q) => $q->whereIn('id', $matricule))
            ->when($marque !== null, fn ($q) => $q->whereIn('marque', $marque))
            ->whereIn('source_principale', $sourcePrincipale)
            ->get();

        $parGroupe = $camions->groupBy('groupe')->map(function ($groupe) use ($depuis, $jusquA) {
            $mesures = $groupe->flatMap->mesures;
            // null explicite (pas 0) si le site n'a aucune mesure exploitable sur toute la période
            // — 0 L/100km laisserait croire à une vraie valeur favorable plutôt qu'à une absence
            // de donnée. audit.blade.php gère déjà ce null (affiche "—"), c'était le comportement
            // attendu.
            $consoReelle = $mesures->isNotEmpty() ? round($mesures->avg('consommation_l100'), 2) : null;

            $objectif = $groupe->first()->objectif_conso ?? 35;
            $serInitiale = $groupe->first()->conso_initiale_ser;

            // Sous-ensemble scopé à la période (depuis/jusqu_a) — mêmes bornes que
            // $mesuresPeriode/ $mesuresCamion plus bas, pour que "Répartition par site" bouge avec
            // la période comme les KPI globaux et le classement par camion.
            $mesuresPeriodeGroupe = $this->filtrerParPeriode($mesures, $depuis, $jusquA);

            // Tendance récente : scopée sur depuis/jusqu_a quand une période est explicitement
            // demandée — sinon (aucune borne, "Tout l'historique" sélectionné) repli sur une
            // fenêtre glissante des 6 derniers mois.
            if ($depuis !== null || $jusquA !== null) {
                $consoRecente = $mesuresPeriodeGroupe->isNotEmpty() ? round($mesuresPeriodeGroupe->avg('consommation_l100'), 2) : null;
            } else {
                $depuisSixMois = now()->subMonths(6)->format('Y-m-d');
                $recentes = $this->filtrerParPeriode($mesures, $depuisSixMois, null);
                $consoRecente = $recentes->isNotEmpty() ? round($recentes->avg('consommation_l100'), 2) : null;
            }

            // null explicite (pas 0) si le groupe n'a aucune mesure exploitable sur la fenêtre
            // considérée — même règle et même raison que conso_moyenne_globale/consoReelle
            // ci-dessus : ou "0,0 L/100km" laisserait croire à une vraie valeur favorable plutôt
            // qu'à une absence de donnée.
            $mesuresAvecEcoscoreGroupe = $mesuresPeriodeGroupe->where('ecoscore', '>', 0);
            $ecoScoreMoyenGroupe = $mesuresAvecEcoscoreGroupe->isNotEmpty() ? round($mesuresAvecEcoscoreGroupe->avg('ecoscore'), 2) : null;

            return [
                'nb_camions' => $groupe->count(),
                'nb_mesures_exploitables' => $mesuresPeriodeGroupe->count(),
                'conso_moyenne_globale' => $consoReelle,
                'conso_moyenne_6_derniers_mois' => $consoRecente,
                'objectif_officiel' => $objectif,
                'ser_initiale' => $serInitiale,
                // null propagé si conso_moyenne_globale l'est déjà (cf. commentaire ci-dessus) — un
                // écart calculé contre une consommation absente n'aurait aucun sens.
                'ipe_vs_objectif_pct' => $consoReelle !== null
                    ? round((($consoReelle - $objectif) / $objectif) * 100, 1)
                    : null,
                // null propagé si consoRecente l'est (période/fenêtre sans mesure) — même raison que
                // ci-dessus, un écart contre une consommation absente n'aurait aucun sens.
                'tendance_recente_vs_ser_pct' => ($serInitiale && $consoRecente !== null)
                    ? round((($serInitiale - $consoRecente) / $serInitiale) * 100, 1)
                    : null,
                'eco_score_moyen' => $ecoScoreMoyenGroupe,
            ];
        });

        [$coutCarburantMoyen, $coutCarburantEstime] = $this->coutCarburantFlotte($camions);

        $tousLesMesures = $camions->flatMap->mesures;

        // Sous-ensemble scopé à la période (depuis/jusqu_a) — fait maintenant autorité pour TOUS
        // les KPI de "Vue d'ensemble" sensibles à une fenêtre temporelle (Eco Score, Conso.
        // moyenne, Distance totale, Mesures exploitables)
        $mesuresPeriode = $this->filtrerParPeriode($tousLesMesures, $depuis, $jusquA);

        // Requête indépendante de $camions (qui ne charge QUE les mesures exploitables) : il faut
        // TOUTES les mesures (y compris lacunaires/en cours) pour ces 3 compteurs, sur la même
        // fenêtre depuis/jusqu_a que $mesuresPeriode ci-dessus. whereIn sur les camions déjà filtrés
        // par site/usage_norme_ref/matricule ci-dessus — sans ça, "Mesures exploitables X/Y" resterait
        // calculé sur TOUTE la flotte pour Y (nb_mesures_totales) même quand X (nb_mesures_exploitables,
        // dérivé de $mesuresPeriode ci-dessus, déjà correctement filtré) ne l'est plus.
        $mesuresToutesPeriodeQuery = Mesure::whereIn('camion_id', $camions->pluck('id'));
        if ($depuis !== null) {
            $mesuresToutesPeriodeQuery->where('date', '>=', $depuis);
        }
        if ($jusquA !== null) {
            $mesuresToutesPeriodeQuery->where('date', '<=', $jusquA);
        }
        $nbMesuresTotales = (clone $mesuresToutesPeriodeQuery)->count();
        // Compartiment à part entière, pas une variante de "lacunaire" : une ligne est_partielle
        // porte une vraie donnée (juste incomplète), contrairement à une mesure absente/aberrante.
        // À exclure du calcul de nb_mesures_lacunaires ci-dessous pour ne pas la compter deux fois.
        $nbMesuresEnCours = (clone $mesuresToutesPeriodeQuery)->where('est_partielle', true)->count();

        // null explicite (pas 0) quand la période/le filtre ne laisse aucune mesure exploitable —
        // même règle et même raison que conso_moyenne_globale par groupe plus haut : 0 L/100km ou
        // laisserait croire à une vraie valeur favorable plutôt qu'à une absence de donnée (ex:
        // site=__AUCUN__, 0 camion sélectionné)
        $mesuresAvecEcoscore = $mesuresPeriode->where('ecoscore', '>', 0);
        $ecoScoreMoyen = $mesuresAvecEcoscore->isNotEmpty() ? round($mesuresAvecEcoscore->avg('ecoscore'), 2) : null;
        $consoMoyenne = $mesuresPeriode->isNotEmpty() ? round($mesuresPeriode->avg('consommation_l100'), 2) : null;
        // conso_horaire (L/h, Télématique A) n'est PAS dans ZERO_SIGNIFIE_ABSENCE (cf.
        // CourbesController) : 0 y serait une vraie valeur, pas une absence, donc pas de filtre '>
        // 0' ici comme pour eco_score_moyen.
        $mesuresAvecConsoHoraire = $mesuresPeriode->whereNotNull('conso_horaire');
        $consoHoraireMoyenne = $mesuresAvecConsoHoraire->isNotEmpty() ? round($mesuresAvecConsoHoraire->avg('conso_horaire'), 2) : null;

        // Litres consommés — somme RÉELLE par mesure (consommation_l100 * km_parcourus / 100), pas
        // une moyenne : un volume physique de carburant, additif comme distance_totale ci-dessous
        // (donc même traitement Télématique B que la distance — addition PAR VÉHICULE plus bas —
        // plutôt que le repli en bloc de conso_moyenne.
        $litresSourceA = $mesuresPeriode->sum(fn ($m) => ($m->consommation_l100 * ($m->km_parcourus ?? 0)) / 100);

        // Repli Télématique B pour le bandeau des 5 KPI.
        $repliSourceBDesactive = $request->query('desactiver_repli_source_b') === '1';

        $camionsSansSourceA = collect();
        $camionsSourceBFiables = collect();
        $consoMoyenneSource = 'source_a';
        $distanceSourceBAjoutee = 0.0;
        $nbCamionsDistanceSourceB = 0;
        $litresSourceBAjoutee = 0.0;
        $nbCamionsLitresSourceB = 0;
        $nbMesuresExploitablesSourceB = 0;
        $nbMesuresTotalesSourceB = 0;
        $nbSourceBSansDistance = 0;
        // Camions Télématique B ayant réellement contribué au moins un intervalle DANS LA PÉRIODE
        // demandée — distinct de $camionsSourceBFiables (l'éligibilité ne dépend pas de la
        // période) : sert uniquement à qualite_mesures.camions.source_b/non_comptes.
        $idsSourceBAvecDonneesPeriode = collect();

        if (!$repliSourceBDesactive) {
            $camionsSansSourceA = $camions->filter(fn ($c) => $c->mesures->isEmpty());
            $camionsSourceBFiables = $camionsSansSourceA->filter(
                fn ($c) => in_array($c->immatriculation, VehiculesSourceB::PLAQUES_CONSOMMATION_FIABLE, true)
            );

            // Repli en bloc de conso_moyenne (badge "Source Télématique B") — VOLONTAIREMENT PAS
            // scopé par depuis/jusqu_a, contrairement aux 4 KPI ci-dessous : resumePourCamion()
            // calcule une moyenne pondérée sur l'historique complet de chaque véhicule.
            if ($consoMoyenne === null && $camions->isNotEmpty() && $camionsSansSourceA->count() === $camions->count()) {
                $resumesSourceB = $camionsSourceBFiables->map(fn ($c) => ConsommationSourceB::resumePourCamion($c->id))->filter();

                if ($resumesSourceB->isNotEmpty()) {
                    // Repondération sur la distance totale de chaque véhicule — resumePourCamion() a
                    // déjà pondéré ses propres intervalles (somme volume/somme distance), ce calcul
                    // pondère à son tour les véhicules entre eux par leur propre distance totale,
                    // même principe que ConsommationSourceB::resumer() (jamais une moyenne simple,
                    // faussée par les véhicules à faible distance cumulée).
                    $sommeVolumeEquivalent = $resumesSourceB->sum(fn ($r) => $r['consommation_l100km'] * $r['distance_totale_km'] / 100);
                    $sommeDistanceSourceB = $resumesSourceB->sum('distance_totale_km');

                    if ($sommeDistanceSourceB > 0) {
                        $consoMoyenne = round($sommeVolumeEquivalent / $sommeDistanceSourceB * 100, 2);
                        $consoMoyenneSource = 'source_b';
                    }
                }
            }

            foreach ($camionsSourceBFiables as $c) {
                // Distance (mesures_source_b, source=mileage, une ligne par jour) — : - scopée sur
                // depuis/jusqu_a via whereDate(horodatage_record) quand une période est demandée ;
                // - exclut les jours de kilométrage CORROMPU, mêmes bornes que
                // KilometrageSourceBSource::pourCamion() (qui, elle.
                $distanceQuery = MesureSourceB::where('camion_id', $c->id)
                    ->where('source', 'mileage')
                    ->where('distance_km', '>=', -KilometrageSourceBSource::TOLERANCE_NEGATIVE_KM)
                    ->where('distance_km', '<=', KilometrageSourceBSource::DISTANCE_JOUR_MAX_KM);
                if ($depuis !== null) {
                    $distanceQuery->whereDate('horodatage_record', '>=', $depuis);
                }
                if ($jusquA !== null) {
                    $distanceQuery->whereDate('horodatage_record', '<=', $jusquA);
                }
                $distanceCamionSourceB = (float) $distanceQuery->sum('distance_km');

                if ($distanceCamionSourceB > 0) {
                    $distanceSourceBAjoutee += $distanceCamionSourceB;
                    $nbCamionsDistanceSourceB++;
                }

                // Volume RÉEL de carburant acheté (consommations_source_b.volume_l, relevé à
                // chaque plein), pas une estimation dérivée d'un taux — même source que
                // resumer()::$sommeVolume, mais exposée directement ici plutôt que via
                // resumePourCamion() (qui ne la retourne pas)
                $consoQueryCamion = ConsommationSourceB::where('camion_id', $c->id);
                if ($depuis !== null) {
                    $consoQueryCamion->whereDate('plein_suivant_date', '>=', $depuis);
                }
                if ($jusquA !== null) {
                    $consoQueryCamion->whereDate('plein_suivant_date', '<=', $jusquA);
                }

                $litresCamionSourceB = (float) (clone $consoQueryCamion)->whereNotNull('consommation_l100km')->sum('volume_l');

                if ($litresCamionSourceB > 0) {
                    $litresSourceBAjoutee += $litresCamionSourceB;
                    $nbCamionsLitresSourceB++;
                }

                $nbIntervallesCamion = (clone $consoQueryCamion)->count();
                if ($nbIntervallesCamion > 0) {
                    $idsSourceBAvecDonneesPeriode->push($c->id);
                }
                $nbMesuresTotalesSourceB += $nbIntervallesCamion;
                // « Exploitable » = consommation calculée ET dans les bornes [5, 80] L/100km —
                // même borne que la plausibilité d'une moyenne Télématique B, appliquée ici à
                // l'intervalle individuel.
                $nbMesuresExploitablesSourceB += (clone $consoQueryCamion)->exploitable()->count();
                $nbSourceBSansDistance += (clone $consoQueryCamion)->whereNull('consommation_l100km')->count();
            }
        }

        $nbMesuresTotalesFinal = $nbMesuresTotales + $nbMesuresTotalesSourceB;
        $nbMesuresExploitablesFinal = $mesuresPeriode->count() + $nbMesuresExploitablesSourceB;

        // Répartition PAR RAISON des mesures comptées ci-dessus.
        $sourceAClos = (clone $mesuresToutesPeriodeQuery)->where('est_partielle', false);
        $sourceASansDonnee = (clone $sourceAClos)->whereNull('consommation_l100')->count();
        $sourceAAZero = (clone $sourceAClos)->where('consommation_l100', 0)->count();
        $sourceAHorsBornes = (clone $sourceAClos)
            ->where(fn ($q) => $q->where('consommation_l100', '<', 0)
                ->orWhere('consommation_l100', '>', Mesure::CONSO_L100_MAX_PLAUSIBLE))
            ->count();
        $idsAvecSourceA = (clone $mesuresToutesPeriodeQuery)->distinct()->pluck('camion_id');
        // idsSourceBAvecDonneesPeriode (pas camionsSourceBFiables->pluck('id')) : ne compte que
        // les camions ayant réellement un intervalle dans la période demandée, symétrique à
        // idsAvecSourceA ci-dessus (déjà construit depuis une requête period-aware)
        $idsAvecSourceB = $idsSourceBAvecDonneesPeriode->unique()->values();
        $qualiteMesures = [
            'source_a' => [
                'total' => $nbMesuresTotales,
                'exploitables' => $mesuresPeriode->count(),
                'en_cours' => $nbMesuresEnCours,
                // Mois (AAAA-MM) des lignes est_partielle : pas forcément le seul mois calendaire
                // courant (un mois non encore clôturé reste partiel).
                'en_cours_mois' => (clone $mesuresToutesPeriodeQuery)->where('est_partielle', true)->get(['date'])
                    ->map(fn ($m) => $m->date->format('Y-m'))->unique()->sort()->values()->all(),
                'exclues_sans_donnee' => $sourceASansDonnee,
                'exclues_a_zero' => $sourceAAZero,
                'exclues_hors_bornes' => $sourceAHorsBornes,
                'bornes_max_l100' => Mesure::CONSO_L100_MAX_PLAUSIBLE,
            ],
            'source_b' => [
                'total' => $nbMesuresTotalesSourceB,
                'exploitables' => $nbMesuresExploitablesSourceB,
                // consommations_source_b.consommation_l100km n'est NULL que si la distance de l'intervalle
                // est introuvable (aucun kilométrage Télématique B sur ces jours).
                'exclues_sans_distance' => $nbSourceBSansDistance,
                // Consommation calculée mais hors des bornes de plausibilité : le complément exact.
                'exclues_hors_bornes' => $nbMesuresTotalesSourceB - $nbMesuresExploitablesSourceB - $nbSourceBSansDistance,
                'bornes_l100' => ConsommationSourceB::bornesPlausiblesL100km(),
            ],
            'camions' => [
                'source_a' => $idsAvecSourceA->count(),
                'source_b' => $idsAvecSourceB->diff($idsAvecSourceA)->count(),
                // Véhicules de la sélection qui ne contribuent à AUCUN des deux compteurs (ex. véhicule
                // Télématique B dont la consommation n'est pas jugée fiable, cf. PLAQUES_CONSOMMATION_FIABLE).
                'non_comptes' => $camions->pluck('id')->diff($idsAvecSourceA)->diff($idsAvecSourceB)->count(),
            ],
        ];

        // null explicite (jamais 0) si ni Télématique A ni Télématique B n'apportent de mesure sur
        // la sélection — même règle que conso_moyenne/eco_score_moyen plus haut : un "0 L"
        // laisserait croire à une vraie absence de consommation plutôt qu'à une absence de donnée.
        $litresConsommes = ($mesuresPeriode->isNotEmpty() || $nbCamionsLitresSourceB > 0)
            ? round($litresSourceA + $litresSourceBAjoutee, 2)
            : null;
        // Prix fixe (PRIX_DEFAUT_REPLI, 1,985 TND/L — même valeur que le repli Fiche camion/Vue
        // Direction quand prix_carburant_historique ne couvre pas une date) — volontairement PAS le
        // calcul prix-en-vigueur-par-mesure de coutCarburantFlotte()/cout_carburant_moyen ci-dessous
        // (qui répond à une question différente : un coût MOYEN au km, historique). Ici on veut un
        // montant total simple sur la période, cohérent avec litres_consommes lui-même déjà agrégé.
        $montantCarburant = $litresConsommes !== null ? round($litresConsommes * self::PRIX_DEFAUT_REPLI, 2) : null;

        // Règle de transparence — `source_b_disponible` (par_camion, ci-dessous) n'est plus gaté
        // par PLAQUES_CONSOMMATION_FIABLE : un seul calcul groupé (pas de N+1 dans la boucle
        // par_camion) pour savoir quels camions ont AU MOINS un calcul Télématique B (plausible ou
        // non)
        $camionIdsAvecConsommationSourceB = ConsommationSourceB::whereNotNull('consommation_l100km')
            ->distinct()
            ->pluck('camion_id')
            ->all();

        return response()->json([
            'nb_camions' => $camions->count(),
            'nb_mesures_totales' => $nbMesuresTotalesFinal,
            'nb_mesures_exploitables' => $nbMesuresExploitablesFinal,
            'nb_mesures_exploitables_inclut_source_b' => $nbMesuresExploitablesSourceB > 0,
            'nb_mesures_en_cours' => $nbMesuresEnCours,
            'nb_mesures_lacunaires' => $nbMesuresTotalesFinal - $nbMesuresExploitablesFinal - $nbMesuresEnCours,
            'qualite_mesures' => $qualiteMesures,
            'eco_score_moyen' => $ecoScoreMoyen,
            'conso_moyenne' => $consoMoyenne,
            'conso_moyenne_source' => $consoMoyenneSource,
            'conso_horaire_moyenne' => $consoHoraireMoyenne,
            'nb_mesures_conso_horaire' => $mesuresAvecConsoHoraire->count(),
            'nb_mesures_periode' => $mesuresPeriode->count(),
            'distance_totale' => round($mesuresPeriode->sum('km_parcourus') + $distanceSourceBAjoutee, 2),
            'distance_totale_inclut_source_b' => $nbCamionsDistanceSourceB > 0,
            'litres_consommes' => $litresConsommes,
            'litres_consommes_inclut_source_b' => $nbCamionsLitresSourceB > 0,
            'montant_carburant' => $montantCarburant,
            'montant_carburant_prix_l' => self::PRIX_DEFAUT_REPLI,
            // Moyenne (non pondérée) du coût/km de chaque camion, chaque mesure mensuelle utilisant
            // le prix en vigueur à sa propre date (table prix_carburant_historique) — même logique
            // que prixEnVigueur()/calculerCoutCarburant() côté Fiche camion. Porte sur l'historique
            // complet des mesures exploitables, pas sur la période depuis/jusqu_a (jamais appliquée
            // à ce calcul côté client non plus).
            'cout_carburant_moyen' => $coutCarburantMoyen,
            'cout_carburant_estime' => $coutCarburantEstime,
            'par_groupe' => $parGroupe,
            // eco_score_moyen/conso_moyenne portent sur la période (depuis/jusqu_a) quand elle est
            // fournie, sinon sur l'historique complet — même fenêtre que les 2 KPI globaux ci-dessus,
            // pour que le classement par camion reste cohérent avec la période sélectionnée.
            'par_camion' => $camions->map(function ($c) use ($depuis, $jusquA, $camionIdsAvecConsommationSourceB) {
                // Cible norme interne officielle du véhicule.
                $cibleNormeRef = $this->norme_ref->uniteSer($c) === 'l/100km' ? $c->ser_norme_ref_cible : null;

                $mesuresCamion = $this->filtrerParPeriode($c->mesures, $depuis, $jusquA);

                // null explicite (pas 0) si le camion n'a aucune mesure exploitable sur la période
                // — même règle que conso_moyenne_globale par groupe plus haut.
                $consoMoyenne = $mesuresCamion->isNotEmpty() ? round($mesuresCamion->avg('consommation_l100'), 2) : null;
                $mesuresAvecEcoscore = $mesuresCamion->where('ecoscore', '>', 0);
                $ecoScoreMoyen = $mesuresAvecEcoscore->isNotEmpty() ? round($mesuresAvecEcoscore->avg('ecoscore'), 2) : null;

                return [
                    'id' => $c->id,
                    'immatriculation' => $c->immatriculation,
                    'groupe' => $c->groupe,
                    'source_principale' => $c->source_principale,
                    // Exposé directement ici pour que camionCorrespondFiltresGlobaux()
                    // (dashboard.blade.php) le lise sans lookup de secours — même principe que
                    // groupe/source_principale juste au-dessus, contrairement à usage_norme_ref
                    // qui n'est pas exposé ici et nécessite usageNormeRefParCamionId()
                    'marque' => $c->marque,
                    'nb_mesures_exploitables' => $mesuresCamion->count(),
                    'eco_score_moyen' => $ecoScoreMoyen,
                    'conso_moyenne' => $consoMoyenne,
                    'objectif_conso' => $cibleNormeRef,
                    'objectif_atteint' => ($consoMoyenne !== null && $cibleNormeRef !== null) ? $consoMoyenne <= $cibleNormeRef : null,
                    // Info statique saisie manuellement depuis le portail Télématique B, jamais
                    // pour les 6 camions SITE B (absents du parc Télématique B) — pas de fraîcheur
                    // garantie.
                    'conducteur_source_b' => $c->conducteur_source_b,
                    // Badge "+Télématique B" — un camion en source Télématique A
                    // (source_principale 'SOURCE_A') peut quand même avoir un boîtier Télématique
                    // B réel mappé (ex: DEMO-003), distinct de source_principale='SOURCE_B'.
                    'source_b_complementaire' => $c->source_principale !== 'SOURCE_B'
                        && array_key_exists($c->immatriculation, VehiculesSourceB::DEVICE_PAR_PLAQUE),
                    // Repli d'affichage — quand ce camion n'a AUCUNE mesure Télématique A
                    // (conso_moyenne null ci-dessus : soit un véhicule en source Télématique B,
                    // soit un camion Télématique A sans mesure sur la période), le frontend a
                    // besoin de savoir si une consommation Télématique B existe malgré tout.
                    'source_b_disponible' => in_array($c->id, $camionIdsAvecConsommationSourceB, true),
                ];
            })->sortByDesc('eco_score_moyen')->values(),
        ]);
    }
    // Agrège les mesures Télématique A par site (SITE A/SITE B) et par mois, pour comparer les deux
    // sites sur une courbe (moyenne Eco Score, moyenne consommation), plutôt que camion par camion.
    public function comparateurSites()
    {
        $lignes = DB::table('mesures')
            ->join('camions', 'camions.id', '=', 'mesures.camion_id')
            ->whereIn('camions.groupe', ['SITE A', 'SITE B'])
            // Le mois en cours (est_partielle) ne doit jamais entrer dans une moyenne mélangée à
            // des mois clos — même règle que scopeExploitable()/ProjectionController/
            // CourbesController::annuel()
            ->where('mesures.est_partielle', false)
            ->selectRaw("DATE_FORMAT(mesures.date, '%Y-%m') as mois")
            ->addSelect('camions.groupe as site')
            ->selectRaw('AVG(CASE WHEN mesures.ecoscore > 0 THEN mesures.ecoscore END) as eco_score_moyen')
            ->selectRaw('AVG(CASE WHEN mesures.consommation_l100 > 0 AND mesures.consommation_l100 <= ' . Mesure::CONSO_L100_MAX_PLAUSIBLE . ' THEN mesures.consommation_l100 END) as conso_moyenne')
            ->selectRaw('COUNT(*) as nb_mesures')
            ->groupBy('mois', 'camions.groupe')
            ->orderBy('mois')
            ->get();

        return response()->json($lignes->map(fn($l) => [
            'mois' => $l->mois,
            'site' => $l->site,
            'eco_score_moyen' => $l->eco_score_moyen !== null ? round((float) $l->eco_score_moyen, 2) : null,
            'conso_moyenne' => $l->conso_moyenne !== null ? round((float) $l->conso_moyenne, 2) : null,
            'nb_mesures' => (int) $l->nb_mesures,
        ]));
    }

    // Lecture seule : la détection des alertes tourne via le scheduler (alertes:detecter), jamais à chaque appel.
    public function alertes()
{
    // Toutes les analyses correctives, groupées par camion_id — plus de rattachement par mois.
    $analysesParCamion = AnalyseCorrective::orderByDesc('mois')->get()->groupBy('camion_id');

    return response()->json(
        Alerte::with('camion')->orderByDesc('created_at')->get()->map(function ($a) use ($analysesParCamion) {
            $analyses = $analysesParCamion->get($a->camion_id, collect());

            return [
                'id' => $a->id,
                'camion' => $a->camion->immatriculation,
                'groupe' => $a->camion->groupe,
                'type' => $a->type,
                'severite' => $a->severite,
                'message' => $a->message,
                'valeur' => $a->valeur,
                'seuil' => $a->seuil,
                'resolue' => $a->resolue,
                'date_detection' => $a->created_at,
                'date_resolution' => $a->date_resolution,
                // Carbon 3 : diffInDays() est signé (négatif si l'appelant est postérieur à l'argument)
                // et retourne un float, pas un int — d'où le cast explicite après avoir mis la date la
                // plus ancienne en premier.
                'jours_actifs' => (int) $a->created_at->diffInDays(now()),
                // Pluriel (tableau, potentiellement vide) — un camion peut avoir plusieurs analyses,
                // contrairement à l'ancien champ singulier 'analyse' qui n'en retenait qu'une seule
                // (celle du mois correspondant, quand elle existait).
                'analyses' => $analyses->map(fn ($an) => [
                    'mois' => $an->mois->format('Y-m'),
                    'cause' => $an->cause,
                    'action' => $an->action,
                    'responsable' => $an->responsable,
                    'date_realisation' => $an->date_realisation,
                    'statut_suivi' => $an->statut_suivi,
                    'methode_mesure' => $an->methode_mesure,
                    'suivi_efficacite' => $an->suivi_efficacite,
                    'source' => $an->source,
                ])->values(),
            ];
        })
    );
}

    // Types d'alertes possibles (source unique : AlerteService::TYPES) — alimente le filtre « Type d'alerte » du
    // tableau de l'Accueil. Liste des types que la détection PEUT produire, pas seulement de ceux actuellement actifs.
    public function typesAlertes()
    {
        return response()->json(collect(AlerteService::TYPES)
            ->map(fn ($libelle, $type) => ['type' => $type, 'libelle' => $libelle])
            ->values());
    }

    // Toutes les analyses correctives d'un camion (WHERE camion_id), indépendamment de tout
    // rattachement à une alerte — source directe pour Synthèse camion.
    public function analysesCorrectives(int $camion_id)
    {
        Camion::findOrFail($camion_id);

        return response()->json(
            AnalyseCorrective::where('camion_id', $camion_id)
                ->orderByDesc('mois')
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'mois' => $a->mois->format('Y-m'),
                    'cause' => $a->cause,
                    'action' => $a->action,
                    'responsable' => $a->responsable,
                    'date_realisation' => $a->date_realisation,
                    'statut_suivi' => $a->statut_suivi,
                    'methode_mesure' => $a->methode_mesure,
                    'suivi_efficacite' => $a->suivi_efficacite,
                    'source' => $a->source,
                ])
        );
    }

    // Consommation Télématique B calculée "plein à plein" — volontairement PAS dans
    // mesures/resumeFlotte (source Télématique A) ni mêlée à norme interne/SER : c'est une
    // observation complémentaire, distincte.
    public function consommationSourceB(int $camion_id)
    {
        Camion::findOrFail($camion_id);

        $resume = ConsommationSourceB::resumePourCamion($camion_id);

        if (!$resume) {
            return response()->json(['disponible' => false]);
        }

        return response()->json(array_merge(
            ['disponible' => true, 'statut_plausibilite' => ConsommationSourceB::statutPlausibilite($resume)],
            $resume
        ));
    }

    // Avertissement « kilométrage source corrompu » : indique si le kilométrage journalier
    // Télématique B (mesures_source_b, source mileage) de ce véhicule contient des jours négatifs
    // ou aberrants (> 1 000 km)
    public function kilometrageSourceB(int $camion_id)
    {
        return response()->json(KilometrageSourceBSource::pourCamion(Camion::findOrFail($camion_id)));
    }

    public function meteo(WeatherService $service)
    {
        return response()->json($service->getMeteoParSite());
    }

    // Découpe un paramètre de requête en liste séparée par virgules et valide chaque valeur par
    // whitelist.
    private function listeParametreValidee(Request $request, string $nom, array $valeursValides): array|JsonResponse|null
    {
        $brut = $request->query($nom);
        if ($brut === null) {
            return null;
        }
        if ($brut === self::AUCUNE_VALEUR) {
            return [];
        }

        $valeurs = explode(',', $brut);
        foreach ($valeurs as $v) {
            if (!in_array($v, $valeursValides, true)) {
                return response()->json(['message' => "Paramètre {$nom} invalide."], 422);
            }
        }

        return $valeurs;
    }

    // Filtre commun depuis/jusqu_a réutilisé par resumeFlotte() (KPI globaux, par_groupe,
    // par_camion) — pour que les 3 endroits ne divergent jamais sur la définition de "dans la
    // période". $mesures !== null uniquement : sans borne, la collection est retournée telle
    // quelle (pas de copie inutile).
    private function filtrerParPeriode($mesures, ?string $depuis, ?string $jusquA)
    {
        if ($depuis === null && $jusquA === null) {
            return $mesures;
        }

        return $mesures->filter(function ($m) use ($depuis, $jusquA) {
            $date = $m->date->format('Y-m-d');
            if ($depuis !== null && $date < $depuis) return false;
            if ($jusquA !== null && $date > $jusquA) return false;
            return true;
        });
    }

    private function coutCarburantFlotte($camions): array
    {
        $historique = PrixCarburantHistorique::orderBy('date_debut')->get();

        $parCamion = $camions->map(function ($c) use ($historique) {
            $mesures = $c->mesures->filter(fn ($m) => $m->km_parcourus > 0);
            if ($mesures->isEmpty()) {
                return null;
            }

            $coutTotal = 0.0;
            $kmTotal = 0.0;
            $estime = false;

            foreach ($mesures as $m) {
                $litres = ($m->consommation_l100 * $m->km_parcourus) / 100;
                [$prix, $prixEstime] = $this->prixEnVigueur($m->date->format('Y-m-d'), $historique);
                $coutTotal += $litres * $prix;
                $kmTotal += $m->km_parcourus;
                if ($prixEstime) {
                    $estime = true;
                }
            }

            return ['cout_km' => $kmTotal > 0 ? $coutTotal / $kmTotal : 0.0, 'estime' => $estime];
        })->filter();

        if ($parCamion->isEmpty()) {
            return [null, false];
        }

        return [
            round($parCamion->avg('cout_km'), 4),
            $parCamion->contains(fn ($r) => $r['estime']),
        ];
    }

    // Même règle que prixEnVigueur() côté Fiche camion : prix HT en vigueur à une date donnée
    // d'après prix_carburant_historique, repli sur PRIX_DEFAUT_REPLI (avec estime=true) si aucune
    // période de l'historique ne couvre cette date.
    private function prixEnVigueur(string $date, $historique): array
    {
        $periode = $historique->first(function ($p) use ($date) {
            $debut = $p->date_debut->format('Y-m-d');
            $fin = $p->date_fin?->format('Y-m-d');
            return $date >= $debut && ($fin === null || $date <= $fin);
        });

        return $periode
            ? [(float) $periode->prix_ht, false]
            : [self::PRIX_DEFAUT_REPLI, true];
    }
}
