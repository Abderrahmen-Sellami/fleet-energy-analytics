<?php

namespace Tests\Feature\Api;

use App\Models\AnalyseCorrective;
use App\Models\Camion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysesCorrectivesTest extends TestCase
{
    use RefreshDatabase;

    public function test_retourne_toutes_les_analyses_du_camion_indépendamment_de_tout_rattachement_a_une_alerte(): void
    {
        $camion = Camion::factory()->create();
        $autreCamion = Camion::factory()->create();

        AnalyseCorrective::create([
            'camion_id' => $camion->id,
            'mois' => '2025-09-01',
            'cause' => 'Cause septembre',
            'action' => 'Action septembre',
            'statut_suivi' => 'RAS',
        ]);
        AnalyseCorrective::create([
            'camion_id' => $camion->id,
            'mois' => '2025-10-01',
            'cause' => 'Cause octobre',
            'action' => 'Action octobre',
            'statut_suivi' => 'En cours',
        ]);
        AnalyseCorrective::create([
            'camion_id' => $autreCamion->id,
            'mois' => '2025-09-01',
            'cause' => 'Ne doit pas apparaître',
        ]);

        $response = $this->getJson("/api/analyses-correctives/{$camion->id}");

        $response->assertOk()->assertJsonStructure([
            '*' => ['id', 'mois', 'cause', 'action', 'responsable', 'date_realisation', 'statut_suivi', 'methode_mesure', 'suivi_efficacite', 'source'],
        ]);
        $this->assertCount(2, $response->json());
        // orderByDesc('mois') : le plus récent (octobre) en premier.
        $this->assertSame('2025-10', $response->json('0.mois'));
        $this->assertSame('2025-09', $response->json('1.mois'));
    }

    public function test_retourne_tableau_vide_sans_analyse(): void
    {
        $camion = Camion::factory()->create();

        $response = $this->getJson("/api/analyses-correctives/{$camion->id}");

        $response->assertOk()->assertJson([]);
    }

    public function test_retourne_404_json_propre_sur_id_inconnu(): void
    {
        $response = $this->getJson('/api/analyses-correctives/999999');

        $response->assertStatus(404)->assertJsonStructure(['message']);
    }
}
