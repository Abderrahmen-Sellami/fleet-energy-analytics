<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Analyses correctives déjà réalisées (norme interne ou autre) sur un camion/mois donné — saisies
     * manuellement pour l'instant (source par défaut 'NORME_REF_Analyse_manuelle'). Affichées dans
     * l'onglet Alertes quand une alerte correspond au même camion+mois, pour éviter de ré-investiguer
     * un cas déjà traité.
     */
    public function up(): void
    {
        Schema::create('analyses_correctives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('camion_id')->constrained()->cascadeOnDelete();
            $table->date('mois');
            $table->text('cause')->nullable();
            $table->text('action')->nullable();
            $table->string('responsable')->nullable();
            $table->date('date_realisation')->nullable();
            $table->string('statut_suivi')->nullable();
            $table->string('methode_mesure')->nullable();
            $table->string('suivi_efficacite')->nullable();
            $table->string('source')->default('NORME_REF_Analyse_manuelle');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analyses_correctives');
    }
};
