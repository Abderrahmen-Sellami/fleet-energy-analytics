<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Console\Commands\Concerns\VehiculesSourceB;
use App\Http\Controllers\Api\CamionController;
use App\Http\Controllers\Api\SerDynamiqueController;
use App\Http\Controllers\Api\SuggestionController;
use App\Models\Camion;
use App\Models\ConsommationSourceB;
use App\Models\Mesure;
use App\Support\Concerns\ValideDatesCalendaires;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Rapport d'audit ISO 50001 — document autonome, distinct de l'impression écran
 * (dashboard/direction)
 */
class AuditController extends Controller
{
    use ValideDatesCalendaires;

    public function index(
        Request $request,
        CamionController $camionController,
        SerDynamiqueController $serDynamiqueController,
        SuggestionController $suggestionController
    ) {
        $depuis = $request->query('depuis');
        $jusquA = $request->query('jusqu_a');

        foreach (['depuis' => $depuis, 'jusqu_a' => $jusquA] as $nom => $valeur) {
            if ($valeur !== null && !$this->estDateCalendaireValide($valeur)) {
                abort(422, "Paramètre {$nom} invalide, format attendu AAAA-MM-JJ.");
            }
        }

        $confirme = $request->query('confirmer') === '1';
        $erreur = null;
        if ($confirme && $depuis !== null && $jusquA !== null && $depuis > $jusquA) {
            // Repli sur le formulaire plutôt qu'un rapport généré sur une plage inversée — même
            // validation que CamionController::resumeFlotte()
            $erreur = 'La date de début doit être antérieure ou égale à la date de fin.';
            $confirme = false;
        }

        // Formulaire de génération (resources/views/audit-formulaire.blade.php) tant qu'on n'a pas
        // les deux dates ET la confirmation explicite — jamais de génération "par défaut" implicite.
        // Repli "année civile en cours" uniquement pour préremplir le formulaire quand aucune période
        // n'a été suggérée par le lien d'origine (dashboard/direction) — un choix par défaut plausible
        // pour un rapport d'audit, jamais utilisé pour générer quoi que ce soit tant que l'utilisateur
        // ne valide pas explicitement le formulaire.
        if (!$confirme || $depuis === null || $jusquA === null) {
            return view('audit-formulaire', [
                'depuisDefaut' => $depuis ?: now()->startOfYear()->format('Y-m-d'),
                'jusquADefaut' => $jusquA ?: now()->format('Y-m-d'),
                'erreur' => $erreur,
            ]);
        }

        $request->query->set('source_principale', 'SOURCE_A,SOURCE_B');
        // Repli Télématique B de distance_totale/nb_mesures_exploitables désactivé pour ce
        // document précis — un rapport de conformité ISO 50001 figé ne doit jamais voir un de ses
        // KPI changer de méthodologie silencieusement pendant qu'un autre (Consommation moyenne)
        // reste pur Télématique A juste à côté.
        $request->query->set('desactiver_repli_source_b', '1');
        $resume = $camionController->resumeFlotte($request)->getData(true);

        $statutsSerParId = collect($serDynamiqueController->index()->getData(true))->keyBy('id');

        $dernieresMesuresSourceA = Camion::with(['mesures' => function ($q) {
            $q->exploitable()->orderByDesc('date')->limit(1);
        }])->get()->keyBy('id');

        // Mention honnête de la source par donnée (pas par véhicule exclu) : résumé de
        // consommation Télématique B pour les seuls véhicules de la whitelist
        // VehiculesSourceB::PLAQUES_CONSOMMATION_FIABLE — les autres véhicules Télématique B (sans
        // détection de plein, ou hors bornes de plausibilité) restent "—" dans le rapport.
        $consommationsSourceBParId = Camion::whereIn('immatriculation', VehiculesSourceB::PLAQUES_CONSOMMATION_FIABLE)
            ->get(['id'])
            ->mapWithKeys(fn ($c) => [$c->id => ConsommationSourceB::resumePourCamion($c->id)]);

        $camions = Camion::orderBy('groupe')->orderBy('immatriculation')->get()->map(function ($c) use ($statutsSerParId, $dernieresMesuresSourceA, $consommationsSourceBParId) {
            return (object) [
                'camion' => $c,
                'statut' => $statutsSerParId->get($c->id),
                'derniere_mesure_source_a' => $dernieresMesuresSourceA->get($c->id)?->mesures->first(),
                'consommation_source_b' => $consommationsSourceBParId->get($c->id),
            ];
        });

        // Bornées sur la période choisie du rapport (depuis/jusquA, garantis non-null à ce stade)
        $depuisFiltre = Carbon::parse($depuis)->startOfDay();
        $jusquAFiltre = Carbon::parse($jusquA)->endOfDay();

        $alertesReponse = collect($camionController->alertes()->getData(true));
        $alertes = $alertesReponse
            ->filter(fn ($a) => Carbon::parse($a['date_detection'])->between($depuisFiltre, $jusquAFiltre))
            ->sortByDesc('date_detection')
            ->values();

        // Même mécanisme que les alertes ci-dessus.
        $suggestionsReponse = collect($suggestionController->index()->getData(true));
        $suggestions = $suggestionsReponse
            ->filter(fn ($s) => Carbon::parse($s['date_detection'])->between($depuisFiltre, $jusquAFiltre))
            ->sortByDesc('date_detection')
            ->values();

        // Un seul calcul du libellé de période, réutilisé partout dans la vue.
        $periodeLabel = $depuisFiltre->format('d/m/Y') . ' → ' . Carbon::parse($jusquA)->format('d/m/Y');

        return view('audit', [
            'resume' => $resume,
            'camions' => $camions,
            'alertes' => $alertes,
            'suggestions' => $suggestions,
            'depuis' => $depuis,
            'jusquA' => $jusquA,
            'periodeLabel' => $periodeLabel,
            'consoMaxPlausible' => Mesure::CONSO_L100_MAX_PLAUSIBLE,
            'genereLe' => now(),
        ]);
    }
}
