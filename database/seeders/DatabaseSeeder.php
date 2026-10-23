<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * « php artisan migrate --seed » : base de démonstration complète, entièrement fictive,
 * sans aucune source externe ni clé d'API (cf. DemoSeeder).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
