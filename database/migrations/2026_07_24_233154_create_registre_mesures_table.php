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
        Schema::create('registre_mesures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('camion_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('usage')->nullable();
            $table->float('ser')->nullable();
            $table->float('ser_1x')->nullable();
            $table->float('cible')->nullable();
            $table->float('km')->nullable();
            $table->float('heures')->nullable();
            $table->float('litres')->nullable();
            $table->float('pe_lh')->nullable();
            $table->float('pe_l100')->nullable();
            $table->string('site')->nullable();
            $table->unique(['camion_id', 'date']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registre_mesures');
    }
};
