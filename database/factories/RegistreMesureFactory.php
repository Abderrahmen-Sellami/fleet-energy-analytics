<?php

namespace Database\Factories;

use App\Models\Camion;
use App\Models\RegistreMesure;
use Illuminate\Database\Eloquent\Factories\Factory;

class RegistreMesureFactory extends Factory
{
    protected $model = RegistreMesure::class;

    public function definition(): array
    {
        return [
            'camion_id' => Camion::factory(),
            'date' => now()->startOfMonth()->toDateString(),
            'usage' => 'Transport — catégorie 2',
            'ser' => 45.0,
            'ser_1x' => 56.25,
            'cible' => 44.1,
            'km' => 5000,
            'heures' => 150,
            'litres' => 2000,
            'pe_lh' => null,
            'pe_l100' => null,
            'site' => 'SITE A',
        ];
    }
}
