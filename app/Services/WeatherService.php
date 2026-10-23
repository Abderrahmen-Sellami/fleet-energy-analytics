<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class WeatherService
{
    private const SITES = [
        // Coordonnées fictives de démonstration (aucun site réel).
        'SITE A' => ['lat' => 45.7000, 'lon' => 4.9000],
        'SITE B' => ['lat' => 44.9000, 'lon' => 4.8800],
    ];

    /** Codes des sites suivis (SITE A, SITE B) — source unique, réutilisée par meteo:archiver-jour. */
    public static function sites(): array
    {
        return array_keys(self::SITES);
    }

    public function getMeteoParSite(): array
    {
        // Sans clé configurée (cas de la démonstration), aucun appel réseau : valeurs météo
        // fictives et tempérées, affichées uniquement pour illustrer l'interface.
        if (! config('services.openweathermap.key')) {
            return [
                'SITE A' => $this->meteoFictive(21.0, 20.4, 55, 12.0),
                'SITE B' => $this->meteoFictive(19.0, 18.5, 60, 9.0),
            ];
        }

        $resultats = [];
        foreach (self::SITES as $site => $coords) {
            $resultats[$site] = Cache::remember("meteo_{$site}", now()->addMinutes(30), function () use ($coords) {
                $response = Http::get('https://api.openweathermap.org/data/2.5/weather', [
                    'lat' => $coords['lat'],
                    'lon' => $coords['lon'],
                    'appid' => config('services.openweathermap.key'),
                    'units' => 'metric',
                    'lang' => 'fr',
                ]);

                if (!$response->ok()) {
                    return ['erreur' => true, 'status' => $response->status()];
                }

                $data = $response->json();
                return [
                    'temperature' => $data['main']['temp'] ?? null,
                    'ressenti' => $data['main']['feels_like'] ?? null,
                    'humidite' => $data['main']['humidity'] ?? null,
                    'vent_kmh' => isset($data['wind']['speed']) ? round($data['wind']['speed'] * 3.6, 1) : null,
                    'description' => $data['weather'][0]['description'] ?? null,
                    'icone' => $data['weather'][0]['icon'] ?? null,
                    'maj' => now()->format('H:i'),
                ];
            });
        }
        return $resultats;
    }

    private function meteoFictive(float $temp, float $ressenti, int $humidite, float $vent): array
    {
        return [
            'temperature' => $temp,
            'ressenti' => $ressenti,
            'humidite' => $humidite,
            'vent_kmh' => $vent,
            'description' => 'Données fictives (démonstration)',
            'icone' => null,
            'maj' => now()->format('H:i'),
        ];
    }
}
