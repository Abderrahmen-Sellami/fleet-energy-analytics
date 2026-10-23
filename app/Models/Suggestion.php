<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Suggestion extends Model
{
    protected $guarded = [];

    protected $casts = [
        'valeurs' => 'array',
        'resolue' => 'boolean',
        'date_resolution' => 'datetime',
    ];

    public function camion()
    {
        return $this->belongsTo(Camion::class);
    }
}
