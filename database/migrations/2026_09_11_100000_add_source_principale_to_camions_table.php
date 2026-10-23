<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('camions', function (Blueprint $table) {
            if (!Schema::hasColumn('camions', 'source_principale')) {
                $table->string('source_principale')->nullable()->after('vpi');
            }
        });
    }

    public function down(): void
    {
        Schema::table('camions', function (Blueprint $table) {
            $table->dropColumn('source_principale');
        });
    }
};
