<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\CamionController;
use App\Http\Controllers\Api\SuggestionController;
use App\Models\Camion;
use App\Models\Mesure;
use App\Models\RegistreMesure;
use App\Services\AlerteService;
use App\Services\SuggestionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RapportSnapshotProjet extends Command
{
    protected $signature = 'rapport:snapshot-projet';

    protected $description = "Exporte un instantané JSON du projet (résumé flotte, alertes actives, suggestions, comptage de couverture des données) — utile pour un point d'avancement";

    public function handle(
        CamionController $camionController,
        SuggestionController $suggestionController,
        AlerteService $alerteService,
        SuggestionService $suggestionService
    ): int {
        // Même appel que GET /api/flotte/resume sans depuis/jusqu_a : historique complet.
        $flotteResume = $camionController->resumeFlotte(request())->getData(true);

        // Même appel que GET /api/alertes (déclenche AlerteService::detecterEtEnregistrer() au passage),
        // on ne garde que les actives pour ce snapshot.
        $toutesLesAlertes = $camionController->alertes($alerteService)->getData(true);
        $alertesActives = array_values(array_filter($toutesLesAlertes, fn ($a) => !$a['resolue']));

        $suggestions = $suggestionController->index($suggestionService)->getData(true);

        // "Exploitable" reprend exactement les définitions déjà en place ailleurs dans le projet,
        // pour ne jamais afficher un chiffre de qualité incohérent avec le reste du dashboard :
        // Télématique A -> Mesure::scopeExploitable (consommation_l100 non nulle, > 0 et <= borne haute plausible),
        // registre manuel -> NormeRefStatutService::derniersMoisExploitables (heures>0 et litres renseignés,
        // ou pe_l100 renseigné).
        $suiviParCamion = Camion::with([
                'mesures:id,camion_id,date,consommation_l100',
                'registreMesures:id,camion_id,date,heures,litres,pe_l100',
            ])
            ->orderBy('groupe')->orderBy('immatriculation')->get()
            ->map(function ($c) {
                $moisSourceA = $c->mesures->map(fn ($m) => Carbon::parse($m->date)->format('Y-m'));
                $moisRegistre = $c->registreMesures->map(fn ($m) => Carbon::parse($m->date)->format('Y-m'));
                $moisSuivisTotal = $moisSourceA->merge($moisRegistre)->unique()->count();

                $sourceAExploitables = $c->mesures
                    ->filter(fn ($m) => Mesure::estValeurConsoExploitable($m->consommation_l100))
                    ->count();
                $registreExploitables = $c->registreMesures
                    ->filter(fn ($m) => ($m->heures > 0 && $m->litres !== null) || $m->pe_l100 !== null)
                    ->count();

                return [
                    'camion' => $c->immatriculation,
                    'groupe' => $c->groupe,
                    // Étendue du suivi : tout mois où AU MOINS une ligne existe (Télématique A OU registre manuel),
                    // exploitable ou non — répond à "a-t-on quelque chose pour ce mois ?".
                    'mois_suivis_total' => $moisSuivisTotal,
                    // Qualité des données dans cette étendue : combien de ces lignes sont réellement
                    // utilisables pour un calcul, par source (les deux sources ne se recoupent pas
                    // forcément mois par mois).
                    'mesures_exploitables' => [
                        'source_a' => $sourceAExploitables,
                        'registre' => $registreExploitables,
                    ],
                ];
            })->values();

        $snapshot = [
            'genere_le' => now()->toDateTimeString(),
            'flotte_resume' => $flotteResume,
            'alertes_actives' => $alertesActives,
            'suggestions' => $suggestions,
            'comptage' => [
                'nb_mesures_source_a_total' => Mesure::count(),
                'nb_mesures_registre_total' => RegistreMesure::count(),
                // Mêmes définitions "exploitable" que le détail par camion ci-dessous, agrégées sur
                // toute la flotte — à mettre en regard des deux totaux bruts juste au-dessus.
                'nb_mesures_source_a_exploitables' => Mesure::exploitable()->count(),
                'nb_mesures_registre_exploitables' => RegistreMesure::where(function ($q) {
                    $q->where(function ($q2) {
                        $q2->where('heures', '>', 0)->whereNotNull('litres');
                    })->orWhereNotNull('pe_l100');
                })->count(),
                'suivi_par_camion' => $suiviParCamion,
            ],
        ];

        $chemin = storage_path('app/export_snapshot_projet.json');
        file_put_contents($chemin, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->info("Snapshot exporté vers {$chemin}.");

        return Command::SUCCESS;
    }
}
