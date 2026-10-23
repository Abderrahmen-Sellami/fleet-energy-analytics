<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

/**
 * Handler API du 405 : message JSON propre, jamais la trace complète — y compris en
 * APP_DEBUG=true, où Laravel rendait sinon exception/file/line/trace (chemins serveur)
 */
class MethodeNonPermiseTest extends TestCase
{
    public function test_post_sur_une_route_en_lecture_seule_renvoie_405_json_sans_trace_meme_en_debug(): void
    {
        config(['app.debug' => true]);

        $response = $this->postJson('/api/ser-standards', ['ser_norme_ref' => 1]);

        $response->assertStatus(405)
            ->assertExactJson(['message' => 'Méthode POST non permise pour cette route.']);
        $this->assertStringNotContainsString('vendor', $response->getContent());
        $this->assertStringNotContainsString('trace', $response->getContent());
    }

    public function test_conserve_l_en_tete_allow(): void
    {
        $response = $this->deleteJson('/api/alertes');

        $response->assertStatus(405);
        $this->assertStringContainsString('GET', (string) $response->headers->get('Allow'));
    }

    public function test_le_message_reprend_la_methode_utilisee(): void
    {
        $this->putJson('/api/flotte/resume')
            ->assertStatus(405)
            ->assertJson(['message' => 'Méthode PUT non permise pour cette route.']);
    }

    public function test_le_404_de_routage_reste_inchange(): void
    {
        $this->getJson('/api/route-inexistante')
            ->assertStatus(404)
            ->assertExactJson(['message' => 'Route ou identifiant invalide.']);
    }

    public function test_un_405_hors_api_n_est_pas_intercepte(): void
    {
        $response = $this->call('DELETE', '/dashboard');

        $response->assertStatus(405);
        $this->assertFalse($response->headers->contains('Content-Type', 'application/json'));
    }
}
