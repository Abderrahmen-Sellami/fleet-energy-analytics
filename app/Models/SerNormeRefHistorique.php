<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SerNormeRefHistorique extends Model
{
    protected $table = 'ser_norme_ref_historique';
    protected $guarded = [];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'ser' => 'float',
        'incertitude' => 'float',
        'cible' => 'float',
        'confirme_le' => 'datetime',
    ];

    public function camion()
    {
        return $this->belongsTo(Camion::class);
    }
}
