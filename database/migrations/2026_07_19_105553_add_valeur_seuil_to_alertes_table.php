<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alertes', function (Blueprint $table) {
            if (!Schema::hasColumn('alertes', 'valeur')) {
                $table->float('valeur')->nullable();
            }
            if (!Schema::hasColumn('alertes', 'seuil')) {
                $table->float('seuil')->nullable();
            }
            if (!Schema::hasColumn('alertes', 'date_resolution')) {
                $table->timestamp('date_resolution')->nullable();
            }
        });
    }

    public function down(): void {}
};