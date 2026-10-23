<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consommation L/100km calculée « plein à plein » : volume du plein suivant rapporté à la distance
 * parcourue entre deux pleins consécutifs, distance issue de `mesures_source_b`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consommations_source_b', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->foreignId('camion_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('plein_precedent_date');
            $table->timestamp('plein_suivant_date');
            $table->float('volume_l');
            $table->float('distance_km')->nullable();
            $table->float('consommation_l100km')->nullable();
            $table->boolean('est_partielle')->default(false);
            $table->timestamps();

            $table->unique(['device_id', 'plein_suivant_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consommations_source_b');
    }
};
