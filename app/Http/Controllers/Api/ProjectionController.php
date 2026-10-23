<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Camion;
use App\Models\Mesure;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProjectionController extends Controller
{
    // Chemin A : projection par extrapolation de l'historique réel, sans facteur climat.
    private const METRIQUES = ['ecoscore', 'consommation_l100'];

    // Même convention que CourbesController : ecoscore et consommation_l100 stockent 0 (au lieu
    // de NULL) pour les mois sans données Télématique A, donc 0 ne doit pas être traité comme une
    // vraie mesure.
    private const ZERO_SIGNIFIE_ABSENCE = ['ecoscore', 'consommation_l100'];

    // Au-delà de ces bornes, la valeur est une anomalie de mesure, exclue de la régression pour ne pas fausser pente/R².
    private const BORNES_HAUTES = ['consommation_l100' => Mesure::CONSO_L100_MAX_PLAUSIBLE];

    private const MOIS_MINIMUM = 6;
    private const MOIS_PROJETES = 3;
    private const SEUIL_R2_FIABLE = 0.3;

    public function index(int $camion_id, string $metrique)
    {
        if (!in_array($metrique, self::METRIQUES, true)) {
            return response()->json([
                'message' => "Métrique inconnue : {$metrique}. Valeurs acceptées : " . implode(', ', self::METRIQUES),
            ], 422);
        }

        $camion = Camion::findOrFail($camion_id);

        $query = DB::table('mesures')
            ->select('date', "{$metrique} as valeur")
            ->where('camion_id', $camion->id)
            // Le mois en cours (incomplet) n'entre jamais dans une régression avec des mois clos.
            ->where('est_partielle', false)
            ->whereNotNull($metrique);

        if (in_array($metrique, self::ZERO_SIGNIFIE_ABSENCE, true)) {
            $query->where($metrique, '>', 0);
        }
        if (isset(self::BORNES_HAUTES[$metrique])) {
            $query->where($metrique, '<=', self::BORNES_HAUTES[$metrique]);
        }

        $historique = $query->orderBy('date')->get();

        if ($historique->count() < self::MOIS_MINIMUM) {
            return response()->json([
                'message' => "Historique insuffisant pour une projection fiable ({$historique->count()} mois exploitables, " . self::MOIS_MINIMUM . ' requis).',
            ], 422);
        }

        $points = $historique->values()->map(fn ($row, $i) => [
            'x' => $i,
            'y' => (float) $row->valeur,
            'mois' => Carbon::parse($row->date)->format('Y-m'),
        ]);

        $reg = $this->regressionLineaire($points->map(fn ($p) => ['x' => $p['x'], 'y' => $p['y']])->all());
        $fiable = $reg['r2'] >= self::SEUIL_R2_FIABLE;
        $n = $points->count();

        $reponse = [
            'camion_id' => $camion->id,
            'immatriculation' => $camion->immatriculation,
            'metrique' => $metrique,
            'n_mois' => $n,
            'pente' => round($reg['pente'], 4),
            'ordonnee_origine' => round($reg['ordonnee'], 4),
            'r2' => round($reg['r2'], 4),
            'fiable' => $fiable,
            'historique' => $points->map(fn ($p) => ['mois' => $p['mois'], 'valeur' => $p['y']])->values(),
            'projection' => [],
        ];

        if ($fiable) {
            $dernierMois = Carbon::parse($historique->last()->date);
            for ($i = 1; $i <= self::MOIS_PROJETES; $i++) {
                $x = $n - 1 + $i;
                $reponse['projection'][] = [
                    'mois' => $dernierMois->copy()->addMonths($i)->format('Y-m'),
                    'valeur' => round($reg['pente'] * $x + $reg['ordonnee'], 2),
                ];
            }
        } else {
            $reponse['message'] = 'Tendance trop irrégulière pour une projection fiable.';
        }

        return response()->json($reponse);
    }

    private function regressionLineaire(array $points): array
    {
        $n = count($points);
        $meanX = array_sum(array_column($points, 'x')) / $n;
        $meanY = array_sum(array_column($points, 'y')) / $n;

        $sxy = 0.0;
        $sxx = 0.0;
        foreach ($points as $p) {
            $sxy += ($p['x'] - $meanX) * ($p['y'] - $meanY);
            $sxx += ($p['x'] - $meanX) ** 2;
        }

        $pente = $sxx > 0 ? $sxy / $sxx : 0.0;
        $ordonnee = $meanY - $pente * $meanX;

        $ssRes = 0.0;
        $ssTot = 0.0;
        foreach ($points as $p) {
            $predit = $pente * $p['x'] + $ordonnee;
            $ssRes += ($p['y'] - $predit) ** 2;
            $ssTot += ($p['y'] - $meanY) ** 2;
        }

        $r2 = $ssTot > 0 ? 1 - ($ssRes / $ssTot) : 1.0;

        return ['pente' => $pente, 'ordonnee' => $ordonnee, 'r2' => $r2];
    }
}
