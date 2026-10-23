<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Camion;
use App\Models\MesureSourceB;
use App\Support\Concerns\ValideDatesCalendaires;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Historique de trajet Télématique B — symétrique à PositionHistoriqueController (Télématique A),
 * même contrat d'API (depuis/jusqu_a en AAAA-MM-JJ, repli 24h.
 */
class PositionHistoriqueSourceBController extends Controller
{
    use ValideDatesCalendaires;

    public function index(Request $request, int $camionId)
    {
        $camion = Camion::findOrFail($camionId);

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

        // Même repli par défaut que PositionHistoriqueController (24h) — cohérent avec la fréquence
        // d'archivage Télématique B, elle aussi de 15 min (~96 points/jour/camion attendus dans le meilleur cas).
        $borneFin = $jusquA !== null ? Carbon::createFromFormat('Y-m-d', $jusquA)->endOfDay() : Carbon::now();
        $borneDebut = $depuis !== null ? Carbon::createFromFormat('Y-m-d', $depuis)->startOfDay() : $borneFin->copy()->subDay();

        $points = MesureSourceB::where('camion_id', $camionId)
            ->where('source', 'realtime')
            ->whereBetween('horodatage_record', [$borneDebut, $borneFin])
            ->orderBy('horodatage_record')
            ->get(['latitude', 'longitude', 'vitesse', 'horodatage_record'])
            ->map(fn ($p) => [
                'latitude' => $p->latitude,
                'longitude' => $p->longitude,
                'vitesse' => $p->vitesse,
                'horodatage_position' => $p->horodatage_record->toIso8601String(),
            ]);

        return response()->json([
            'camion_id' => $camion->id,
            'immatriculation' => $camion->immatriculation,
            'depuis' => $borneDebut->toIso8601String(),
            'jusqu_a' => $borneFin->toIso8601String(),
            'points' => $points,
        ]);
    }
}
