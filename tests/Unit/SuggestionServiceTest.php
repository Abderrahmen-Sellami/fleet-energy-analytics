<?php

namespace Tests\Unit;

use App\Models\Camion;
use App\Models\Mesure;
use App\Models\Suggestion;
use App\Models\RegistreMesure;
use App\Services\NormeRefStatutService;
use App\Services\SuggestionService;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Une classe de test par règle de déclenchement de SuggestionService (8 règles), même principe
 * que AlerteServiceTest : un camion qui déclenche, un qui ne déclenche pas, jamais de données
 * réelles. WeatherService est mocké (Mockery) — CANICULE_IPE_HAUSSE mise à part, la météo n'a
 * aucun effet sur les 6 autres règles, donc un mock "toujours en erreur" leur suffit.
 */
class SuggestionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(array $meteoParSite = ['SITE A' => ['erreur' => true], 'SITE B' => ['erreur' => true]]): SuggestionService
    {
        $weather = \Mockery::mock(WeatherService::class);
        $weather->shouldReceive('getMeteoParSite')->andReturn($meteoParSite);

        return new SuggestionService($weather, new NormeRefStatutService());
    }

    // Camion en usage L/h (toupie) : les tests norme interne ci-dessous sèment des heures/litres
    // (IPE en L/h)
    private function camionLh(array $o = []): Camion
    {
        return Camion::factory()->create(array_merge(['usage_norme_ref' => 'Engin — catégorie 3'], $o));
    }

    private function suggestionExiste(int $camionId, string $regle): bool
    {
        return Suggestion::where('camion_id', $camionId)->where('regle_declenchee', $regle)->where('resolue', false)->exists();
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    // un usage Transport est jugé en L/100km (litres ÷ km × 100), sans heures.
    public function test_norme_ref_rouge_2mois_transport_se_base_sur_le_l100km(): void
    {
        // ser=45, incertitude=0.25 => ser1x=56.25 ; km=1000, litres=700 => 70 L/100km => ROUGE
        $declenche = Camion::factory()->create(['usage_norme_ref' => 'Transport — catégorie 1']);
        foreach ([now()->startOfMonth(), now()->subMonthNoOverflow()->startOfMonth()] as $mois) {
            RegistreMesure::factory()->create(['camion_id' => $declenche->id, 'date' => $mois->toDateString(), 'km' => 1000, 'litres' => 700, 'heures' => null]);
        }
        $normal = Camion::factory()->create(['usage_norme_ref' => 'Transport — catégorie 2']);
        foreach ([now()->startOfMonth(), now()->subMonthNoOverflow()->startOfMonth()] as $mois) {
            RegistreMesure::factory()->create(['camion_id' => $normal->id, 'date' => $mois->toDateString(), 'km' => 1000, 'litres' => 400, 'heures' => null]);
        }

        $this->service()->detecterEtEnregistrer();

        $this->assertTrue($this->suggestionExiste($declenche->id, 'NORME_REF_ROUGE_2MOIS'));
        $this->assertFalse($this->suggestionExiste($normal->id, 'NORME_REF_ROUGE_2MOIS'));
        $valeurs = Suggestion::where('camion_id', $declenche->id)->where('regle_declenchee', 'NORME_REF_ROUGE_2MOIS')->value('valeurs');
        $this->assertSame('l/100km', (is_string($valeurs) ? json_decode($valeurs, true) : $valeurs)['unite'] ?? null);
    }

    public function test_norme_ref_rouge_2mois_se_declenche_sur_deux_mois_rouges_consecutifs(): void
    {
        // ser_norme_ref=45 (défaut factory), incertitude=0.25 => ser1x=56.25
        $declenche = $this->camionLh();
        RegistreMesure::factory()->create(['camion_id' => $declenche->id, 'date' => now()->startOfMonth()->toDateString(), 'heures' => 10, 'litres' => 700]);
        RegistreMesure::factory()->create(['camion_id' => $declenche->id, 'date' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'heures' => 10, 'litres' => 700]);

        $normal = $this->camionLh();
        RegistreMesure::factory()->create(['camion_id' => $normal->id, 'date' => now()->startOfMonth()->toDateString(), 'heures' => 10, 'litres' => 700]);
        // un seul mois exploitable => pas assez pour la règle

        $this->service()->detecterEtEnregistrer();

        $this->assertTrue($this->suggestionExiste($declenche->id, 'NORME_REF_ROUGE_2MOIS'));
        $this->assertFalse($this->suggestionExiste($normal->id, 'NORME_REF_ROUGE_2MOIS'));
    }

    public function test_utilisation_abusive_faible_se_declenche_sous_5(): void
    {
        $declenche = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $declenche->id, 'date' => '2026-01-01', 'utilisation_abusive' => 3]);

        $normal = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $normal->id, 'date' => '2026-01-01', 'utilisation_abusive' => 8]);

        $this->service()->detecterEtEnregistrer();

        $this->assertTrue($this->suggestionExiste($declenche->id, 'UTILISATION_ABUSIVE_FAIBLE'));
        $this->assertFalse($this->suggestionExiste($normal->id, 'UTILISATION_ABUSIVE_FAIBLE'));
    }

    public function test_arret_faible_se_declenche_sous_5(): void
    {
        $declenche = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $declenche->id, 'date' => '2026-01-01', 'arret' => 3]);

        $normal = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $normal->id, 'date' => '2026-01-01', 'arret' => 8]);

        $this->service()->detecterEtEnregistrer();

        $this->assertTrue($this->suggestionExiste($declenche->id, 'ARRET_FAIBLE'));
        $this->assertFalse($this->suggestionExiste($normal->id, 'ARRET_FAIBLE'));
    }

    public function test_divergence_registre_recurrente_se_declenche_a_partir_de_2_mois_divergents(): void
    {
        $declenche = Camion::factory()->create();
        foreach ([now(), now()->subMonthNoOverflow()] as $mois) {
            Mesure::factory()->create(['camion_id' => $declenche->id, 'date' => $mois->copy()->startOfMonth()->toDateString(), 'consommation_l100' => 60]);
            RegistreMesure::factory()->create(['camion_id' => $declenche->id, 'date' => $mois->copy()->startOfMonth()->toDateString(), 'km' => 1000, 'litres' => 300]); // écart 100%
        }

        $normal = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $normal->id, 'date' => now()->startOfMonth()->toDateString(), 'consommation_l100' => 60]);
        RegistreMesure::factory()->create(['camion_id' => $normal->id, 'date' => now()->startOfMonth()->toDateString(), 'km' => 1000, 'litres' => 300]); // 1 seul mois divergent

        $this->service()->detecterEtEnregistrer();

        $this->assertTrue($this->suggestionExiste($declenche->id, 'DIVERGENCE_REGISTRE_RECURRENTE'));
        $this->assertFalse($this->suggestionExiste($normal->id, 'DIVERGENCE_REGISTRE_RECURRENTE'));
    }

    public function test_usage_norme_ref_manquant_se_declenche_quand_strictement_null(): void
    {
        $declenche = Camion::factory()->create(['usage_norme_ref' => null]);
        $normal = Camion::factory()->create(['usage_norme_ref' => 'Transport — catégorie 2']);

        $this->service()->detecterEtEnregistrer();

        $this->assertTrue($this->suggestionExiste($declenche->id, 'USAGE_NORME_REF_MANQUANT'));
        $this->assertFalse($this->suggestionExiste($normal->id, 'USAGE_NORME_REF_MANQUANT'));
    }

    public function test_canicule_ipe_hausse_se_declenche_avec_chaleur_et_ipe_en_hausse(): void
    {
        $declenche = $this->camionLh(['groupe' => 'SITE A']);
        RegistreMesure::factory()->create(['camion_id' => $declenche->id, 'date' => now()->startOfMonth()->toDateString(), 'heures' => 10, 'litres' => 500]); // ipe=50
        RegistreMesure::factory()->create(['camion_id' => $declenche->id, 'date' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'heures' => 10, 'litres' => 400]); // ipe=40, hausse

        // Même camion/mêmes mesures mais météo sous le seuil canicule => ne doit pas déclencher
        $normal = $this->camionLh(['groupe' => 'SITE B']);
        RegistreMesure::factory()->create(['camion_id' => $normal->id, 'date' => now()->startOfMonth()->toDateString(), 'heures' => 10, 'litres' => 500]);
        RegistreMesure::factory()->create(['camion_id' => $normal->id, 'date' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'heures' => 10, 'litres' => 400]);

        $this->service(['SITE A' => ['erreur' => false, 'temperature' => 36], 'SITE B' => ['erreur' => false, 'temperature' => 28]])
            ->detecterEtEnregistrer();

        $this->assertTrue($this->suggestionExiste($declenche->id, 'CANICULE_IPE_HAUSSE'));
        $this->assertFalse($this->suggestionExiste($normal->id, 'CANICULE_IPE_HAUSSE'));
    }

    public function test_amelioration_constatee_se_declenche_apres_deux_mois_difficiles_consecutifs(): void
    {
        // ser_norme_ref=50, incertitude=0.25 => ser1x=62.5, cible=44.1
        $declenche = $this->camionLh(['ser_norme_ref' => 50, 'ser_norme_ref_incertitude' => 0.25, 'ser_norme_ref_cible' => 44.1]);
        RegistreMesure::factory()->create(['camion_id' => $declenche->id, 'date' => now()->startOfMonth()->toDateString(), 'heures' => 10, 'litres' => 400]); // ipe=40 => VERT_FONCE
        RegistreMesure::factory()->create(['camion_id' => $declenche->id, 'date' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'heures' => 10, 'litres' => 550]); // ipe=55 => ORANGE
        RegistreMesure::factory()->create(['camion_id' => $declenche->id, 'date' => now()->subMonthsNoOverflow(2)->startOfMonth()->toDateString(), 'heures' => 10, 'litres' => 700]); // ipe=70 => ROUGE

        // Même profil récent (vert), mais le mois précédent le mois vert est déjà vert lui aussi
        // (pas "difficile") => ne doit pas déclencher.
        $normal = $this->camionLh(['ser_norme_ref' => 50, 'ser_norme_ref_incertitude' => 0.25, 'ser_norme_ref_cible' => 44.1]);
        RegistreMesure::factory()->create(['camion_id' => $normal->id, 'date' => now()->startOfMonth()->toDateString(), 'heures' => 10, 'litres' => 400]); // VERT_FONCE
        RegistreMesure::factory()->create(['camion_id' => $normal->id, 'date' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'heures' => 10, 'litres' => 550]); // ORANGE
        RegistreMesure::factory()->create(['camion_id' => $normal->id, 'date' => now()->subMonthsNoOverflow(2)->startOfMonth()->toDateString(), 'heures' => 10, 'litres' => 400]); // VERT_FONCE, pas difficile

        $this->service()->detecterEtEnregistrer();

        $this->assertTrue($this->suggestionExiste($declenche->id, 'AMELIORATION_CONSTATEE'));
        $this->assertFalse($this->suggestionExiste($normal->id, 'AMELIORATION_CONSTATEE'));
    }

    public function test_amelioration_consommation_soutenue_se_declenche_a_partir_de_10_pourcent_de_baisse(): void
    {
        // 3 mois précédents à 40, 3 mois récents à 34 => baisse de 15% (>= seuil 10%).
        $declenche = Camion::factory()->create();
        foreach ([5, 4, 3] as $i) {
            Mesure::factory()->create(['camion_id' => $declenche->id, 'date' => now()->subMonthsNoOverflow($i)->startOfMonth()->toDateString(), 'consommation_l100' => 40]);
        }
        foreach ([2, 1, 0] as $i) {
            Mesure::factory()->create(['camion_id' => $declenche->id, 'date' => now()->subMonthsNoOverflow($i)->startOfMonth()->toDateString(), 'consommation_l100' => 34]);
        }

        // Même profil mais baisse de seulement 5% (40 -> 38) : sous le seuil, ne doit pas déclencher.
        $normal = Camion::factory()->create();
        foreach ([5, 4, 3] as $i) {
            Mesure::factory()->create(['camion_id' => $normal->id, 'date' => now()->subMonthsNoOverflow($i)->startOfMonth()->toDateString(), 'consommation_l100' => 40]);
        }
        foreach ([2, 1, 0] as $i) {
            Mesure::factory()->create(['camion_id' => $normal->id, 'date' => now()->subMonthsNoOverflow($i)->startOfMonth()->toDateString(), 'consommation_l100' => 38]);
        }

        $this->service()->detecterEtEnregistrer();

        $this->assertTrue($this->suggestionExiste($declenche->id, 'AMELIORATION_CONSOMMATION_SOUTENUE'));
        $this->assertFalse($this->suggestionExiste($normal->id, 'AMELIORATION_CONSOMMATION_SOUTENUE'));
    }

    public function test_amelioration_consommation_soutenue_exige_6_mois_calendaires_consecutifs(): void
    {
        // Même baisse de 15% que le cas positif ci-dessus, mais un trou d'un mois dans la fenêtre
        // (mois -4 absent) : ne doit pas déclencher malgré 6 lignes exploitables au total.
        $camion = Camion::factory()->create();
        foreach ([5, 3] as $i) {
            Mesure::factory()->create(['camion_id' => $camion->id, 'date' => now()->subMonthsNoOverflow($i)->startOfMonth()->toDateString(), 'consommation_l100' => 40]);
        }
        foreach ([2, 1, 0] as $i) {
            Mesure::factory()->create(['camion_id' => $camion->id, 'date' => now()->subMonthsNoOverflow($i)->startOfMonth()->toDateString(), 'consommation_l100' => 34]);
        }
        // Une 6e ligne exploitable, mais trop ancienne pour faire partie d'une fenêtre consécutive
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => now()->subMonthsNoOverflow(8)->startOfMonth()->toDateString(), 'consommation_l100' => 40]);

        $this->service()->detecterEtEnregistrer();

        $this->assertFalse($this->suggestionExiste($camion->id, 'AMELIORATION_CONSOMMATION_SOUTENUE'));
    }

    public function test_une_suggestion_active_non_redeclenchee_devient_resolue(): void
    {
        $camion = Camion::factory()->create(['usage_norme_ref' => null]);

        $this->service()->detecterEtEnregistrer();
        $suggestion = Suggestion::where('camion_id', $camion->id)->where('regle_declenchee', 'USAGE_NORME_REF_MANQUANT')->firstOrFail();
        $this->assertFalse($suggestion->resolue);
        $this->assertNull($suggestion->date_resolution);

        // La règle ne s'applique plus une fois usage_norme_ref renseigné
        $camion->usage_norme_ref = 'Transport — catégorie 2';
        $camion->save();
        $this->service()->detecterEtEnregistrer();

        $suggestion->refresh();
        $this->assertTrue($suggestion->resolue);
        $this->assertNotNull($suggestion->date_resolution);
    }
}
