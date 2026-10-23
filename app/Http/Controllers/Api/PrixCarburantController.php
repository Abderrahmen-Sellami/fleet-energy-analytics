<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrixCarburantHistorique;

class PrixCarburantController extends Controller
{
    public function index()
    {
        return response()->json(
            PrixCarburantHistorique::orderBy('date_debut')->get()
        );
    }
}
