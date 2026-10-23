<?php

namespace Tests\Feature;

use App\Models\MeteoHistorique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * meteo:archiver-jour est planifiée toutes les 4h.
 */
class ArchiverMeteoJourTest extends TestCase
{
    use RefreshDatabase;

    private function fakeMeteo(float $temp = 20.0): void
    {
        Http::fake(['api.openweathermap.org/*' => Http::response([
            'main' => ['temp' => $temp, 'feels_like' => $temp, 'humidity' => 50],
            'wind' => ['speed' => 2.0],
            'weather' => [['description' => 'ciel dégagé', 'icon' => '01d']],
        ], 200)]);
    }

    public function test_premier_passage_archive_les_deux_sites(): void
    {
        $this->fakeMeteo();

        $this->artisan('meteo:archiver-jour')->assertExitCode(0);

        $this->assertSame(2, MeteoHistorique::where('date', now()->toDateString())->count());
    }

    public function test_passage_suivant_sans_nouveaute_ne_fait_rien_ni_appel_api_ni_ecrasement(): void
    {
        $this->fakeMeteo(20.0);
        $this->artisan('meteo:archiver-jour')->assertExitCode(0);

        // Nouvelle lecture différente, cache vidé : si la commande retapait l'API, la ligne du jour changerait.
        Cache::flush();
        Http::fake(['api.openweathermap.org/*' => Http::response(['main' => ['temp' => 35.0]], 200)]);

        $this->artisan('meteo:archiver-jour')
            ->expectsOutputToContain('Rien à faire')
            ->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertSame(2, MeteoHistorique::count());
        $this->assertEquals(20.0, MeteoHistorique::where('site', 'SITE A')->value('temperature'));
    }

    public function test_un_site_en_erreur_est_rattrape_au_passage_suivant_sans_toucher_l_autre(): void
    {
        MeteoHistorique::create(['site' => 'SITE A', 'date' => now()->toDateString(), 'temperature' => 18.0]);
        $this->fakeMeteo(25.0);

        $this->artisan('meteo:archiver-jour')->assertExitCode(0);

        $this->assertSame(2, MeteoHistorique::where('date', now()->toDateString())->count());
        $this->assertEquals(18.0, MeteoHistorique::where('site', 'SITE A')->value('temperature'), 'SITE A déjà archivé : lecture d\'origine conservée');
        $this->assertEquals(25.0, MeteoHistorique::where('site', 'SITE B')->value('temperature'));
    }
}
