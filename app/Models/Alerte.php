<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alerte extends Model
{
    protected $guarded = [];
    protected $casts = ['resolue' => 'boolean', 'date_resolution' => 'datetime'];

    public function camion()
    {
        return $this->belongsTo(Camion::class);
    }
}