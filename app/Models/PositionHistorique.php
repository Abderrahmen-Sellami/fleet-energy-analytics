<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PositionHistorique extends Model
{
    const UPDATED_AT = null;

    protected $table = 'positions_historique';

    protected $guarded = [];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'vitesse' => 'float',
        'cap' => 'float',
        'altitude' => 'float',
        'horodatage_position' => 'datetime',
    ];

    public function camion()
    {
        return $this->belongsTo(Camion::class);
    }
}
