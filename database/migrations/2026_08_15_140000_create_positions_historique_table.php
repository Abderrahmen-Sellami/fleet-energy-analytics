<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historique de trajet GPS (positions archivées). Volontairement PAS de contrainte unique : plusieurs
     * points par camion et par jour sont attendus (un point par exécution planifiée), à la
     * différence des tables d'archivage hebdomadaire (constructeur_scores_historique) qui, elles,
     * n'attendent qu'une valeur par période.
     */
    public function up(): void
    {
        Schema::create('positions_historique', function (Blueprint $table) {
            $table->id();
            $table->foreignId('camion_id')->constrained()->cascadeOnDelete();
            $table->float('latitude');
            $table->float('longitude');
            $table->float('vitesse')->nullable();
            $table->float('cap')->nullable();
            $table->float('altitude')->nullable();
            $table->timestamp('horodatage_position');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['camion_id', 'horodatage_position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions_historique');
    }
};
