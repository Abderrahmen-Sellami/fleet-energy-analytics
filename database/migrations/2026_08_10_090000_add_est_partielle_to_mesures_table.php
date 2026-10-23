<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Distingue une mesure Télématique A définitive (mois calendaire clos) d'une mesure provisoire du mois
// en cours — jamais à mélanger dans une moyenne/statut avec des mois complets.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mesures', function (Blueprint $table) {
            if (!Schema::hasColumn('mesures', 'est_partielle')) {
                $table->boolean('est_partielle')->default(false)->after('date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mesures', function (Blueprint $table) {
            if (Schema::hasColumn('mesures', 'est_partielle')) {
                $table->dropColumn('est_partielle');
            }
        });
    }
};
