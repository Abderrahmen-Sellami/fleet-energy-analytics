<?php

namespace App\Console\Commands;

use App\Models\Camion;
use App\Models\RegistreMesure;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ImportRegistreHistorique extends Command
{
    protected $signature = 'registre:import {fichier=storage/app/registre_historique_12camions.csv}';
    protected $description = 'Importe l\'historique registre manuel depuis le CSV extrait du registre mensuel';

    public function handle()
    {
        $chemin = base_path($this->argument('fichier'));
        if (!file_exists($chemin)) {
            $this->error("Fichier introuvable : {$chemin}");
            return Command::FAILURE;
        }

        $handle = fopen($chemin, 'r');
        $entetes = fgetcsv($handle, 0, ';');
        $compteur = 0;

        while (($ligne = fgetcsv($handle, 0, ';')) !== false) {
            $data = array_combine($entetes, $ligne);
            $camion = Camion::where('immatriculation', $data['matricule'])->first();
            if (!$camion) {
                $this->warn("Camion introuvable : {$data['matricule']}");
                continue;
            }

            $date = Carbon::createFromFormat('d/m/Y', $data['date'])->format('Y-m-d');

            RegistreMesure::updateOrCreate(
                ['camion_id' => $camion->id, 'date' => $date],
                [
                    'usage' => $data['usage'],
                    'ser' => $data['ser'] ?: null,
                    'ser_1x' => $data['ser_1x'] ?: null,
                    'cible' => $data['cible'] ?: null,
                    'km' => $data['km'] ?: null,
                    'heures' => $data['heures'] ?: null,
                    'litres' => $data['litres'] ?: null,
                    'pe_lh' => $data['pe_lh'] ?: null,
                    'pe_l100' => $data['pe_l100'] ?: null,
                    'site' => $data['site'],
                ]
            );
            $compteur++;
        }

        fclose($handle);
        $this->info("{$compteur} mesures registre manuel importées.");
        return Command::SUCCESS;
    }
}
