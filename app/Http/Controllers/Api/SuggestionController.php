<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Suggestion;

class SuggestionController extends Controller
{
    // Même filtre que l'ancien retour de detecterEtEnregistrer() : uniquement les suggestions
    // actives (resolue=false) — une suggestion résolue n'a jamais été incluse dans la réponse de
    // cet endpoint.
    public function index()
    {
        return response()->json(
            Suggestion::where('resolue', false)
                ->with('camion')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'camion' => $s->camion->immatriculation,
                    'regle_declenchee' => $s->regle_declenchee,
                    'message' => $s->message,
                    'gravite' => $s->gravite,
                    'valeurs' => $s->valeurs,
                    'date_detection' => $s->created_at,
                    'resolue' => $s->resolue,
                    'date_resolution' => $s->date_resolution,
                ])
        );
    }
}
