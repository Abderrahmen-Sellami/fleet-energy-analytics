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
        Schema::table('ser_norme_ref_historique', function (Blueprint $table) {
            if (!Schema::hasColumn('ser_norme_ref_historique', 'confirme_par')) {
                $table->string('confirme_par')->nullable()->after('source');
            }
            if (!Schema::hasColumn('ser_norme_ref_historique', 'confirme_le')) {
                $table->timestamp('confirme_le')->nullable()->after('confirme_par');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ser_norme_ref_historique', function (Blueprint $table) {
            $table->dropColumn(['confirme_par', 'confirme_le']);
        });
    }
};
