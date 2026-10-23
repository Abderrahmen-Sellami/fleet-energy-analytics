<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SanteAutomatisationsService;

class SanteAutomatisationsController extends Controller
{
    public function index(SanteAutomatisationsService $service)
    {
        $taches = $service->verifierToutes();

        return response()->json([
            'taches' => $taches,
            'au_moins_une_en_retard' => collect($taches)->contains('en_retard', true),
        ]);
    }
}
