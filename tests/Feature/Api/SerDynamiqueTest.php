<?php

namespace Tests\Feature\Api;

use App\Models\Camion;
use App\Models\RegistreMesure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SerDynamiqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_retourne_donnees_insuffisantes_sans_mesure_registre_exploitable(): void
    {
        $camion = Camion::factory()->create();

        $response = $this->getJson('/api/ser-dynamique');

        $response->assertOk();
        $ligne = collect($response->json())->firstWhere('id', $camion->id);
        $this->assertSame('DONNEES_INSUFFISANTES', $ligne['statut']);
    }

    public function test_retourne_un_statut_calcule_avec_une_mesure_exploitable(): void
    {
        // Usage en L/h (toupie) : ser_norme_ref=45 (défaut factory), incertitude=0.25 => ser1x=56.25, cible=44.1
        $camion = Camion::factory()->create(['usage_norme_ref' => 'Engin — catégorie 3']);
        RegistreMesure::factory()->create([
            'camion_id' => $camion->id,
            'date' => now()->startOfMonth()->toDateString(),
            'heures' => 10,
            'litres' => 400, // ipe=40 => VERT_FONCE (< cible 44.1)
        ]);

        $response = $this->getJson('/api/ser-dynamique');

        $response->assertOk();
        $ligne = collect($response->json())->firstWhere('id', $camion->id);
        $this->assertSame('VERT_FONCE', $ligne['statut']);
        $this->assertSame('l/h', $ligne['unite']);
        $this->assertSame('l/h', $ligne['unite_ser']);
        $this->assertEqualsWithDelta(40.0, $ligne['ipe'], 0.01);
        $this->assertArrayHasKey('ser_1x', $ligne);
        $this->assertArrayHasKey('cible', $ligne);
    }


    // usages Transport en L/100km, sans heures — statut calculable et unité exposée.
    public function test_usage_transport_est_juge_en_l100km_meme_sans_heures(): void
    {
        $camion = Camion::factory()->create(['usage_norme_ref' => 'Transport — catégorie 2']);
        RegistreMesure::factory()->create([
            'camion_id' => $camion->id, 'date' => now()->startOfMonth()->toDateString(),
            'km' => 1000, 'litres' => 700, 'heures' => null, // 70 L/100km > ser1x 56.25
        ]);

        $ligne = collect($this->getJson('/api/ser-dynamique')->assertOk()->json())->firstWhere('id', $camion->id);

        $this->assertSame('ROUGE', $ligne['statut']);
        $this->assertSame('l/100km', $ligne['unite']);
        $this->assertSame('l/100km', $ligne['unite_ser']);
        $this->assertEqualsWithDelta(70.0, $ligne['ipe'], 0.01);
    }

    // Un usage en L/h sans heures reste « unité incompatible » (raison conservée) et expose l'unité de sa SER.
    public function test_usage_lh_sans_heures_reste_unite_incompatible(): void
    {
        $camion = Camion::factory()->create(['usage_norme_ref' => 'Engin — catégorie 3']);
        RegistreMesure::factory()->create([
            'camion_id' => $camion->id, 'date' => now()->startOfMonth()->toDateString(),
            'km' => 1000, 'litres' => null, 'heures' => null, 'pe_l100' => 55,
        ]);

        $ligne = collect($this->getJson('/api/ser-dynamique')->assertOk()->json())->firstWhere('id', $camion->id);

        $this->assertSame('DONNEES_INSUFFISANTES', $ligne['statut']);
        $this->assertSame('UNITE_INCOMPATIBLE', $ligne['raison_insuffisance']);
        $this->assertSame('l/h', $ligne['unite_ser']);
    }

    public function test_donnees_insuffisantes_sans_mesure_expose_quand_meme_l_unite_de_la_ser(): void
    {
        $camion = Camion::factory()->create(['usage_norme_ref' => 'Transport — catégorie 1']);

        $ligne = collect($this->getJson('/api/ser-dynamique')->assertOk()->json())->firstWhere('id', $camion->id);

        $this->assertSame('DONNEES_INSUFFISANTES', $ligne['statut']);
        $this->assertSame('l/100km', $ligne['unite_ser']);
    }
}
