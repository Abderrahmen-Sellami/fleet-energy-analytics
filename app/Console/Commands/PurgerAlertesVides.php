<?php

namespace App\Console\Commands;

use App\Models\Alerte;
use Illuminate\Console\Command;

class PurgerAlertesVides extends Command
{
    protected $signature = 'alertes:purger-vides {--dry-run : Affiche le nombre de lignes concernées sans rien supprimer}';
    protected $description = 'Supprime les alertes résolues sans type (vides)';

    public function handle()
    {
        $requete = Alerte::where('type', '')->where('resolue', true);
        $total = $requete->count();

        if ($total === 0) {
            $this->info('Aucune alerte à type vide et résolue trouvée.');
            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("{$total} ligne(s) seraient supprimées (type='' AND resolue=true). Aucune suppression effectuée (--dry-run).");
            return Command::SUCCESS;
        }

        $supprimees = $requete->delete();
        $this->info("{$supprimees} ligne(s) supprimée(s).");
        return Command::SUCCESS;
    }
}
