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
        Schema::create('prix_carburant_historique', function (Blueprint $table) {
            $table->id();
            $table->date('date_debut')->unique(); // clé de l'updateOrCreate dans la commande carburant:prix
            $table->date('date_fin')->nullable(); // null = période encore en vigueur
            $table->decimal('prix_ht', 6, 3); // TND/L hors taxes
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prix_carburant_historique');
    }
};
