<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Sonde légère (socket brut, timeout court) pour savoir si un serveur Reverb écoute en local,
 * avant de tenter un dispatch() d'event ShouldBroadcastNow — à appeler une fois par commande,
 * jamais par itération d'une boucle.
 */
class ReverbDisponibiliteService
{
    private const CACHE_KEY = 'reverb_disponible';
    private const CACHE_MINUTES = 5;
    private const TIMEOUT_SECONDES = 0.25;
    private const HOTE = '127.0.0.1';

    public function disponible(): bool
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(self::CACHE_MINUTES), function () {
            $port = config('reverb.servers.reverb.port');

            $connexion = @fsockopen(self::HOTE, $port, $errno, $errstr, self::TIMEOUT_SECONDES);
            if ($connexion === false) {
                return false;
            }
            fclose($connexion);

            return true;
        });
    }
}
