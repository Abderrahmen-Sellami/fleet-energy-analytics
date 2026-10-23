<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Même correctif que 2026_07_26_090000_add_foreign_keys_camion_id.php.
return new class extends Migration
{
    private const CONTRAINTES = [
        'suggestions' => 'fk_suggestions_camion',
        'analyses_correctives' => 'fk_analyses_correctives_camion',
        'ser_norme_ref_historique' => 'fk_ser_norme_ref_historique_camion',
        'constructeur_scores_historique' => 'fk_constructeur_scores_historique_camion',
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
