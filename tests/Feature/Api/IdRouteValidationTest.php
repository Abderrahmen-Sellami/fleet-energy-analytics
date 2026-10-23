<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// Un id non numérique dans l'URL doit renvoyer un 404 JSON propre, jamais une erreur 500 avec trace.
class IdRouteValidationTest extends TestCase
{
    use RefreshDatabase;

    public static function routesAvecIdProvider(): array
    {
        return [
            'camions/{id}/mesures' => ['GET', '/api/camions/abc/mesures'],
            'comparateur-registre/{id}' => ['GET', '/api/comparateur-registre/abc'],
            'kilometrage-source_b/{camion_id}' => ['GET', '/api/kilometrage-source_b/abc'],
            'positions-historique/{camion_id}' => ['GET', '/api/positions-historique/abc'],
            'ser-historique/{camion_id}' => ['GET', '/api/ser-historique/abc'],
            'analyses-correctives/{camion_id}' => ['GET', '/api/analyses-correctives/abc'],
            'projection/{camion_id}/{metrique}' => ['GET', '/api/projection/abc/ecoscore'],
            'courbes/{id}/mensuel/{metrique}' => ['GET', '/api/courbes/abc/mensuel/consommation_l100'],
            'courbes/{id}/annuel/{metrique}' => ['GET', '/api/courbes/abc/annuel/consommation_l100'],
            // Système de rôles — même contrainte where('id','[0-9]+') que le reste.
            'utilisateurs/{id}' => ['PATCH', '/api/utilisateurs/abc'],
        ];
    }

    #[DataProvider('routesAvecIdProvider')]
    public function test_id_non_numerique_renvoie_404_json_propre_sans_trace(string $method, string $uri): void
    {
        $response = $this->json($method, $uri);

        $response->assertStatus(404)
            ->assertJsonStructure(['message'])
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('trace')
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('line')
            ->assertJsonFragment(['message' => 'Route ou identifiant invalide.']);

        $this->assertStringNotContainsString('fleet-energy-demo', $response->getContent());
        $this->assertStringNotContainsString('vendor', $response->getContent());
    }

    public function test_metrique_invalide_reste_en_422_json_propre_id_valide(): void
    {
        $camion = \App\Models\Camion::factory()->create();

        $response = $this->getJson("/api/courbes/{$camion->id}/mensuel/champ_invalide_xyz");

        $response->assertStatus(422)
            ->assertJsonStructure(['message'])
            ->assertJsonMissingPath('trace');
    }
}
