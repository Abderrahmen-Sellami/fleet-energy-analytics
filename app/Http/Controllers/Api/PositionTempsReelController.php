<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

// Position GPS temps réel de la source A : aucune API n'est connectée dans cette application de
// démonstration. Réponse explicite « indisponible » (jamais une position inventée) ; le Suivi GPS
// affiche le message et continue de montrer les trajets archivés et les positions de la source B.
class PositionTempsReelController extends Controller
{
    public function index()
    {
        return response()->json([
            'message' => 'aucune source temps réel connectée dans cette application de démonstration.',
        ], 503);
    }
}
