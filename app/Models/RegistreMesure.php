<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistreMesure extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'ser' => 'float',
        'ser_1x' => 'float',
        'cible' => 'float',
        'km' => 'float',
        'heures' => 'float',
        'litres' => 'float',
        'pe_lh' => 'float',
        'pe_l100' => 'float',
        // Cf. migration dédiée : importées depuis "Copy of Récap data studio" (colonnes O/U des
        // fichiers norme interne mensuels individuels), jamais depuis l'onglet DATA du registre.
        'pct_ser_usage' => 'float',
        'pe_lh_precis' => 'float',
    ];

    public function camion()
    {
        return $this->belongsTo(Camion::class);
    }
}
