<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ser_norme_ref_historique', function (Blueprint $table) {
            $table->id();
            $table->foreignId('camion_id')->constrained()->cascadeOnDelete();
            $table->date('date_debut');
            $table->date('date_fin')->nullable(); // null = période encore en vigueur
            $table->decimal('ser', 6, 2); // SER norme interne, en L/h
            $table->decimal('incertitude', 6, 4)->nullable(); // ratio, ex: 0.25 pour SER×1,25
            $table->decimal('cible', 6, 2)->nullable();
            $table->string('usage')->nullable();
            $table->string('source')->nullable(); // ex: 'sheets:sync-registre', 'Correction manuelle...'
            $table->timestamps();

            $table->unique(['camion_id', 'date_debut']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ser_norme_ref_historique');
    }
};
