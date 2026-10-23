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
    Schema::create('alertes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('camion_id')->constrained()->onDelete('cascade');
        $table->enum('type', ['consommation', 'ralenti', 'maintenance', 'ecoscore']);
        $table->enum('severite', ['info', 'warning', 'danger']);
        $table->text('message');
        $table->boolean('resolue')->default(false);
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alertes');
    }
};
