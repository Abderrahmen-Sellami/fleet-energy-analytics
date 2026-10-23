<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeteoHistorique extends Model
{
    protected $table = 'meteo_historique';
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'temperature' => 'float',
        'humidite' => 'float',
        'vent_kmh' => 'float',
    ];
}
