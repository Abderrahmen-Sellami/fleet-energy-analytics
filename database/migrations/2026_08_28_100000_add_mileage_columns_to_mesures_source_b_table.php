<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colonnes manquantes pour importer POST /mileage/{deviceId} (agrégats journaliers distance/durée)
 * dans mesures_source_b — la table avait été dimensionnée pour GET /realTimeRecords (position
 * instantanée, vitesse, régime moteur), structurellement différent d'un agrégat par jour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mesures_source_b', function (Blueprint $table) {
            if (!Schema::hasColumn('mesures_source_b', 'distance_km')) {
                $table->float('distance_km')->nullable()->after('fuel_rate');
            }
            if (!Schema::hasColumn('mesures_source_b', 'duree_secondes')) {
                $table->integer('duree_secondes')->nullable()->after('distance_km');
            }
            if (!Schema::hasColumn('mesures_source_b', 'source')) {
                $table->string('source', 20)->nullable()->after('realtime_record_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mesures_source_b', function (Blueprint $table) {
            $table->dropColumn(['distance_km', 'duree_secondes', 'source']);
        });
    }
};
