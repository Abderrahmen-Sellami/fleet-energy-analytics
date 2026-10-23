<?php

namespace Tests\Feature\Api;

use App\Models\Camion;
use App\Models\PositionHistorique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PositionsHistoriqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_retourne_200_avec_les_points_dans_la_periode(): void
    {
        $camion = Camion::factory()->create();
        PositionHistorique::create([
            'camion_id' => $camion->id,
            'latitude' => 45.704210,
            'longitude' => 4.901830,
            'vitesse' => 30.0,
            'cap' => 314,
            'altitude' => 556,
            'horodatage_position' => '2026-08-10 10:00:00',
        ]);

        $response = $this->getJson("/api/positions-historique/{$camion->id}?depuis=2026-08-01&jusqu_a=2026-08-31");

        $response->assertOk()->assertJsonStructure([
            'camion_id', 'immatriculation', 'depuis', 'jusqu_a',
            'points' => ['*' => ['latitude', 'longitude', 'vitesse', 'cap', 'altitude', 'horodatage_position']],
        ]);
        $this->assertCount(1, $response->json('points'));
    }

    public function test_exclut_les_points_hors_periode(): void
    {
        $camion = Camion::factory()->create();
        PositionHistorique::create([
            'camion_id' => $camion->id,
            'latitude' => 45.7,
            'longitude' => 4.9,
            'horodatage_position' => '2026-08-10 10:00:00',
        ]);

        $response = $this->getJson("/api/positions-historique/{$camion->id}?depuis=2026-01-01&jusqu_a=2026-01-31");

        $response->assertOk();
        $this->assertCount(0, $response->json('points'));
    }

    public function test_retourne_404_json_propre_sur_id_inconnu(): void
    {
        $response = $this->getJson('/api/positions-historique/999999');

        $response->assertStatus(404)->assertJsonStructure(['message']);
    }

    public function test_retourne_422_sur_format_de_date_invalide(): void
    {
        $camion = Camion::factory()->create();

        $response = $this->getJson("/api/positions-historique/{$camion->id}?depuis=pas-une-date");

        $response->assertStatus(422)->assertJsonStructure(['message']);
    }

    public function test_retourne_422_quand_depuis_est_posterieur_a_jusqu_a(): void
    {
        $camion = Camion::factory()->create();

        $response = $this->getJson("/api/positions-historique/{$camion->id}?depuis=2026-08-31&jusqu_a=2026-08-01");

        $response->assertStatus(422)->assertJsonStructure(['message']);
    }

    /**
     * Dates impossibles : la regex seule les laissait passer et PHP les décalait silencieusement
     * vers une autre date.
     */
    public static function datesImpossibles(): array
    {
        return [
            '13e mois' => ['2026-13-45'],
            '30 février' => ['2026-02-30'],
            '29 février hors année bissextile' => ['2026-02-29'],
            '31 avril' => ['2026-04-31'],
            'jour 00' => ['2026-09-00'],
            'mois 00' => ['2026-00-10'],
        ];
    }

    #[DataProvider('datesImpossibles')]
    public function test_rejette_en_422_une_date_impossible_sur_depuis_et_jusqu_a(string $date): void
    {
        $camion = Camion::factory()->create();

        foreach (['depuis', 'jusqu_a'] as $param) {
            $this->getJson("/api/positions-historique/{$camion->id}?{$param}={$date}")
                ->assertStatus(422)
                ->assertJson(['message' => "Paramètre {$param} invalide, format attendu AAAA-MM-JJ."]);
            $this->getJson("/api/positions-historique-source_b/{$camion->id}?{$param}={$date}")
                ->assertStatus(422)
                ->assertJson(['message' => "Paramètre {$param} invalide, format attendu AAAA-MM-JJ."]);
        }
    }

    public function test_accepte_le_29_fevrier_d_une_annee_bissextile(): void
    {
        $camion = Camion::factory()->create();

        $this->getJson("/api/positions-historique/{$camion->id}?depuis=2024-02-29&jusqu_a=2024-03-01")
            ->assertOk()->assertJsonPath('depuis', '2024-02-29T00:00:00+00:00');
        $this->getJson("/api/positions-historique-source_b/{$camion->id}?depuis=2024-02-29&jusqu_a=2024-03-01")
            ->assertOk()->assertJsonPath('depuis', '2024-02-29T00:00:00+00:00');
    }

    public function test_historique_source_b_sans_boitier_renvoie_une_liste_vide(): void
    {
        $camion = Camion::factory()->create();

        $this->getJson("/api/positions-historique-source_b/{$camion->id}?depuis=2026-08-01&jusqu_a=2026-08-31")
            ->assertOk()
            ->assertJsonStructure(['camion_id', 'immatriculation', 'depuis', 'jusqu_a', 'points'])
            ->assertJsonCount(0, 'points');
    }

    public function test_historique_source_b_422_quand_depuis_est_posterieur_a_jusqu_a(): void
    {
        $camion = Camion::factory()->create();

        $this->getJson("/api/positions-historique-source_b/{$camion->id}?depuis=2026-08-31&jusqu_a=2026-08-01")
            ->assertStatus(422)->assertJsonStructure(['message']);
    }
}
