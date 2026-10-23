<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// alertes.type et alertes.severite étaient des ENUM ne couvrant pas les valeurs
// utilisées par AlerteService ('eco_score_faible', 'conso_elevee', 'moyenne', 'critique'...).
// Sous sql_mode sans STRICT_TRANS_TABLES, MySQL tronquait silencieusement toute valeur hors
// enum en chaîne vide, ce qui corrompait type/severite sur chaque ligne insérée et empêchait
// firstOrCreate() de jamais retrouver une alerte existante (doublons à chaque appel).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE alertes MODIFY type VARCHAR(50) NOT NULL");
        DB::statement("ALTER TABLE alertes MODIFY severite VARCHAR(20) NOT NULL");
    }

    public function down(): void {}
};
