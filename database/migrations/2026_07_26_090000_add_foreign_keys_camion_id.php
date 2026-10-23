<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Ajoute les contraintes de clé étrangère camion_id -> camions.id qui n'avaient jamais été
// réellement appliquées (les tables étaient en MyISAM au moment de leur création, qui ignore
// silencieusement les clauses ->constrained()). Intégrité référentielle vérifiée avant migration :
// 0 ligne orpheline sur mesures, registre_mesures, alertes (camion_id sans camion correspondant).
return new class extends Migration
{
    private const CONTRAINTES = [
        'mesures' => 'fk_mesures_camion',
        'registre_mesures' => 'fk_registre_camion',
        'alertes' => 'fk_alertes_camion',
    ];

    public function up(): void
    {
        foreach (self::CONTRAINTES as $table => $nomContrainte) {
            if ($this->contrainteExiste($table, $nomContrainte)) {
                continue;
            }
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$nomContrainte}` FOREIGN KEY (camion_id) REFERENCES camions(id) ON DELETE CASCADE");
        }
    }

    public function down(): void
    {
        foreach (self::CONTRAINTES as $table => $nomContrainte) {
            if ($this->contrainteExiste($table, $nomContrainte)) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$nomContrainte}`");
            }
        }
    }

    private function contrainteExiste(string $table, string $nomContrainte): bool
    {
        $r = DB::select(
            "SELECT COUNT(*) as n FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$table, $nomContrainte]
        );
        return ($r[0]->n ?? 0) > 0;
    }
};
