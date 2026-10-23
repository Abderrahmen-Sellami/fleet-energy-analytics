<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Deux colonnes supplémentaires importées depuis l'onglet "Copy of Récap data studio" des fichiers
// norme interne mensuels individuels (Site A/Site B)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registre_mesures', function (Blueprint $table) {
            if (!Schema::hasColumn('registre_mesures', 'pct_ser_usage')) {
                // Fraction (0,6386 pour "63,86%"), même convention que camions.ser_norme_ref_incertitude
                // (0,25 pour "25%") — jamais le nombre de pourcentage brut (63,86), pour rester
                // cohérent avec le seul autre champ "pourcentage" déjà en base dans ce projet.
                $table->float('pct_ser_usage')->nullable()->after('pe_l100');
            }
            if (!Schema::hasColumn('registre_mesures', 'pe_lh_precis')) {
                // Valeur non arrondie de P,E (l/h), calculée par le Sheet lui-même (colonne "All PE")
                // — indépendante de pe_lh (déjà arrondi à 2 décimales par valeurDecimale() lors de
                // l'import du registre DATA) : une lecture directe et plus précise de la même
                // grandeur, pas un recalcul de notre côté.
                $table->float('pe_lh_precis')->nullable()->after('pct_ser_usage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('registre_mesures', function (Blueprint $table) {
            if (Schema::hasColumn('registre_mesures', 'pe_lh_precis')) {
                $table->dropColumn('pe_lh_precis');
            }
            if (Schema::hasColumn('registre_mesures', 'pct_ser_usage')) {
                $table->dropColumn('pct_ser_usage');
            }
        });
    }
};
