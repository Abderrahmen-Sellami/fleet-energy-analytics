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
    Schema::create('camions', function (Blueprint $table) {
        $table->id();
        $table->string('vpi')->unique();
        $table->string('vin')->nullable();
        $table->string('immatriculation');
        $table->string('marque')->default('Marque A');
        $table->string('modele')->nullable();
        $table->string('euro_class')->nullable();
        $table->decimal('ser_reference', 5, 2)->nullable();
        $table->boolean('actif')->default(true);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('camions');
    }
};
