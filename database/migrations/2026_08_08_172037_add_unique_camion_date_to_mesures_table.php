<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Rétablit une contrainte unique (camion_id, date) sur mesures, absente de la vraie base.
return new class extends Migration
{
    private const NOM_INDEX = 'mesures_camion_id_date_unique';

    public function up(): void
    {
        if ($this->indexExiste()) {
            return;
        }

        DB::statement('ALTER TABLE `mesures` ADD UNIQUE `'.self::NOM_INDEX.'` (`camion_id`, `date`)');
    }

    public function down(): void
    {
        if ($this->indexExiste()) {
            DB::statement('ALTER TABLE `mesures` DROP INDEX `'.self::NOM_INDEX.'`');
        }
    }

    private function indexExiste(): bool
    {
        $r = DB::select(
            "SELECT COUNT(*) as n FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mesures' AND INDEX_NAME = ?",
            [self::NOM_INDEX]
        );

        return ($r[0]->n ?? 0) > 0;
    }
};
