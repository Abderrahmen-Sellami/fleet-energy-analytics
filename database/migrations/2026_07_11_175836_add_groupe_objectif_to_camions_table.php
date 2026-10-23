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
        $table->string('groupe')->nullable()->after('immatriculation');
        $table->float('objectif_conso')->nullable();
        $table->float('conso_initiale_ser')->nullable();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('camions', function (Blueprint $table) {
            //
        });
    }
};
