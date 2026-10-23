<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Archive hebdomadaire de l'API scores du constructeur (API constructeur), EN COMPLÉMENT des
     * données Télématique A déjà en base (table mesures)
     */
    public function up(): void
    {
        Schema::create('constructeur_scores_historique', function (Blueprint $table) {
            $table->id();
            $table->foreignId('camion_id')->constrained()->cascadeOnDelete();
            // Bornes de la semaine calendaire (archive), pas nécessairement les bornes exactes de
            // la fenêtre de données réellement couverte par l'API (rapport glissant, cf. commande).
            $table->date('semaine_debut');
            $table->date('semaine_fin');
            $table->float('score_total')->nullable();
            $table->float('anticipation')->nullable();
            $table->float('braking')->nullable();
            $table->float('coasting')->nullable();
            $table->float('engine_and_gear_utilization')->nullable();
            $table->float('within_economy')->nullable();
            $table->float('gearbox_in_power_mode')->nullable();
            $table->float('overrev')->nullable();
            $table->float('abusive_use')->nullable();
            $table->float('standstill')->nullable();
            $table->float('idling')->nullable();
            $table->float('avg_speed_driving')->nullable();
            $table->float('total_distance')->nullable();
            $table->float('avg_fuel_consumption')->nullable();
            $table->float('total_time')->nullable();
            $table->timestamps();

            $table->unique(['camion_id', 'semaine_debut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('constructeur_scores_historique');
    }
};
