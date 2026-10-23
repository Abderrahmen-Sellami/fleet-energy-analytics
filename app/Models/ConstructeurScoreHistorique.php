<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConstructeurScoreHistorique extends Model
{
    protected $table = 'constructeur_scores_historique';

    protected $guarded = [];

    protected $casts = [
        'semaine_debut' => 'date',
        'semaine_fin' => 'date',
        'score_total' => 'float',
        'anticipation' => 'float',
        'braking' => 'float',
        'coasting' => 'float',
        'engine_and_gear_utilization' => 'float',
        'within_economy' => 'float',
        'gearbox_in_power_mode' => 'float',
        'overrev' => 'float',
        'abusive_use' => 'float',
        'standstill' => 'float',
        'idling' => 'float',
        'avg_speed_driving' => 'float',
        'total_distance' => 'float',
        'avg_fuel_consumption' => 'float',
        'total_time' => 'float',
    ];

    public function camion()
    {
        return $this->belongsTo(Camion::class);
    }
}
