<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mesures', function (Blueprint $table) {
            foreach (['anticipation', 'utilisation_abusive', 'utilisation_optimale', 'arret', 'vitesse_moyenne', 'nb_arrets', 'conso_horaire'] as $col) {
                if (!Schema::hasColumn('mesures', $col)) {
                    $table->float($col)->nullable();
                }
            }
        });
    }

    public function down(): void {}
};