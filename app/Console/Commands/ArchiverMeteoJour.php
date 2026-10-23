<?php

namespace App\Console\Commands;

use App\Models\MeteoHistorique;
use App\Services\WeatherService;
use Illuminate\Console\Command;

class ArchiverMeteoJour extends Command
{
    protected $signature = 'meteo:archiver-jour';
    protected $description = 'Archive la météo du jour (par site) dans meteo_historique, pour constituer l\'historique nécessaire à une future analyse de corrélation';

    public function handle(WeatherService $service)
    {
        $aujourdhui = now()->toDateString();

        // Sans ce garde, chaque passage écraserait la ligne du jour avec une lecture différente
        // (la valeur "du jour" dépendrait du hasard du dernier créneau éveillé) et rappellerait
        // l'API météo pour rien.
        $dejaArchives = MeteoHistorique::where('date', $aujourdhui)->pluck('site')->all();
        $restants = array_values(array_diff(WeatherService::sites(), $dejaArchives));

        if ($restants === []) {
            $this->info("Rien à faire : tous les sites sont déjà archivés pour le {$aujourdhui}.");

            return Command::SUCCESS;
        }

        $meteoParSite = $service->getMeteoParSite();
        $compteur = 0;

        foreach ($meteoParSite as $site => $donnees) {
            if (!in_array($site, $restants, true)) {
                continue;
            }

            if (!$donnees || ($donnees['erreur'] ?? false)) {
                $this->warn("Site {$site} : donnée météo indisponible aujourd'hui (erreur " . ($donnees['status'] ?? '?') . "), ligne non archivée.");
                continue;
            }

            MeteoHistorique::updateOrCreate(
                ['site' => $site, 'date' => $aujourdhui],
                [
                    'temperature' => $donnees['temperature'] ?? null,
                    'humidite' => $donnees['humidite'] ?? null,
                    'vent_kmh' => $donnees['vent_kmh'] ?? null,
                    'description' => $donnees['description'] ?? null,
                ]
            );
            $compteur++;
        }

        $this->info("{$compteur} site(s) archivé(s) pour le {$aujourdhui}.");

        return Command::SUCCESS;
    }
}
