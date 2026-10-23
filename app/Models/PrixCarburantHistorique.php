<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrixCarburantHistorique extends Model
{
    protected $table = 'prix_carburant_historique';
    protected $guarded = [];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'prix_ht' => 'float',
    ];
}
