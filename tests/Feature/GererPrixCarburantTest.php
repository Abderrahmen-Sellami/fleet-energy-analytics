<?php

namespace Tests\Feature;

use App\Models\PrixCarburantHistorique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * carburant:prix (App\Console\Commands\GererPrixCarburant) — validation calendaire stricte de
 * --date.
 */
class GererPrixCarburantTest extends TestCase
{
    use RefreshDatabase;

    public static function datesImpossibles(): array
    {
        return [
            '13e mois' => ['2026-13-45'],
            '30 février' => ['2026-02-30'],
            '29 février hors année bissextile' => ['2026-02-29'],
            '31 avril' => ['2026-04-31'],
            'jour 00' => ['2026-09-00'],
            'mois 00' => ['2026-00-10'],
            'pas une date' => ['abc'],
        ];
    }

    #[DataProvider('datesImpossibles')]
    public function test_refuse_une_date_impossible_sans_rien_ecrire(string $date): void
    {
        $this->artisan('carburant:prix', ['prix' => '2.050', '--date' => $date])
            ->expectsOutputToContain('Date invalide')
            ->assertFailed();

        $this->assertSame(0, PrixCarburantHistorique::count());
    }

    public function test_enregistre_une_date_valide_telle_quelle(): void
    {
        $this->artisan('carburant:prix', ['prix' => '2.050', '--date' => '2026-02-28'])->assertSuccessful();

        $ligne = PrixCarburantHistorique::first();
        $this->assertSame('2026-02-28', $ligne->date_debut->toDateString());
        $this->assertEquals(2.05, (float) $ligne->prix_ht);
    }

    public function test_accepte_le_29_fevrier_d_une_annee_bissextile(): void
    {
        $this->artisan('carburant:prix', ['prix' => '1.985', '--date' => '2024-02-29'])->assertSuccessful();

        $this->assertSame('2024-02-29', PrixCarburantHistorique::first()->date_debut->toDateString());
    }

    public function test_sans_option_date_utilise_aujourd_hui(): void
    {
        $this->artisan('carburant:prix', ['prix' => '2.000'])->assertSuccessful();

        $this->assertSame(now()->toDateString(), PrixCarburantHistorique::first()->date_debut->toDateString());
    }

    public function test_ferme_la_periode_precedente_a_la_veille_de_la_nouvelle_date(): void
    {
        $this->artisan('carburant:prix', ['prix' => '1.985', '--date' => '2026-01-01'])->assertSuccessful();
        $this->artisan('carburant:prix', ['prix' => '2.050', '--date' => '2026-03-01'])->assertSuccessful();

        $premiere = PrixCarburantHistorique::orderBy('date_debut')->first();
        $this->assertSame('2026-02-28', $premiere->date_fin->toDateString());
    }
}
