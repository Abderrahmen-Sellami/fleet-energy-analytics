<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PositionsTempsReelTest extends TestCase
{
    use RefreshDatabase;

    public function test_position_temps_reel_indisponible_sans_appel_reseau(): void
    {
        Http::fake();

        $response = $this->getJson('/api/positions-temps-reel');

        $response->assertStatus(503)->assertJsonStructure(['message']);
        $this->assertStringContainsString('démonstration', $response->json('message'));
        Http::assertNothingSent();
    }
}
