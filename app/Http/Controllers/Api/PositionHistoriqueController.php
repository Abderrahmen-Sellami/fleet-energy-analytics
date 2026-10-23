<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Camion;
use App\Models\PositionHistorique;
use App\Support\Concerns\ValideDatesCalendaires;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PositionHistoriqueController extends Controller
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

        // Par défaut (aucune borne fournie) : dernières 24h (un point toutes les 15 min environ).
        $borneFin = $jusquA !== null ? Carbon::createFromFormat('Y-m-d', $jusquA)->endOfDay() : Carbon::now();
        $borneDebut = $depuis !== null ? Carbon::createFromFormat('Y-m-d', $depuis)->startOfDay() : $borneFin->copy()->subDay();

        $points = PositionHistorique::where('camion_id', $camionId)
            ->whereBetween('horodatage_position', [$borneDebut, $borneFin])
            ->orderBy('horodatage_position')
            ->get(['latitude', 'longitude', 'vitesse', 'cap', 'altitude', 'horodatage_position']);

        return response()->json([
            'camion_id' => $camion->id,
            'immatriculation' => $camion->immatriculation,
            'depuis' => $borneDebut->toIso8601String(),
            'jusqu_a' => $borneFin->toIso8601String(),
            'points' => $points,
        ]);
    }
}
