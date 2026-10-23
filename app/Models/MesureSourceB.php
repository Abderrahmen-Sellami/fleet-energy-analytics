<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MesureSourceB extends Model
{
    protected $table = 'mesures_source_b';

    protected $guarded = [];

    protected $casts = [
        'horodatage_record' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'fuel_rate' => 'float',
        'odo' => 'float',
        'accum_odo' => 'float',
        'distance_km' => 'float',
        'duree_secondes' => 'integer',
        'ignition' => 'boolean',
        'donnees_brutes' => 'array',
    ];

    public function camion()
    {
        return $this->belongsTo(Camion::class);
    }
}
