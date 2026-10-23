<?php

namespace Tests\Feature\Api;

use App\Models\Camion;
use App\Models\ConsommationSourceB;
use App\Models\Mesure;
use App\Models\RegistreMesure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComparateurRegistreTest extends TestCase
{
    use RefreshDatabase;

    public function test_retourne_200_avec_la_structure_attendue_pour_un_id_valide(): void
    {
        $camion = Camion::factory()->create(['groupe' => 'SITE A']);
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'consommation_l100' => 40]);
        RegistreMesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'km' => 1000, 'litres' => 400]);

        $response = $this->getJson("/api/comparateur-registre/{$camion->id}");

        $response->assertOk()->assertJsonStructure([
            'camion', 'groupe', 'source_b_disponible', 'nb_sources',
            'comparaison' => ['*' => [
                'mois', 'source_a_l100', 'registre_l100', 'ecart_pct', 'fiabilite', 'remarque',
                'source_a_lh', 'registre_lh', 'ecart_pct_lh', 'fiabilite_lh', 'remarque_lh',
                'source_b_l100', 'ecart_pct_source_b', 'fiabilite_source_b', 'remarque_source_b',
            ]],
        ]);
        $this->assertSame($camion->immatriculation, $response->json('camion'));
        $this->assertSame('2026-01', $response->json('comparaison.0.mois'));
    }

    // 3e source Télématique B — deux intervalles plein-à-plein se terminant en janvier 2026
    // doivent être moyennés en PONDÉRÉ (somme volume/somme distance), jamais en moyenne simple des
    // deux taux individuels, même principe que ConsommationSourceB::resumer()
    public function test_troisieme_source_source_b_disponible_et_ponderee(): void
    {
        $camion = Camion::factory()->create(['immatriculation' => 'DEMO-003']);
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'consommation_l100' => 40]);
        RegistreMesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'km' => 1000, 'litres' => 400]);

        // Intervalle 1 : 80L/1000km = 8%. Intervalle 2 : 40L/500km = 8%. Moyenne pondérée =
        // (80+40)/(1000+500)*100 = 8% — choisi identique aux deux pour ne pas dépendre de l'ordre.
        ConsommationSourceB::create([
            'device_id' => 990006, 'camion_id' => $camion->id,
            'plein_precedent_date' => '2025-12-15', 'plein_suivant_date' => '2026-01-10',
            'volume_l' => 80, 'distance_km' => 1000, 'consommation_l100km' => 8.0,
        ]);
        ConsommationSourceB::create([
            'device_id' => 990006, 'camion_id' => $camion->id,
            'plein_precedent_date' => '2026-01-10', 'plein_suivant_date' => '2026-01-25',
            'volume_l' => 40, 'distance_km' => 500, 'consommation_l100km' => 8.0,
        ]);

        $response = $this->getJson("/api/comparateur-registre/{$camion->id}");

        $response->assertOk();
        $this->assertTrue($response->json('source_b_disponible'));
        $this->assertSame(3, $response->json('nb_sources'));
        $ligne = $response->json('comparaison.0');
        $this->assertEquals(8.0, $ligne['source_b_l100']);
        // ecart Télématique B vs registre manuel (référence) : (8 - 40) / 40 * 100 = -80%
        $this->assertEquals(-80.0, $ligne['ecart_pct_source_b']);
        $this->assertSame('DIVERGENT', $ligne['fiabilite_source_b']);
        $this->assertStringContainsString('Télématique B', $ligne['remarque_source_b']);
    }

    // Véhicule sans AUCUN intervalle ConsommationSourceB (immatriculation aléatoire de test) :
    // l'écran garde son comportement à 2 sources — une vraie absence de donnée reste `null`,
    // jamais un chiffre fabriqué.
    public function test_source_b_indisponible_sans_aucun_intervalle(): void
    {
        $camion = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'consommation_l100' => 40]);
        RegistreMesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'km' => 1000, 'litres' => 400]);

        $response = $this->getJson("/api/comparateur-registre/{$camion->id}");

        $response->assertOk();
        $this->assertFalse($response->json('source_b_disponible'));
        $this->assertSame(2, $response->json('nb_sources'));
        $ligne = $response->json('comparaison.0');
        $this->assertNull($ligne['source_b_l100']);
        $this->assertNull($ligne['ecart_pct_source_b']);
        $this->assertSame('DONNEE_MANQUANTE', $ligne['fiabilite_source_b']);
    }

    // Véhicule Télématique B-only (aucune mesure Télématique A) : Télématique A n'est PAS une source — jamais un fantôme à 0
    // point dans le compte « N sources ». Ici Registre + Télématique B = 2 sources.
    public function test_source_a_non_compte_comme_source_sans_aucun_point(): void
    {
        $camion = Camion::factory()->create(['immatriculation' => 'DEMO-014']);
        RegistreMesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'km' => 1000, 'litres' => 400]);
        ConsommationSourceB::create([
            'device_id' => 1, 'camion_id' => $camion->id,
            'plein_precedent_date' => '2025-12-15', 'plein_suivant_date' => '2026-01-10',
            'volume_l' => 80, 'distance_km' => 1000, 'consommation_l100km' => 8.0,
        ]);

        $json = $this->getJson("/api/comparateur-registre/{$camion->id}")->assertOk()->json();

        $this->assertSame(['source_a' => false, 'registre' => true, 'source_b' => true], $json['sources']);
        $this->assertSame(2, $json['nb_sources']);
        $this->assertNull($json['source_b_motif_indisponible']);
    }

    public function test_motif_source_b_sans_boitier_pour_un_vehicule_source_a_seul(): void
    {
        $camion = Camion::factory()->create(['immatriculation' => 'DEMO-101']);
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'consommation_l100' => 40]);
        RegistreMesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'km' => 1000, 'litres' => 400]);

        $json = $this->getJson("/api/comparateur-registre/{$camion->id}")->json();

        $this->assertSame(['source_a' => true, 'registre' => true, 'source_b' => false], $json['sources']);
        $this->assertSame('Télématique B indisponible pour ce véhicule', $json['source_b_motif_indisponible']);
        $this->assertFalse($json['source_b_boitier_mappe']);
    }

    public function test_motif_source_b_boitier_present_sans_aucun_plein_detecte(): void
    {
        $camion = Camion::factory()->create(['immatriculation' => 'DEMO-015']);
        RegistreMesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'km' => 1000, 'litres' => 400]);

        $json = $this->getJson("/api/comparateur-registre/{$camion->id}")->json();

        $this->assertSame(['source_a' => false, 'registre' => true, 'source_b' => false], $json['sources']);
        $this->assertSame(1, $json['nb_sources']);
        $this->assertSame('Consommation Télématique B non calculable (aucun événement de plein détecté)', $json['source_b_motif_indisponible']);
        $this->assertTrue($json['source_b_boitier_mappe']);
    }

    // DEMO-002 a des pleins détectés mais est exclu du calcul : le message « aucun événement » serait faux.
    public function test_motif_source_b_vehicule_exclu_du_calcul_n_affirme_pas_l_absence_de_plein(): void
    {
        $camion = Camion::factory()->create(['immatriculation' => 'DEMO-002']);
        RegistreMesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'km' => 1000, 'litres' => 400]);

        $json = $this->getJson("/api/comparateur-registre/{$camion->id}")->json();

        $this->assertStringContainsString('exclu du calcul', $json['source_b_motif_indisponible']);
        $this->assertStringNotContainsString('aucun événement', $json['source_b_motif_indisponible']);
    }

    // Comparatif L/h : même logique écart %/fiabilité que le L/100km, calculée indépendamment.
    public function test_comparatif_lh_independant_du_l100km(): void
    {
        // litres est partagé par les deux ratios (L/100km = litres/km, L/h = litres/heures) : choisi
        // ici pour rester cohérent en L/100km (384/1000*100=38,4 vs 40 => 4,2%) tout en étant très
        // divergent en L/h (384/8=48 vs 20 => -58,3%), km et heures étant indépendants entre eux.
        $camion = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'consommation_l100' => 40, 'conso_horaire' => 20]);
        RegistreMesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'km' => 1000, 'litres' => 384, 'heures' => 8]);

        $response = $this->getJson("/api/comparateur-registre/{$camion->id}");

        $ligne = $response->json('comparaison.0');
        $this->assertSame('COHERENT', $ligne['fiabilite']);
        $this->assertEquals(48, $ligne['registre_lh']); // 384 litres / 8 heures
        $this->assertSame('DIVERGENT', $ligne['fiabilite_lh']);
        $this->assertNotNull($ligne['remarque_lh']);
    }

    // Un mois où seul le L/100km est exploitable doit afficher DONNEE_MANQUANTE pour le L/h
    // explicitement, jamais une case vide silencieuse qui laisserait croire à une absence de
    // problème plutôt qu'à une absence de donnée.
    public function test_comparatif_lh_donnee_manquante_quand_heures_absentes(): void
    {
        $camion = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'consommation_l100' => 40, 'conso_horaire' => 20]);
        RegistreMesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'km' => 1000, 'litres' => 400, 'heures' => null]);

        $response = $this->getJson("/api/comparateur-registre/{$camion->id}");

        $ligne = $response->json('comparaison.0');
        $this->assertSame('COHERENT', $ligne['fiabilite']); // L/100km, lui, reste exploitable
        $this->assertNull($ligne['registre_lh']);
        $this->assertSame('DONNEE_MANQUANTE', $ligne['fiabilite_lh']);
    }

    public function test_retourne_404_json_propre_sur_id_inconnu(): void
    {
        $response = $this->getJson('/api/comparateur-registre/999999');

        $response->assertStatus(404)
            ->assertJsonStructure(['message'])
            ->assertJsonFragment(['message' => 'Camion introuvable (id: 999999).']);
    }
}
