<?php

namespace Database\Factories;

use App\Models\Camion;
use Illuminate\Database\Eloquent\Factories\Factory;

class CamionFactory extends Factory
{
    protected $model = Camion::class;

    public function definition(): array
    {
        return [
            'vpi' => $this->faker->unique()->bothify('????????????????????????????????'),
            'vin' => $this->faker->unique()->bothify('VF6#############'),
            'immatriculation' => $this->faker->unique()->numerify('####TU###'),
            'marque' => 'Marque A',
            'modele' => 'MODELE-A',
            'euro_class' => 'EURO_3',
            'groupe' => $this->faker->randomElement(['SITE A', 'SITE B']),
            'ser_reference' => null,
            'actif' => true,
            'objectif_conso' => 35,
            'conso_initiale_ser' => null,
            'ser_norme_ref' => 45.0,
            'ser_norme_ref_incertitude' => 0.25,
            'ser_norme_ref_cible' => 44.1,
            'usage_norme_ref' => 'Transport — catégorie 2',
            'source_a_actif_depuis' => now()->subYear()->toDateString(),
            // Flotte core par défaut — un camion de test représente un véhicule Télématique A
            // audité sauf état explicite contraire ('SOURCE_B'), cohérent avec le repli backend de
            // resumeFlotte() (absence de source_principale = exclu du filtre par défaut)
            'source_principale' => 'SOURCE_A',
        ];
    }
}
