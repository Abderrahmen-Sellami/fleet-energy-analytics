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
        Schema::create('suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('camion_id')->constrained()->cascadeOnDelete();
            // VARCHAR, pas ENUM — cf. migration 2026_07_25_190000_convert_alertes_type_severite_to_string
            // (un ENUM sur alertes.type/severite tronquait silencieusement toute valeur hors liste en
            // chaîne vide sous sql_mode non strict, cassant firstOrCreate() : doublons à chaque appel).
            $table->string('regle_declenchee', 50);
            $table->string('gravite', 20);
            $table->text('message');
            // Détail propre à chaque règle (mois, IPE, seuils...) — contrairement à alertes.valeur/seuil
            // qui sont un seul float chacun, les 7 règles de SuggestionService portent des structures
            // différentes (ex: AMELIORATION_CONSTATEE imbrique un tableau de statuts précédents).
            $table->json('valeurs')->nullable();
            $table->boolean('resolue')->default(false);
            $table->timestamp('date_resolution')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suggestions');
    }
};
