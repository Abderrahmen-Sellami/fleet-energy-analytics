<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Rattrapage de 2026_07_25_210000_convert_all_tables_to_innodb.php : cette migration-là avait
// converti les tables existantes AU, mais sans config('database.connections.mysql.engine'), toute
// table créée après elle héritait silencieusement du moteur par défaut du serveur (MyISAM sur
// cette instance) — 6 tables concernées.
return new class extends Migration
{
    private const TABLES = [
        'suggestions',
        'analyses_correctives',
        'meteo_historique',
        'prix_carburant_historique',
        'constructeur_scores_historique',
        'ser_norme_ref_historique',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $status = DB::select("SHOW TABLE STATUS WHERE Name = ?", [$table]);
            if (($status[0]->Engine ?? null) === 'InnoDB') {
                continue;
            }
            DB::statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            DB::statement("ALTER TABLE `{$table}` ENGINE=MyISAM");
        }
    }
};
