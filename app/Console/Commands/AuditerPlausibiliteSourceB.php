<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\VehiculesSourceB;
use App\Models\ConsommationSourceB;
use Illuminate\Console\Command;

/**
 * Rapporte, pour chaque véhicule Télématique B mappé, le statut de plausibilité automatique de sa
 * consommation calculée (cf. ConsommationSourceB::statutPlausibilite()) — construit pour
 * l'élargissement.
 */
class AuditerPlausibiliteSourceB extends Command
{
    protected $signature = 'source_b:auditer-plausibilite {--device= : ne traiter qu un seul idDevice}';

    protected $description = 'Rapporte le statut de plausibilite automatique (bornes L/100km + coherence) de chaque vehicule Télématique B mappe';

    public function handle(): int
    {
        $deviceFiltre = $this->option('device');
        $devices = VehiculesSourceB::DEVICE_PAR_PLAQUE;

        if ($deviceFiltre) {
            $devices = array_filter($devices, fn ($id) => (int) $id === (int) $deviceFiltre);
        }

        $rows = [];

        foreach ($devices as $plaque => $deviceId) {
            $sansCamion = in_array($plaque, VehiculesSourceB::PLAQUES_SANS_CAMION, true);
            $resume = ConsommationSourceB::resumePourDevice($deviceId);

            if ($resume === null) {
                $rows[] = [$plaque, $deviceId, $sansCamion ? 'oui' : 'non', '-', '-', '-', '-', 'AUCUN_INTERVALLE'];

                continue;
            }

            $statut = ConsommationSourceB::statutPlausibilite($resume);

            $rows[] = [
                $plaque,
                $deviceId,
                $sansCamion ? 'oui' : 'non',
                $resume['consommation_l100km'],
                $resume['mediane_l100km'],
                $resume['nombre_intervalles'],
                $resume['distance_totale_km'],
                $statut,
            ];
        }

        $this->table(
            ['Plaque', 'Device', 'Sans camion', 'Pondere L/100km', 'Mediane', 'N intervalles', 'Distance km', 'Statut'],
            $rows
        );

        $plausibles = collect($rows)->where(7, 'PLAUSIBLE')->count();
        $this->info(count($rows)." véhicules audités, {$plausibles} PLAUSIBLE.");

        return self::SUCCESS;
    }
}
