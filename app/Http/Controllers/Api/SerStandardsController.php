<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Camion;
use App\Models\RegistreMesure;
use App\Services\NormeRefStatutService;
use App\Support\Concerns\ValideDatesCalendaires;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SerStandardsController extends Controller
{
    use ValideDatesCalendaires;

    public function __construct(private NormeRefStatutService $norme_ref)
    {
    }

    // depuis / jusqu_a : fenêtre des 4 colonnes "réel" (IPÉ dans l'unité de la SER du véhicule,
    // km, heures, conso L/100km), TOUTES issues de Registre (registre_mesures) — jamais de
    // Télématique A/Télématique B ici.
    public function index(Request $request)
    {
        $depuis = $request->query('depuis');
        $jusquA = $request->query('jusqu_a');

        foreach (['depuis' => $depuis, 'jusqu_a' => $jusquA] as $nom => $valeur) {
            if ($valeur !== null && !$this->estDateCalendaireValide($valeur)) {
                return response()->json(['message' => "Paramètre {$nom} invalide, format attendu AAAA-MM-JJ."], 422);
            }
        }
        if ($depuis !== null && $jusquA !== null && $depuis > $jusquA) {
            return response()->json(['message' => 'Le paramètre depuis doit être antérieur ou égal à jusqu_a.'], 422);
        }

        $debutFenetre = $depuis !== null ? Carbon::createFromFormat('!Y-m-d', $depuis)->startOfMonth() : null;
        $finFenetre = $jusquA !== null ? Carbon::createFromFormat('!Y-m-d', $jusquA)->startOfMonth() : null;

        // Dernier mois pour lequel Registre a QUELQUE CHOSE, toute flotte confondue : borne haute
        // des "mois attendus".
        $dernierMoisFlotteBrut = RegistreMesure::max('date');
        $dernierMoisFlotte = $dernierMoisFlotteBrut
            ? Carbon::parse($dernierMoisFlotteBrut)->startOfMonth()
            : null;

        // eager-load obligatoire : même correctif N+1 que SerDynamiqueController —
        // NormeRefStatutService::derniersMoisExploitables() lit $camion->registreMesures en
        // propriété, sans requête à chaque appel UNIQUEMENT si la relation est déjà chargée ici.
        $camions = Camion::with('registreMesures')->orderBy('groupe')->orderBy('immatriculation')->get();

        return response()->json(
            $camions->map(function ($c) use ($debutFenetre, $finFenetre, $dernierMoisFlotte) {
                // Même logique que SerDynamiqueController/SuggestionService : la dernière mesure
                // registre manuel réellement exploitable, pas juste la plus récente par date — pour que
                // "Valeur réelle" ne diverge jamais du statut norme interne affiché ailleurs.
                $derniere = $this->norme_ref->derniersMoisExploitables($c, 1)->first();
                $valeurReelle = $derniere ? $this->norme_ref->calculerIpe($c, $derniere) : null;

                return [
                    'id' => $c->id,
                    'immatriculation' => $c->immatriculation,
                    'groupe' => $c->groupe,
                    'source_principale' => $c->source_principale,
                    'marque' => $c->marque,
                    'usage_norme_ref' => $c->usage_norme_ref,
                    'ser_norme_ref' => $c->ser_norme_ref,
                    'incertitude_pct' => $c->ser_norme_ref_incertitude,
                    'ser_1x' => $c->ser_norme_ref && $c->ser_norme_ref_incertitude
                        ? round($c->ser_norme_ref * (1 + $c->ser_norme_ref_incertitude), 2)
                        : null,
                    'cible' => $c->ser_norme_ref_cible,
                    'source_a_actif_depuis' => $c->source_a_actif_depuis,
                    'valeur_reelle' => $valeurReelle !== null ? round($valeurReelle, 2) : null,
                    // Unité de la SER/Seuil/Cible du véhicule : L/100km pour les usages Transport,
                    // L/h pour les autres.
                    'unite_ser' => $this->norme_ref->uniteSer($c),
                    'unite_reelle' => $derniere ? $this->norme_ref->uniteIpe($c, $derniere) : null,
                    // % SER/usage et P.E (l/h) précis — lus directement dans le registre (colonnes
                    // O/U de "Copy of Récap data studio" des fichiers norme interne mensuels, cf.
                    // sheets:sync-registre::synchroniserColonnesRecapDataStudio()), PAS recalculés
                    // ici.
                    'pct_ser_usage' => $derniere?->pct_ser_usage,
                    'pe_lh_precis' => $derniere?->pe_lh_precis,
                    // Litres consommés — valeur brute de registre_mesures.litres pour ce MÊME
                    // dernier mois exploitable (pas une somme sur plusieurs mois : cohérent avec
                    // les 2 autres colonnes ci-dessus, intrinsèquement mensuelles)
                    'litres_dernier_mois' => $derniere && $derniere->litres > 0 ? $derniere->litres : null,
                    'periode' => $this->agregatPeriode($c->registreMesures, $debutFenetre, $finFenetre, $dernierMoisFlotte, $this->norme_ref->uniteSer($c)),
                ];
            })
        );
    }

    /**
     * Agrégat Registre d'un camion sur la fenêtre (mois entiers)
     */
    private function agregatPeriode(Collection $mesures, ?Carbon $debut, ?Carbon $fin, ?Carbon $dernierMoisFlotte, string $uniteSer): array
    {
        $vide = [
            'unite' => $uniteSer,
            'ipe' => null, 'ipe_mois' => 0,
            'mois_periode' => 0,
            'ipe_lh' => null, 'ipe_lh_mois' => 0,
            'km' => null, 'km_mois' => 0,
            'heures' => null, 'heures_mois' => 0,
            'conso_l100' => null, 'conso_l100_mois' => 0,
        ];

        $datees = $mesures->filter(fn ($m) => $m->date !== null);
        if ($datees->isEmpty() || $dernierMoisFlotte === null) {
            return $vide;
        }

        $premierMois = Carbon::parse($datees->min('date'))->startOfMonth();
        $debutEff = $debut !== null && $debut->gt($premierMois) ? $debut : $premierMois;
        $finEff = $fin !== null && $fin->lt($dernierMoisFlotte) ? $fin : $dernierMoisFlotte;
        $indexMois = fn (Carbon $d) => $d->year * 12 + $d->month - 1;
        $moisPeriode = max(0, $indexMois($finEff) - $indexMois($debutEff) + 1);

        $dansFenetre = $datees->filter(function ($m) use ($debutEff, $finEff, $indexMois) {
            $i = $indexMois(Carbon::parse($m->date));

            return $i >= $indexMois($debutEff) && $i <= $indexMois($finEff);
        });

        $present = fn ($v) => $v !== null && $v > 0;
        $km = $dansFenetre->filter(fn ($m) => $present($m->km));
        $heures = $dansFenetre->filter(fn ($m) => $present($m->heures));
        $ipe = $dansFenetre->filter(fn ($m) => $present($m->heures) && $present($m->litres));
        $conso = $dansFenetre->filter(fn ($m) => $present($m->km) && $present($m->litres));

        $ipeLh = $ipe->isNotEmpty() ? round($ipe->sum('litres') / $ipe->sum('heures'), 2) : null;
        $consoL100 = $conso->isNotEmpty() ? round($conso->sum('litres') / $conso->sum('km') * 100, 2) : null;
        $enL100km = $uniteSer === 'l/100km';

        return [
            'unite' => $uniteSer,
            'ipe' => $enL100km ? $consoL100 : $ipeLh,
            'ipe_mois' => $enL100km ? $conso->count() : $ipe->count(),
            'mois_periode' => $moisPeriode,
            'ipe_lh' => $ipe->isNotEmpty() ? round($ipe->sum('litres') / $ipe->sum('heures'), 2) : null,
            'ipe_lh_mois' => $ipe->count(),
            'km' => $km->isNotEmpty() ? round($km->sum('km'), 0) : null,
            'km_mois' => $km->count(),
            'heures' => $heures->isNotEmpty() ? round($heures->sum('heures'), 1) : null,
            'heures_mois' => $heures->count(),
            'conso_l100' => $conso->isNotEmpty() ? round($conso->sum('litres') / $conso->sum('km') * 100, 2) : null,
            'conso_l100_mois' => $conso->count(),
        ];
    }
}
