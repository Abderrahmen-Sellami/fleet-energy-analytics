<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Camion;
use App\Models\SerNormeRefHistorique;

class SerNormeRefHistoriqueController extends Controller
{
    public function index(int $camionId)
    {
        $camion = Camion::findOrFail($camionId);

        $historique = SerNormeRefHistorique::where('camion_id', $camionId)
            ->orderBy('date_debut')
            ->get(['date_debut', 'date_fin', 'ser', 'incertitude', 'cible', 'usage', 'source']);

        return response()->json([
            'camion_id' => $camion->id,
            'immatriculation' => $camion->immatriculation,
            'historique' => $historique,
        ]);
    }
}
