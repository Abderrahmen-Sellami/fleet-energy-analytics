<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * mesures.ecoscore était typée INT en base (contrairement à ses colonnes sœurs anticipation/
     * utilisation_abusive/utilisation_optimale/arret/usage_mode_puissance/engine_overrev, toutes en
     * DOUBLE) — chaque import arrondissait silencieusement la vraie valeur décimale de l'API
     * (fuelEfficiencyIndicators.total.value / 10) à l'entier le plus proche. DECIMAL(4,2) choisi
     * par cohérence avec consommation_l100 (déjà en decimal(5,2)) : un score borné 0-10 n'a pas
     * besoin de précision flottante binaire.
     */
    public function up(): void
    {
        $colonne = DB::selectOne("SHOW COLUMNS FROM mesures WHERE Field = 'ecoscore'");
        if ($colonne !== null && str_starts_with($colonne->Type, 'int')) {
            DB::statement('ALTER TABLE mesures MODIFY ecoscore DECIMAL(4,2) NULL');
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE mesures MODIFY ecoscore INT NULL');
    }
};
