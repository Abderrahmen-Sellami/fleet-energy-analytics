<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyseCorrective extends Model
{
    protected $table = 'analyses_correctives';

    protected $guarded = [];

    protected $casts = [
        'mois' => 'date',
        'date_realisation' => 'date',
    ];

    public function camion()
    {
        return $this->belongsTo(Camion::class);
    }
}
