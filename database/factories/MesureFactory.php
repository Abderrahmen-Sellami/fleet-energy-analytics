<?php

namespace Database\Factories;

use App\Models\Camion;
use App\Models\Mesure;
use Illuminate\Database\Eloquent\Factories\Factory;

class MesureFactory extends Factory
{
    protected $model = Mesure::class;

    public function definition(): array
    {
        return [
            'camion_id' => Camion::factory(),
            'date' => now()->startOfMonth()->toDateString(),
            'consommation_l100' => 40.0,
            'km_parcourus' => 5000,
            'taux_ralenti' => null,
            'ecoscore' => 7.5,
            'heures_moteur' => 150,
            'anticipation' => 7.0,
            'utilisation_abusive' => 7.0,
            'utilisation_optimale' => 7.0,
            'arret' => 7.0,
            'vitesse_moyenne' => null,
            'nb_arrets' => null,
            'conso_horaire' => null,
            'usage_mode_puissance' => null,
            'engine_overrev' => null,
        ];
    }
}
