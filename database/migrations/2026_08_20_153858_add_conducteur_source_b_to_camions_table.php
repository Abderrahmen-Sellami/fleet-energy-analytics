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
        Schema::table('camions', function (Blueprint $table) {
            if (!Schema::hasColumn('camions', 'conducteur_source_b')) {
                // Nullable, jamais alimenté pour les 6 camions SITE B — info statique saisie une
                // fois manuellement depuis le portail Télématique B, pas connectée à un import
                // automatique.
                $table->string('conducteur_source_b')->nullable()->after('vpi');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('camions', function (Blueprint $table) {
            if (Schema::hasColumn('camions', 'conducteur_source_b')) {
                $table->dropColumn('conducteur_source_b');
            }
        });
    }
};
