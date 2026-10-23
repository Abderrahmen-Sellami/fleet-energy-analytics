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
        Schema::create('meteo_historique', function (Blueprint $table) {
            $table->id();
            $table->string('site', 20);
            $table->date('date');
            $table->float('temperature')->nullable();
            $table->float('humidite')->nullable();
            $table->float('vent_kmh')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
            $table->unique(['site', 'date']); // évite les doublons sur updateOrCreate
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meteo_historique');
    }
};
