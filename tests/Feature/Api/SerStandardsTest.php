<?php

namespace Tests\Feature\Api;

use App\Models\Camion;
use App\Models\RegistreMesure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Standards SER enrichi : agrégats Registre sur une fenêtre de mois entiers.
 */
class SerStandardsTest extends TestCase
{
    use RefreshDatabase;

    private function mesure(Camion $c, string $mois, array $champs = []): RegistreMesure
    {
        return RegistreMesure::factory()->create(array_merge([
            'camion_id' => $c->id,
            'date' => $mois . '-01',
            'km' => null, 'heures' => null, 'litres' => null,
        ], $champs));
    }

    private function periodeDe(array $reponse, Camion $c): array
    {
        return collect($reponse)->firstWhere('id', $c->id)['periode'];
    }

    public function test_les_champs_existants_sont_conserves_et_periode_ajoutee(): void
    {
        $c = Camion::factory()->create();
        $this->mesure($c, '2026-01', ['km' => 1000, 'heures' => 10, 'litres' => 400]);

        $json = $this->getJson('/api/ser-standards')->assertOk()->json();

        $ligne = collect($json)->firstWhere('id', $c->id);
        foreach (['immatriculation', 'ser_norme_ref', 'incertitude_pct', 'ser_1x', 'cible', 'valeur_reelle', 'unite_reelle', 'unite_ser', 'source_principale', 'marque'] as $cle) {
            $this->assertArrayHasKey($cle, $ligne);
        }
        $this->assertEqualsCanonicalizing(
            ['unite', 'ipe', 'ipe_mois', 'mois_periode', 'ipe_lh', 'ipe_lh_mois', 'km', 'km_mois', 'heures', 'heures_mois', 'conso_l100', 'conso_l100_mois'],
            array_keys($ligne['periode'])
        );
    }

    public function test_sommes_ponderees_jamais_des_moyennes_de_moyennes(): void
    {
        $c = Camion::factory()->create();
        // Mois A : 100 L/h ; mois B : 1 L/h. Moyenne simple = 50,5 ; somme pondérée = 1100/110 = 10.
        $this->mesure($c, '2026-01', ['km' => 1000, 'heures' => 10, 'litres' => 1000]);
        $this->mesure($c, '2026-02', ['km' => 4000, 'heures' => 100, 'litres' => 100]);

        $p = $this->periodeDe($this->getJson('/api/ser-standards')->json(), $c);

        $this->assertEquals(10.0, $p['ipe_lh']);
        $this->assertEquals(5000.0, $p['km']);
        $this->assertEquals(110.0, $p['heures']);
        // conso : (1000+100) / (1000+4000) * 100 = 22 L/100km (pas la moyenne de 100 et 2,5)
        $this->assertEquals(22.0, $p['conso_l100']);
        $this->assertSame(2, $p['mois_periode']);
        $this->assertSame(2, $p['ipe_lh_mois']);
    }

    public function test_ipe_et_conso_ignorent_les_mois_ou_une_des_deux_valeurs_manque(): void
    {
        $c = Camion::factory()->create();
        $this->mesure($c, '2026-01', ['km' => 1000, 'heures' => 10, 'litres' => 400]);   // complet
        $this->mesure($c, '2026-02', ['km' => 2000, 'heures' => null, 'litres' => 900]); // sans heures
        $this->mesure($c, '2026-03', ['km' => null, 'heures' => 50, 'litres' => 500]);   // sans km

        $p = $this->periodeDe($this->getJson('/api/ser-standards')->json(), $c);

        // IPÉ : mois 1 et 3 seulement (heures ET litres) = 900/60 = 15 ; jamais les litres du mois 2.
        $this->assertEquals(15.0, $p['ipe_lh']);
        $this->assertSame(2, $p['ipe_lh_mois']);
        // conso : mois 1 et 2 seulement (km ET litres) = 1300/3000*100 = 43,33.
        $this->assertEquals(43.33, $p['conso_l100']);
        $this->assertSame(2, $p['conso_l100_mois']);
        // km : 2 mois (1 et 2) ; heures : 2 mois (1 et 3).
        $this->assertEquals(3000.0, $p['km']);
        $this->assertEquals(60.0, $p['heures']);
        $this->assertSame(3, $p['mois_periode']);
    }

    public function test_valeurs_absentes_donnent_null_jamais_zero(): void
    {
        $c = Camion::factory()->create();
        // site-B : jamais d'heures ; une valeur à 0 = non saisie.
        $this->mesure($c, '2026-01', ['km' => 1000, 'heures' => 0, 'litres' => 400]);
        $this->mesure($c, '2026-02', ['km' => 0, 'heures' => null, 'litres' => 0]);

        $p = $this->periodeDe($this->getJson('/api/ser-standards')->json(), $c);

        $this->assertNull($p['ipe_lh']);
        $this->assertNull($p['heures']);
        $this->assertSame(0, $p['heures_mois']);
        $this->assertSame(0, $p['ipe_lh_mois']);
        $this->assertEquals(1000.0, $p['km']);
        $this->assertSame(1, $p['km_mois']);
        $this->assertEquals(40.0, $p['conso_l100']);
        $this->assertSame(2, $p['mois_periode']);
    }

    public function test_camion_sans_aucune_ligne_registre_donne_tout_a_null(): void
    {
        $c = Camion::factory()->create();

        $p = $this->periodeDe($this->getJson('/api/ser-standards')->json(), $c);

        $this->assertSame(0, $p['mois_periode']);
        foreach (['ipe_lh', 'km', 'heures', 'conso_l100'] as $cle) {
            $this->assertNull($p[$cle]);
        }
    }

    public function test_fenetre_en_mois_entiers(): void
    {
        $c = Camion::factory()->create();
        foreach (['2026-01', '2026-02', '2026-03', '2026-04', '2026-05'] as $mois) {
            $this->mesure($c, $mois, ['km' => 100, 'heures' => 10, 'litres' => 50]);
        }

        // 15 mars -> 2 avril : mars ET avril entiers (arrondi au mois), pas février ni mai.
        $p = $this->periodeDe($this->getJson('/api/ser-standards?depuis=2026-03-15&jusqu_a=2026-04-02')->json(), $c);

        $this->assertEquals(200.0, $p['km']);
        $this->assertSame(2, $p['km_mois']);
        $this->assertSame(2, $p['mois_periode']);
    }

    public function test_mois_attendus_bornes_par_le_dernier_mois_de_la_flotte_et_le_premier_du_camion(): void
    {
        $ancien = Camion::factory()->create();   // lignes janv-mars seulement
        $recent = Camion::factory()->create();   // arrivé en avril, lignes jusqu'en mai
        foreach (['2026-01', '2026-02', '2026-03'] as $m) {
            $this->mesure($ancien, $m, ['km' => 100, 'litres' => 10]);
        }
        foreach (['2026-04', '2026-05'] as $m) {
            $this->mesure($recent, $m, ['km' => 100, 'litres' => 10]);
        }

        $json = $this->getJson('/api/ser-standards')->json();

        // Dernier mois de la flotte = mai : l'ancien "devrait" avoir 5 mois, il en a 3 (trou signalé).
        $this->assertSame(5, $this->periodeDe($json, $ancien)['mois_periode']);
        $this->assertSame(3, $this->periodeDe($json, $ancien)['km_mois']);
        // Le récent n'est pas pénalisé pour les mois d'avant son arrivée : 2 mois attendus, 2 présents.
        $this->assertSame(2, $this->periodeDe($json, $recent)['mois_periode']);
        $this->assertSame(2, $this->periodeDe($json, $recent)['km_mois']);

        // Fenêtre postérieure au dernier mois de la flotte : 0 mois attendu (jamais un manque inventé).
        $futur = $this->getJson('/api/ser-standards?depuis=2026-09-01&jusqu_a=2026-12-31')->json();
        $this->assertSame(0, $this->periodeDe($futur, $ancien)['mois_periode']);
        $this->assertNull($this->periodeDe($futur, $ancien)['km']);
    }

    // Verrouillage de l'édition : plus aucune route d'écriture sur les standards SER.
    public function test_standards_ser_est_en_lecture_seule(): void
    {
        $c = Camion::factory()->create(['ser_norme_ref' => 45.0]);

        $this->putJson("/api/ser-standards/{$c->id}", ['ser_norme_ref' => 1])->assertStatus(404);
        $this->patchJson("/api/ser-standards/{$c->id}", ['ser_norme_ref' => 1])->assertStatus(404);
        $this->postJson("/api/ser-standards/{$c->id}/confirmer", ['confirme_par' => 'x'])->assertStatus(404);
        $this->deleteJson("/api/ser-standards/{$c->id}")->assertStatus(404);
        $this->assertEquals(45.0, $c->fresh()->ser_norme_ref);

        $ligne = collect($this->getJson('/api/ser-standards')->json())->firstWhere('id', $c->id);
        $this->assertArrayNotHasKey('ser_confirme_par', $ligne);
        $this->assertArrayNotHasKey('ser_confirme_le', $ligne);
    }

    public function test_parametres_invalides_renvoient_422_json(): void
    {
        $this->getJson('/api/ser-standards?depuis=hier')->assertStatus(422)->assertJsonStructure(['message']);
        $this->getJson('/api/ser-standards?jusqu_a=2026-13-40')->assertStatus(422);
        $this->getJson('/api/ser-standards?depuis=2026-05-01&jusqu_a=2026-01-01')->assertStatus(422);
    }


    // « ipe » = l'IPÉ dans l'unité de la SER du véhicule (L/100km pour les usages Transport, L/h
    // sinon) ; valeur_reelle / unite_reelle suivent la même unité.
    public function test_ipe_de_la_periode_suit_l_unite_de_la_ser(): void
    {
        $transport = Camion::factory()->create(['usage_norme_ref' => 'Transport — catégorie 2']);
        $this->mesure($transport, '2026-01', ['km' => 1000, 'heures' => 10, 'litres' => 1000]);
        $this->mesure($transport, '2026-02', ['km' => 4000, 'heures' => 100, 'litres' => 100]);
        $toupie = Camion::factory()->create(['usage_norme_ref' => 'Engin — catégorie 3']);
        $this->mesure($toupie, '2026-01', ['km' => 1000, 'heures' => 10, 'litres' => 1000]);
        $this->mesure($toupie, '2026-02', ['km' => 4000, 'heures' => 100, 'litres' => 100]);

        $json = $this->getJson('/api/ser-standards')->assertOk()->json();
        $lt = collect($json)->firstWhere('id', $transport->id);
        $ll = collect($json)->firstWhere('id', $toupie->id);

        // Transport : (1000+100)/(1000+4000)*100 = 22 L/100km, jamais la somme ÷ heures.
        $this->assertSame('l/100km', $lt['unite_ser']);
        $this->assertSame('l/100km', $lt['periode']['unite']);
        $this->assertEquals(22.0, $lt['periode']['ipe']);
        $this->assertSame(2, $lt['periode']['ipe_mois']);
        $this->assertSame('l/100km', $lt['unite_reelle']);
        // Autre usage : (1000+100)/(10+100) = 10 L/h.
        $this->assertSame('l/h', $ll['unite_ser']);
        $this->assertEquals(10.0, $ll['periode']['ipe']);
        $this->assertSame('l/h', $ll['unite_reelle']);
    }

    public function test_ipe_transport_sans_km_est_null_jamais_zero(): void
    {
        $c = Camion::factory()->create(['usage_norme_ref' => 'Transport — catégorie 1']);
        $this->mesure($c, '2026-01', ['km' => null, 'heures' => 10, 'litres' => 400]);

        $p = $this->periodeDe($this->getJson('/api/ser-standards')->json(), $c);

        $this->assertNull($p['ipe']);
        $this->assertSame(0, $p['ipe_mois']);
    }

    // % SER/usage et P.E précis — importés tels quels depuis le registre (sheets:sync-registre),
    // jamais recalculés ici.
    public function test_pct_ser_usage_et_pe_lh_precis_viennent_du_dernier_mois_exploitable(): void
    {
        $c = Camion::factory()->create(['usage_norme_ref' => 'Transport — catégorie 2']);
        $this->mesure($c, '2026-01', ['km' => 1000, 'litres' => 400, 'pct_ser_usage' => 0.10, 'pe_lh_precis' => 5.111111]);
        $this->mesure($c, '2026-03', ['km' => 2000, 'litres' => 900, 'pct_ser_usage' => 0.6385760988, 'pe_lh_precis' => 7.146341]);

        // Période resserrée sur janvier UNIQUEMENT (les colonnes "réel" ci-dessous sont bien
        // scopées à janvier), mais pct_ser_usage/pe_lh_precis restent ceux de mars (dernier mois
        // exploitable), pas ceux de janvier.
        $json = $this->getJson('/api/ser-standards?depuis=2026-01-01&jusqu_a=2026-01-31')->assertOk()->json();
        $ligne = collect($json)->firstWhere('id', $c->id);

        $this->assertEquals(0.6385760988, $ligne['pct_ser_usage']);
        $this->assertEquals(7.146341, $ligne['pe_lh_precis']);
        // Contre-vérification que la période a bien restreint les colonnes "réel" à janvier seul.
        $this->assertEquals(1000.0, $ligne['periode']['km']);
    }

    // Délai M+1 : un mois exploitable existe (SER/km/litres saisis) mais le sync n'a pas encore
    // enrichi ce mois précis avec % SER/usage et P.E précis (soit parce que le fichier norme
    // interne correspondant n'a pas encore été lu par la fenêtre glissante de
    // sheets:sync-registre.
    public function test_pct_ser_usage_et_pe_lh_precis_restent_null_si_non_encore_enrichis(): void
    {
        $c = Camion::factory()->create();
        $this->mesure($c, '2026-01', ['km' => 1000, 'litres' => 400, 'pct_ser_usage' => null, 'pe_lh_precis' => null]);

        $ligne = collect($this->getJson('/api/ser-standards')->json())->firstWhere('id', $c->id);

        $this->assertNull($ligne['pct_ser_usage']);
        $this->assertNull($ligne['pe_lh_precis']);
    }

    // Aucun mois exploitable du tout (camion sans donnée Registre) : même garde que valeur_reelle.
    public function test_pct_ser_usage_et_pe_lh_precis_sont_null_sans_aucun_mois_exploitable(): void
    {
        $c = Camion::factory()->create();

        $ligne = collect($this->getJson('/api/ser-standards')->json())->firstWhere('id', $c->id);

        $this->assertNull($ligne['pct_ser_usage']);
        $this->assertNull($ligne['pe_lh_precis']);
    }

    // Litres consommés : valeur brute du DERNIER mois exploitable, jamais une somme sur la période
    // choisie (même principe que pct_ser_usage/pe_lh_precis ci-dessus)
    public function test_litres_dernier_mois_vient_du_dernier_mois_exploitable(): void
    {
        $c = Camion::factory()->create(['usage_norme_ref' => 'Transport — catégorie 2']);
        $this->mesure($c, '2026-01', ['km' => 1000, 'litres' => 400]);
        $this->mesure($c, '2026-03', ['km' => 2000, 'litres' => 900]);

        // Période resserrée sur janvier UNIQUEMENT : litres_dernier_mois reste celui de mars (dernier
        // mois exploitable), pas celui de janvier ni une somme des deux.
        $json = $this->getJson('/api/ser-standards?depuis=2026-01-01&jusqu_a=2026-01-31')->assertOk()->json();
        $ligne = collect($json)->firstWhere('id', $c->id);

        $this->assertEquals(900.0, $ligne['litres_dernier_mois']);
    }

    // 0 = non saisi (même convention que le reste du projet, cf. NormeRefStatutService) — jamais un 0
    // affiché comme une vraie valeur de consommation.
    public function test_litres_dernier_mois_est_null_si_zero_ou_absent(): void
    {
        $c = Camion::factory()->create();
        $this->mesure($c, '2026-01', ['km' => 1000, 'litres' => 0]);

        $ligne = collect($this->getJson('/api/ser-standards')->json())->firstWhere('id', $c->id);

        $this->assertNull($ligne['litres_dernier_mois']);
    }

    // Aucun mois exploitable du tout : même garde que valeur_reelle/pct_ser_usage/pe_lh_precis.
    public function test_litres_dernier_mois_est_null_sans_aucun_mois_exploitable(): void
    {
        $c = Camion::factory()->create();

        $ligne = collect($this->getJson('/api/ser-standards')->json())->firstWhere('id', $c->id);

        $this->assertNull($ligne['litres_dernier_mois']);
    }
}
