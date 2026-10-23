<?php

namespace App\Http\Controllers\Api;

use App\Events\StatutSerMisAJour;
use App\Http\Controllers\Controller;
use App\Models\Camion;
use App\Services\NormeRefStatutService;
use App\Services\ReverbDisponibiliteService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SerDynamiqueController extends Controller
{
    // Dernier statut connu par camion, pour ne diffuser (cf. diffuserSiChangement()) que sur un vrai
    // changement — Cache::forever() plutôt qu'une colonne dédiée : c'est de la bookkeeping technique
    // pure (quel statut a déjà été vu), pas une donnée métier/audit qui mériterait une vraie table.
    private const CACHE_PREFIX_DERNIER_STATUT = 'ser_statut_dernier_connu:';

    public function __construct(
        private NormeRefStatutService $norme_ref,
        private ReverbDisponibiliteService $reverbDisponibilite
    ) {
    }

    public function index()
    {
        // Eager-load : NormeRefStatutService lit $camion->registreMesures en propriété (pas de N+1).
        $camions = Camion::with('registreMesures')->get();

        // Sondé une seule fois pour toute la flotte, jamais par camion dans la boucle ci-dessous.
        $reverbDisponible = app()->runningUnitTests() ? false : $this->reverbDisponibilite->disponible();

        return response()->json($camions->map(function ($c) use ($reverbDisponible) {
            $derniere = $this->norme_ref->derniersMoisExploitables($c, 1)->first();

            if (!$derniere || !$c->ser_norme_ref) {
                $statutSer = [
                    'id' => $c->id,
                    'immatriculation' => $c->immatriculation,
                    'groupe' => $c->groupe,
                    'source_principale' => $c->source_principale,
                    'marque' => $c->marque,
                    'statut' => 'DONNEES_INSUFFISANTES',
                    'unite_ser' => $this->norme_ref->uniteSer($c),
                    'raison_insuffisance' => $this->norme_ref->raisonDonneesInsuffisantes($c, $derniere),
                    'source_a_actif_depuis' => $c->source_a_actif_depuis,
                ];
            } else {
                $statutInfo = $this->norme_ref->calculerStatut($c, $derniere);

                $statutSer = [
                    'id' => $c->id,
                    'immatriculation' => $c->immatriculation,
                    'groupe' => $c->groupe,
                    'source_principale' => $c->source_principale,
                    'marque' => $c->marque,
                    'unite' => $statutInfo['unite'],
                    'unite_ser' => $statutInfo['unite_ser'],
                    'ipe' => $statutInfo['ipe'],
                    'ser' => $c->ser_norme_ref,
                    'ser_1x' => round($statutInfo['ser1x'], 2),
                    'cible' => $c->ser_norme_ref_cible,
                    'statut' => $statutInfo['statut'],
                    'raison_insuffisance' => $statutInfo['raison'] ?? null,
                    'derniere_date' => $derniere->date,
                ];
            }

            $this->diffuserSiChangement($statutSer, $reverbDisponible);

            return $statutSer;
        }));
    }

    // Ne diffuse que si le statut ('statut', pas le détail IPE/SER autour) a réellement changé
    // depuis le dernier appel connu pour ce camion — jamais à chaque recalcul identique.
    private function diffuserSiChangement(array $statutSer, bool $reverbDisponible): void
    {
        $cle = self::CACHE_PREFIX_DERNIER_STATUT . $statutSer['id'];
        $ancienStatut = Cache::get($cle);

        if ($ancienStatut === $statutSer['statut']) {
            return;
        }
        Cache::forever($cle, $statutSer['statut']);

        if (!$reverbDisponible) {
            return;
        }

        try {
            StatutSerMisAJour::dispatch($statutSer);
        } catch (\Throwable $e) {
            Log::warning('ser-dynamique : diffusion WebSocket échouée (non bloquant)', [
                'camion_id' => $statutSer['id'],
                'erreur' => $e->getMessage(),
            ]);
        }
    }
}
