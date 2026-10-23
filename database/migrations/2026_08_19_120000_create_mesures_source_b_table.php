<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table préparée pour la future intégration Télématique B.
     */
    public function up(): void
    {
        Schema::create('mesures_source_b', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->foreignId('camion_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('horodatage_record');
            $table->float('latitude')->nullable();
            $table->float('longitude')->nullable();
            $table->integer('vitesse')->nullable();
            $table->integer('fuel')->nullable();
            $table->integer('rpm')->nullable();
            $table->float('fuel_rate')->nullable();
            $table->float('odo')->nullable();
            $table->float('accum_odo')->nullable();
            $table->boolean('ignition')->nullable();
            $table->integer('power')->nullable();
            $table->string('realtime_record_status', 30)->nullable();
            $table->json('donnees_brutes')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'horodatage_record']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mesures_source_b');
    }
};
