<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\ComparateurRegistreController;
use App\Models\Camion;
use Illuminate\Console\Command;

class RapportAnomaliesRegistre extends Command
{
    protected $signature = 'rapport:anomalies-registre';

    protected $description = "Exporte en CSV les mois où l'écart registre manuel/Télématique A est DIVERGENT ou A_VERIFIER (hors mois cohérents), triés par écart absolu décroissant";

    public function handle(ComparateurRegistreController $comparateur): int
    {
        $lignes = [];

        foreach (Camion::orderBy('groupe')->orderBy('immatriculation')->get() as $camion) {
            $data = $comparateur->show($camion->id)->getData(true);

            foreach ($data['comparaison'] as $mois) {
                if (!in_array($mois['fiabilite'], ['DIVERGENT', 'A_VERIFIER'], true)) {
                    continue;
                }

                $lignes[] = [
                    'camion' => $data['camion'],
                    'groupe' => $data['groupe'],
                    'mois' => $mois['mois'],
                    'source_a_l100' => $mois['source_a_l100'],
                    'registre_l100' => $mois['registre_l100'],
                    'ecart_pct' => $mois['ecart_pct'],
                    'fiabilite' => $mois['fiabilite'],
                    'remarque' => $mois['remarque'],
                ];
            }
        }

        usort($lignes, fn ($a, $b) => abs($b['ecart_pct']) <=> abs($a['ecart_pct']));

        $chemin = storage_path('app/export_anomalies_registre.csv');
        $handle = fopen($chemin, 'w');
        fputcsv($handle, ['camion', 'groupe', 'mois', 'source_a_l100', 'registre_l100', 'ecart_pct', 'fiabilite', 'remarque'], ';');
        foreach ($lignes as $ligne) {
            fputcsv($handle, $ligne, ';');
        }
        fclose($handle);

        $this->info(count($lignes) . " ligne(s) d'anomalie exportée(s) vers {$chemin}.");

        return Command::SUCCESS;
    }
}
