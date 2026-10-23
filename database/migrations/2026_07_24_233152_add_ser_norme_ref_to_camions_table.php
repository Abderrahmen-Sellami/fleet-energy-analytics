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
            if (!Schema::hasColumn('camions', 'ser_norme_ref')) $table->float('ser_norme_ref')->nullable();
            if (!Schema::hasColumn('camions', 'ser_norme_ref_incertitude')) $table->float('ser_norme_ref_incertitude')->nullable();
            if (!Schema::hasColumn('camions', 'ser_norme_ref_cible')) $table->float('ser_norme_ref_cible')->nullable();
            if (!Schema::hasColumn('camions', 'usage_norme_ref')) $table->string('usage_norme_ref')->nullable();
            if (!Schema::hasColumn('camions', 'source_a_actif_depuis')) $table->date('source_a_actif_depuis')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
