<?php

namespace Tests\Unit;

use App\Models\Camion;
use App\Models\RegistreMesure;
use App\Services\NormeRefStatutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NormeRefStatutService porte le calcul du statut couleur officiel norme interne, partagé entre
 * SerDynamiqueController, SerStandardsController, AlerteService et SuggestionService — une erreur
 * ici se répercuterait partout. ser_norme_ref=50, incertitude=0.25 => ser1x=62.5, cible=44.1 pour
 * tous les cas ci-dessous.
 */
class NormeRefStatutServiceTest extends TestCase
{
    use RefreshDatabase;

    private const USAGE_LH = 'Engin — catégorie 3';

    private function camion(array $overrides = []): Camion
    {
        return Camion::factory()->create(array_merge([
            'usage_norme_ref' => self::USAGE_LH,
            'ser_norme_ref' => 50.0,
            'ser_norme_ref_incertitude' => 0.25,
            'ser_norme_ref_cible' => 44.1,
        ], $overrides));
    }

    public function test_statut_rouge_quand_ipe_depasse_ser_fois_incertitude(): void
    {
        $camion = $this->camion();
        // ser1x = 50 * 1.25 = 62.5 ; ipe = 700/10 = 70 > 62.5
        $mesure = RegistreMesure::factory()->make(['heures' => 10, 'litres' => 700, 'pe_l100' => null]);

        $statut = (new NormeRefStatutService())->calculerStatut($camion, $mesure);

        $this->assertSame('ROUGE', $statut['statut']);
        $this->assertEquals(70.0, $statut['ipe']);
    }

    public function test_statut_orange_quand_ipe_entre_ser_et_seuil_incertitude(): void
    {
        $camion = $this->camion();
        // ipe = 550/10 = 55 ; ser(50) < 55 <= ser1x(62.5)
        $mesure = RegistreMesure::factory()->make(['heures' => 10, 'litres' => 550, 'pe_l100' => null]);

        $statut = (new NormeRefStatutService())->calculerStatut($camion, $mesure);

        $this->assertSame('ORANGE', $statut['statut']);
    }

    public function test_statut_vert_clair_quand_ipe_entre_cible_et_ser(): void
    {
        $camion = $this->camion();
        // ipe = 495/10 = 49.5 ; cible(44.1) < 49.5 <= ser(50)
        $mesure = RegistreMesure::factory()->make(['heures' => 10, 'litres' => 495, 'pe_l100' => null]);

        $statut = (new NormeRefStatutService())->calculerStatut($camion, $mesure);

        $this->assertSame('VERT_CLAIR', $statut['statut']);
    }

    public function test_statut_vert_fonce_quand_ipe_sous_la_cible(): void
    {
        $camion = $this->camion();
        // ipe = 400/10 = 40 <= cible(44.1)
        $mesure = RegistreMesure::factory()->make(['heures' => 10, 'litres' => 400, 'pe_l100' => null]);

        $statut = (new NormeRefStatutService())->calculerStatut($camion, $mesure);

        $this->assertSame('VERT_FONCE', $statut['statut']);
    }

    public function test_calculerIpe_bascule_sur_pe_l100_quand_heures_absentes(): void
    {
        $camion = $this->camion();
        // Pas d'heures renseignées (SITE B, pas de saisie registre manuel en heures) => bascule sur pe_l100
        $mesure = RegistreMesure::factory()->make(['heures' => null, 'litres' => null, 'pe_l100' => 42.0]);

        $statut = (new NormeRefStatutService())->calculerStatut($camion, $mesure);

        $this->assertEquals(42.0, $statut['ipe']);
        $this->assertSame('DONNEES_INSUFFISANTES', $statut['statut']);
        $this->assertSame('UNITE_INCOMPATIBLE', $statut['raison']);
    }

    public function test_statut_donnees_insuffisantes_quand_ipe_en_l100km_pas_en_lh(): void
    {
        $camion = $this->camion();
        $mesure = RegistreMesure::factory()->make(['heures' => null, 'litres' => null, 'pe_l100' => 55.0]);

        $statut = (new NormeRefStatutService())->calculerStatut($camion, $mesure);

        $this->assertSame('DONNEES_INSUFFISANTES', $statut['statut']);
        $this->assertSame('UNITE_INCOMPATIBLE', $statut['raison']);
    }

    // heures=0 doit être traité comme "absent", pas comme une durée valide de zéro heure — même
    // garde que heures=null, testé séparément pour ne pas dépendre d'une coercition implicite.
    public function test_statut_donnees_insuffisantes_quand_heures_vaut_zero(): void
    {
        $camion = $this->camion();
        $mesure = RegistreMesure::factory()->make(['heures' => 0, 'litres' => 500, 'pe_l100' => 42.0]);

        $statut = (new NormeRefStatutService())->calculerStatut($camion, $mesure);

        $this->assertSame('DONNEES_INSUFFISANTES', $statut['statut']);
        $this->assertSame('UNITE_INCOMPATIBLE', $statut['raison']);
    }

    // raisonDonneesInsuffisantes() : distingue les 2 autres cas de DONNEES_INSUFFISANTES (aucune
    // mesure exploitable du tout), pour que texteDonneesInsuffisantes() côté frontend n'ait plus
    // besoin de recalculer une date lui-même.
    public function test_raison_abonnement_recent_quand_source_a_actif_depuis_moins_de_deux_mois(): void
    {
        $camion = $this->camion(['source_a_actif_depuis' => now()->subMonth()->toDateString()]);

        $raison = (new NormeRefStatutService())->raisonDonneesInsuffisantes($camion, null);

        $this->assertSame('ABONNEMENT_RECENT', $raison);
    }

    public function test_raison_mois_non_saisi_quand_abonnement_ancien_et_aucune_mesure(): void
    {
        $camion = $this->camion(['source_a_actif_depuis' => now()->subYear()->toDateString()]);

        $raison = (new NormeRefStatutService())->raisonDonneesInsuffisantes($camion, null);

        $this->assertSame('MOIS_NON_SAISI', $raison);
    }

    public function test_raison_null_quand_une_mesure_exploitable_existe(): void
    {
        $camion = $this->camion();
        $mesure = RegistreMesure::factory()->make(['heures' => 10, 'litres' => 400]);

        $raison = (new NormeRefStatutService())->raisonDonneesInsuffisantes($camion, $mesure);

        $this->assertNull($raison);
    }

    // Cas ajouté : une mesure exploitable peut exister alors que ser_norme_ref est encore null —
    // sans ce cas, la méthode retournait silencieusement null malgré un statut
    // DONNEES_INSUFFISANTES réel côté SerDynamiqueController, même principe de fiabilité que
    // UNITE_INCOMPATIBLE.
    public function test_raison_ser_norme_ref_non_definie_quand_mesure_exploitable_mais_ser_null(): void
    {
        $camion = $this->camion(['ser_norme_ref' => null]);
        $mesure = RegistreMesure::factory()->make(['heures' => 10, 'litres' => 400]);

        $raison = (new NormeRefStatutService())->raisonDonneesInsuffisantes($camion, $mesure);

        $this->assertSame('SER_NORME_REF_NON_DEFINIE', $raison);
    }

    private function camionTransport(array $overrides = []): Camion
    {
        return $this->camion(array_merge(['usage_norme_ref' => 'Transport — catégorie 1'], $overrides));
    }

    // Cas "données insuffisantes" : dérivé au niveau de l'appelant (SerDynamiqueController) via
    // derniersMoisExploitables() vide ou ser_norme_ref absent — testé ici au niveau de la source de
    // vérité (derniersMoisExploitables), pas d'une réimplémentation du contrôleur.
    public function test_derniersMoisExploitables_est_vide_sans_aucune_mesure_registre(): void
    {
        $camion = $this->camion();

        $resultat = (new NormeRefStatutService())->derniersMoisExploitables($camion, 1);

        $this->assertTrue($resultat->isEmpty());
    }

    public function test_derniersMoisExploitables_exclut_les_mesures_sans_heures_ni_pe_l100(): void
    {
        $camion = $this->camion();
        // Ligne présente mais non exploitable : ni heures>0+litres, ni pe_l100 renseigné — le mois
        // est saisi mais pas encore calculable.
        RegistreMesure::factory()->create([
            'camion_id' => $camion->id,
            'date' => now()->startOfMonth()->toDateString(),
            'heures' => null,
            'litres' => null,
            'pe_l100' => null,
        ]);

        $resultat = (new NormeRefStatutService())->derniersMoisExploitables($camion, 1);

        $this->assertTrue($resultat->isEmpty());
    }

    public function test_derniersMoisExploitables_retourne_la_ligne_exploitable_la_plus_recente(): void
    {
        $camion = $this->camion();
        RegistreMesure::factory()->create([
            'camion_id' => $camion->id,
            'date' => now()->subMonths(2)->startOfMonth()->toDateString(),
            'heures' => 10,
            'litres' => 500,
        ]);
        $recente = RegistreMesure::factory()->create([
            'camion_id' => $camion->id,
            'date' => now()->startOfMonth()->toDateString(),
            'heures' => 10,
            'litres' => 400,
        ]);

        $resultat = (new NormeRefStatutService())->derniersMoisExploitables($camion, 1);

        $this->assertCount(1, $resultat);
        $this->assertTrue($resultat->first()->is($recente));
    }


    // Unité par usage

    public function test_uniteSer_transport_en_l100km_et_tout_autre_usage_en_lh(): void
    {
        $svc = new NormeRefStatutService();

        $this->assertSame('l/100km', $svc->uniteSer($this->camion(['usage_norme_ref' => 'Transport — catégorie 2'])));
        $this->assertSame('l/100km', $svc->uniteSer($this->camion(['usage_norme_ref' => 'Transport — catégorie 1'])));
        $this->assertSame('l/h', $svc->uniteSer($this->camion(['usage_norme_ref' => self::USAGE_LH])));
        $this->assertSame('l/h', $svc->uniteSer($this->camion(['usage_norme_ref' => 'Engin — catégorie 4'])));
        $this->assertSame('l/h', $svc->uniteSer($this->camion(['usage_norme_ref' => null])));
    }

    // ser=50, ser1x=62.5, cible=44.1 : les 4 couleurs sur un IPE en L/100km (km=1000, donc IPE = litres / 10).
    public function test_transport_les_quatre_couleurs_en_l100km(): void
    {
        $camion = $this->camionTransport();
        $svc = new NormeRefStatutService();
        $cas = ['ROUGE' => 700, 'ORANGE' => 550, 'VERT_CLAIR' => 495, 'VERT_FONCE' => 400];

        foreach ($cas as $attendu => $litres) {
            $r = $svc->calculerStatut($camion, RegistreMesure::factory()->make(['km' => 1000, 'litres' => $litres, 'heures' => null, 'pe_l100' => null]));
            $this->assertSame($attendu, $r['statut'], "litres={$litres}");
            $this->assertSame('l/100km', $r['unite']);
            $this->assertSame('l/100km', $r['unite_ser']);
        }
    }

    // Les véhicules « Donnée manquante » par manque d'heures : un usage Transport sans heures a désormais un
    // statut calculable — plus de UNITE_INCOMPATIBLE.
    public function test_transport_sans_heures_obtient_un_statut_calculable(): void
    {
        $camion = $this->camionTransport();
        $mesure = RegistreMesure::factory()->make(['km' => 1000, 'litres' => 700, 'heures' => null, 'pe_l100' => null]);

        $r = (new NormeRefStatutService())->calculerStatut($camion, $mesure);

        $this->assertSame('ROUGE', $r['statut']);
        $this->assertArrayNotHasKey('raison', $r);
        $this->assertEquals(70.0, $r['ipe']);
    }

    // Avec des heures ET des km, un usage Transport est jugé sur les KM : litres/heures (ici 700/10 = 70, qui
    // serait ROUGE en L/h) n'intervient pas — IPE = 700/5000*100 = 14 => VERT_FONCE.
    public function test_transport_ignore_les_heures_meme_quand_elles_sont_saisies(): void
    {
        $camion = $this->camionTransport();
        $mesure = RegistreMesure::factory()->make(['km' => 5000, 'heures' => 10, 'litres' => 700, 'pe_l100' => null]);

        $r = (new NormeRefStatutService())->calculerStatut($camion, $mesure);

        $this->assertSame('VERT_FONCE', $r['statut']);
        $this->assertEquals(14.0, $r['ipe']);
    }

    public function test_transport_retombe_sur_pe_l100_quand_km_absent(): void
    {
        $camion = $this->camionTransport();
        $mesure = RegistreMesure::factory()->make(['km' => null, 'litres' => null, 'heures' => null, 'pe_l100' => 55.0]);

        $r = (new NormeRefStatutService())->calculerStatut($camion, $mesure);

        $this->assertSame('ORANGE', $r['statut']); // 50 < 55 <= 62.5
        $this->assertEquals(55.0, $r['ipe']);
    }

    public function test_une_ser_de_petite_valeur_est_jugee_telle_quelle_sans_traitement_particulier(): void
    {
        $camion = $this->camionTransport(['ser_norme_ref' => 9.0, 'ser_norme_ref_incertitude' => 0.25, 'ser_norme_ref_cible' => 8.82]);
        $mesure = RegistreMesure::factory()->make(['km' => 1000, 'litres' => 640, 'heures' => null, 'pe_l100' => null]); // 64 L/100km

        $r = (new NormeRefStatutService())->calculerStatut($camion, $mesure);

        $this->assertSame('ROUGE', $r['statut']);
    }

    public function test_derniersMoisExploitables_transport_se_base_sur_les_km_pas_sur_les_heures(): void
    {
        $camion = $this->camionTransport();
        // Exploitable en L/100km (km + litres), aucune heure.
        $sansHeures = RegistreMesure::factory()->create([
            'camion_id' => $camion->id, 'date' => now()->subMonth()->startOfMonth()->toDateString(),
            'km' => 1000, 'litres' => 500, 'heures' => null, 'pe_l100' => null,
        ]);
        // Heures + litres seulement (ni km ni pe_l100) : exploitable en L/h, PAS en L/100km.
        RegistreMesure::factory()->create([
            'camion_id' => $camion->id, 'date' => now()->startOfMonth()->toDateString(),
            'km' => null, 'heures' => 10, 'litres' => 400, 'pe_l100' => null,
        ]);

        $resultat = (new NormeRefStatutService())->derniersMoisExploitables($camion->fresh('registreMesures'), 1);

        $this->assertCount(1, $resultat);
        $this->assertTrue($resultat->first()->is($sansHeures));
    }

    public function test_derniersMoisExploitables_usage_lh_reste_base_sur_les_heures(): void
    {
        $camion = $this->camion();
        // km + litres sans heures : PAS exploitable en L/h (ni pe_l100).
        RegistreMesure::factory()->create([
            'camion_id' => $camion->id, 'date' => now()->startOfMonth()->toDateString(),
            'km' => 1000, 'heures' => null, 'litres' => 500, 'pe_l100' => null,
        ]);

        $this->assertTrue((new NormeRefStatutService())->derniersMoisExploitables($camion->fresh('registreMesures'), 1)->isEmpty());
    }
}
