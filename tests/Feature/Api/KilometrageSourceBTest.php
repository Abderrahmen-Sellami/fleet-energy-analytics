<?php

namespace Tests\Feature\Api;

use App\Console\Commands\Concerns\VehiculesSourceB;
use App\Models\Camion;
use App\Models\ConsommationSourceB;
use App\Models\MesureSourceB;
use App\Services\KilometrageSourceBSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un jour est corrompu si sa distance est négative (au-delà de la tolérance d'arrondi) ou > 1 000
 * km.
 */
class KilometrageSourceBTest extends TestCase
{
    use RefreshDatabase;

    private function camionSourceB(string $plaque): Camion
    {
        return Camion::factory()->create(['immatriculation' => $plaque, 'source_principale' => 'SOURCE_B']);
    }

    private function jour(string $plaque, string $date, float $distance, ?int $duree = 6000): void
    {
        MesureSourceB::create([
            'device_id' => VehiculesSourceB::DEVICE_PAR_PLAQUE[$plaque], 'horodatage_record' => $date . ' 00:00:00',
            'source' => 'mileage', 'distance_km' => $distance, 'duree_secondes' => $duree,
        ]);
    }

    public function test_un_jour_de_distance_negative_marque_le_vehicule_corrompu(): void
    {
        $camion = $this->camionSourceB('DEMO-001');
        $this->jour('DEMO-001', '2025-11-17', 320.0);
        $this->jour('DEMO-001', '2025-11-12', -154321.4, 21055);

        $reponse = $this->getJson("/api/kilometrage-source_b/{$camion->id}")->assertOk();

        $this->assertTrue($reponse->json('applicable'));
        $this->assertTrue($reponse->json('corrompu'));
        $this->assertSame(1, $reponse->json('nb_jours'));
        $this->assertSame('2025-11-12', $reponse->json('jours.0.date'));
        $this->assertSame('negative', $reponse->json('jours.0.type'));
        $this->assertEquals(-154321, $reponse->json('jours.0.distance_km'));
        $this->assertSame(['2025-11'], $reponse->json('mois_jours_aberrants'));
    }

    // Bornes exactes : 1 000 km est encore plausible (p99,9 réel = 758 km), 1 000,1 ne l'est plus ; −0,5 km est
    // du bruit d'arrondi (cas réel : −0,0027 km sur DEMO-006), −0,6 km un compteur faussé.
    public function test_bornes_de_detection_et_tolerance_d_arrondi(): void
    {
        $camion = $this->camionSourceB('DEMO-003');
        $this->jour('DEMO-003', '2026-01-01', 1000.0);
        $this->jour('DEMO-003', '2026-01-02', -0.0027);
        $this->jour('DEMO-003', '2026-01-03', -0.5);
        $this->assertFalse($this->getJson("/api/kilometrage-source_b/{$camion->id}")->json('corrompu'));

        $this->jour('DEMO-003', '2026-01-04', 1000.1);
        $this->jour('DEMO-003', '2026-01-05', -0.6);
        $reponse = $this->getJson("/api/kilometrage-source_b/{$camion->id}");
        $this->assertTrue($reponse->json('corrompu'));
        $this->assertSame(2, $reponse->json('nb_jours'));
    }

    // DEMO-003 / DEMO-004 ne doivent PAS être signalés : leur problème est un facteur de
    // conversion des volumes, pas un jour de kilométrage aberrant.
    public function test_un_vehicule_au_kilometrage_sain_n_est_pas_signale_meme_avec_une_vitesse_implicite_elevee(): void
    {
        $camion = $this->camionSourceB('DEMO-003');
        $this->jour('DEMO-003', '2026-07-13', 280.0, 20000);
        $this->jour('DEMO-003', '2026-07-14', 324.0, 6485);
        $this->jour('DEMO-003', '2026-07-15', 758.0, 30000);

        $reponse = $this->getJson("/api/kilometrage-source_b/{$camion->id}")->assertOk();

        $this->assertTrue($reponse->json('applicable'));
        $this->assertFalse($reponse->json('corrompu'));
        $this->assertSame(0, $reponse->json('nb_jours'));
    }

    public function test_un_camion_sans_boitier_source_b_n_est_pas_applicable(): void
    {
        $camion = Camion::factory()->create(['immatriculation' => 'DEMO-007']); // SITE B, absent du parc Télématique B

        $reponse = $this->getJson("/api/kilometrage-source_b/{$camion->id}")->assertOk();

        $this->assertFalse($reponse->json('applicable'));
        $this->assertFalse($reponse->json('corrompu'));
    }

    public function test_camion_inexistant_renvoie_404_json(): void
    {
        $this->getJson('/api/kilometrage-source_b/999999')->assertStatus(404)->assertJsonStructure(['message']);
    }

    // Les mois « affectés » sont ceux (mois de plein_suivant_date) des intervalles plein-à-plein
    // dont la fenêtre de distance — minuit (heure locale) du jour du plein précédent à minuit
    // (heure locale) du jour du plein suivant, bornes incluses — contient un jour aberrant.
    public function test_mois_des_intervalles_dont_la_fenetre_contient_le_jour_aberrant(): void
    {
        $camion = $this->camionSourceB('DEMO-001');
        $this->jour('DEMO-001', '2025-11-12', -154321.4);
        $device = VehiculesSourceB::DEVICE_PAR_PLAQUE['DEMO-001'];

        // Intervalle 12/11 → 13/11 : sa fenêtre (minuit local à minuit local) contient le jour aberrant.
        ConsommationSourceB::create(['device_id' => $device, 'camion_id' => $camion->id, 'plein_precedent_date' => '2025-11-12 06:44:40',
            'plein_suivant_date' => '2025-11-13 07:04:24', 'volume_l' => 169, 'distance_km' => null, 'consommation_l100km' => null]);
        // Intervalle 11/11 → 12/11 : sa fenêtre s'arrête juste avant le jour aberrant -> non affecté.
        ConsommationSourceB::create(['device_id' => $device, 'camion_id' => $camion->id, 'plein_precedent_date' => '2025-11-11 06:22:51',
            'plein_suivant_date' => '2025-11-12 06:44:40', 'volume_l' => 270, 'distance_km' => 320, 'consommation_l100km' => 84.13]);
        // Intervalle d'un autre mois, loin du jour aberrant -> non affecté.
        ConsommationSourceB::create(['device_id' => $device, 'camion_id' => $camion->id, 'plein_precedent_date' => '2026-03-02 06:00:00',
            'plein_suivant_date' => '2026-03-03 06:00:00', 'volume_l' => 100, 'distance_km' => 200, 'consommation_l100km' => 50]);

        $affectes = KilometrageSourceBSource::pourCamion($camion)['mois_intervalles_affectes'];

        $this->assertSame(['2025-11'], $affectes);
    }

    // « Comparaison des sources » embarque l'avertissement dans sa réponse (une seule requête côté écran).
    public function test_comparaison_des_sources_expose_l_avertissement(): void
    {
        $camion = $this->camionSourceB('DEMO-001');
        $this->jour('DEMO-001', '2025-11-12', -154321.4);
        ConsommationSourceB::create(['device_id' => VehiculesSourceB::DEVICE_PAR_PLAQUE['DEMO-001'], 'camion_id' => $camion->id,
            'plein_precedent_date' => '2025-11-19 06:00:00', 'plein_suivant_date' => '2025-11-20 06:00:00',
            'volume_l' => 100, 'distance_km' => 200, 'consommation_l100km' => 50]);

        $reponse = $this->getJson("/api/comparateur-registre/{$camion->id}")->assertOk();

        $this->assertTrue($reponse->json('source_b_disponible'));
        $this->assertTrue($reponse->json('kilometrage_source_b.corrompu'));
        $this->assertSame(1, $reponse->json('kilometrage_source_b.nb_jours'));
    }

    // La whitelist protège le bandeau KPI de l'Accueil et le rapport d'audit — le badge, lui, n'y
    // touche pas.
    public function test_whitelist_de_consommation_fiable(): void
    {
        $this->assertNotContains('DEMO-001', VehiculesSourceB::PLAQUES_CONSOMMATION_FIABLE);
        $this->assertContains('DEMO-003', VehiculesSourceB::PLAQUES_CONSOMMATION_FIABLE);
        $this->assertContains('DEMO-004', VehiculesSourceB::PLAQUES_CONSOMMATION_FIABLE);
    }

    // Aucune détection ne doit modifier les chiffres officiels : un véhicule Télématique B corrompu mais whitelisté garde
    // exactement les mêmes agrégats (litres, distance, mesures) avec ou sans jours aberrants dans mesures_source_b
    // — la détection ne fait que lire mesures_source_b, elle n'écrit ni ne filtre rien.
    public function test_la_detection_ne_modifie_aucune_donnee(): void
    {
        $camion = $this->camionSourceB('DEMO-013');
        $this->jour('DEMO-013', '2025-03-07', 8362.0);
        $avant = MesureSourceB::count();

        KilometrageSourceBSource::pourCamion($camion);
        $this->getJson("/api/kilometrage-source_b/{$camion->id}")->assertOk();

        $this->assertSame($avant, MesureSourceB::count());
        $this->assertEquals(8362.0, MesureSourceB::first()->distance_km);
    }
}
