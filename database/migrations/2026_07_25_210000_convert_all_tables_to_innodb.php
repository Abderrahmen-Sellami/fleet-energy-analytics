<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Toutes les tables de la base sont actuellement en MyISAM, qui ignore silencieusement
// BEGIN/COMMIT/ROLLBACK (DB::transaction() ne protège donc rien) et n'applique aucune
// contrainte de clé étrangère (les clauses ->constrained() des migrations sont parsées
// mais jamais appliquées). Cette migration bascule chaque table en InnoDB, qui supporte
// réellement les transactions.
// Idempotent : ne touche que les tables qui ne sont pas déjà en InnoDB.
return new class extends Migration
{
    private const TABLES = ['camions', 'mesures', 'registre_mesures', 'alertes', 'migrations', 'sessions'];

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
