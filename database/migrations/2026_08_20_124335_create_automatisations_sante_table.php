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
        // Une ligne par tâche planifiée critique, upsertée à chaque exécution réussie.
        Schema::create('automatisations_sante', function (Blueprint $table) {
            $table->id();
            $table->string('tache')->unique();
            $table->timestamp('derniere_execution_reussie')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automatisations_sante');
    }
};
