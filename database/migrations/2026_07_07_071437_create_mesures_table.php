<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sans effet sur la vraie base (cette migration y est déjà marquée "Ran", donc jamais
     * réexécutée)
     */
    public function up(): void
    {
        Schema::create('mesures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('camion_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('consommation_l100', 5, 2)->nullable();
            $table->integer('km_parcourus')->nullable();
            $table->decimal('taux_ralenti', 5, 2)->nullable();
            $table->decimal('ecoscore', 4, 2)->nullable();
            $table->integer('heures_moteur')->nullable();
            $table->timestamps();

            // Sous-scores Télématique A (groupIndicators), ajoutés en réalité par
            // add_sousscores_to_mesures_table — repris ici directement pour que le schéma
            // final d'une base fraîche soit correct dès la création de la table.
            $table->double('anticipation')->nullable();
            $table->double('utilisation_abusive')->nullable();
            $table->double('utilisation_optimale')->nullable();
            $table->double('arret')->nullable();
            $table->double('vitesse_moyenne')->nullable();
            $table->double('nb_arrets')->nullable();
            $table->double('conso_horaire')->nullable();

            // Repris de add_mode_puissance_engine_overrev_to_mesures_table, même raison.
            $table->double('usage_mode_puissance')->nullable();
            $table->double('engine_overrev')->nullable();

            // Absente de la vraie base actuelle — rétablie ici pour toute base fraîche,
            // conformément à l'intention d'origine (evite les doublons sur
            // updateOrCreate(['camion_id', 'date'], ...))
            $table->unique(['camion_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mesures');
    }
};
