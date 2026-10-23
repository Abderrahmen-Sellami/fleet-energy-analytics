<?php

namespace Tests\Unit;

use App\Models\Alerte;
use App\Models\Camion;
use App\Models\Mesure;
use App\Models\RegistreMesure;
use App\Services\AlerteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Une classe de test par règle de détection d'AlerteService : chaque test crée un camion qui
 * déclenche la règle et un second qui ne la déclenche pas, puis vérifie l'alerte correspondante
 * (par camion_id + type) — jamais de dépendance aux vraies données.
 */
class AlerteServiceTest extends TestCase
{
    use RefreshDatabase;

    private function alerteExiste(int $camionId, string $type): bool
    {
        return Alerte::where('camion_id', $camionId)->where('type', $type)->where('resolue', false)->exists();
    }

    public function test_eco_score_faible_se_declenche_sous_7_et_pas_au_dessus(): void
    {
        $declenche = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $declenche->id, 'date' => '2026-01-01', 'consommation_l100' => 40, 'ecoscore' => 5]);

        $normal = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $normal->id, 'date' => '2026-01-01', 'consommation_l100' => 40, 'ecoscore' => 8]);

        (new AlerteService())->detecterEtEnregistrer();

        $this->assertTrue($this->alerteExiste($declenche->id, 'eco_score_faible'));
        $this->assertFalse($this->alerteExiste($normal->id, 'eco_score_faible'));
    }

    public function test_conso_elevee_se_declenche_au_dessus_de_45_et_pas_en_dessous(): void
    {
        $declenche = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $declenche->id, 'date' => '2026-01-01', 'consommation_l100' => 50]);

        $normal = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $normal->id, 'date' => '2026-01-01', 'consommation_l100' => 40]);

        (new AlerteService())->detecterEtEnregistrer();

        $this->assertTrue($this->alerteExiste($declenche->id, 'conso_elevee'));
        $this->assertFalse($this->alerteExiste($normal->id, 'conso_elevee'));
    }

    public function test_ipe_rouge_se_declenche_au_dessus_de_ser_fois_incertitude(): void
    {
        // Usage en L/h (toupie) : ser_norme_ref=45 (défaut factory), incertitude=0.25 => ser1x=56.25
        $declenche = Camion::factory()->create(['usage_norme_ref' => 'Engin — catégorie 3']);
        RegistreMesure::factory()->create(['camion_id' => $declenche->id, 'date' => '2026-01-01', 'heures' => 10, 'litres' => 700]); // ipe=70

        $normal = Camion::factory()->create(['usage_norme_ref' => 'Engin — catégorie 3']);
        RegistreMesure::factory()->create(['camion_id' => $normal->id, 'date' => '2026-01-01', 'heures' => 10, 'litres' => 400]); // ipe=40

        (new AlerteService())->detecterEtEnregistrer();

        $this->assertTrue($this->alerteExiste($declenche->id, 'ipe_rouge'));
        $this->assertFalse($this->alerteExiste($normal->id, 'ipe_rouge'));
    }

    // l'alerte utilise NormeRefStatutService (même unité, même garde-fou que le statut affiché)
    public function test_ipe_rouge_transport_se_base_sur_le_l100km(): void
    {
        // ser=45, incertitude=0.25 => ser1x=56.25
        $declenche = Camion::factory()->create(['usage_norme_ref' => 'Transport — catégorie 1']);
        RegistreMesure::factory()->create(['camion_id' => $declenche->id, 'date' => '2026-01-01', 'km' => 1000, 'litres' => 700, 'heures' => null]); // 70 L/100km

        $normal = Camion::factory()->create(['usage_norme_ref' => 'Transport — catégorie 2']);
        RegistreMesure::factory()->create(['camion_id' => $normal->id, 'date' => '2026-01-01', 'km' => 1000, 'litres' => 400, 'heures' => null]); // 40 L/100km

        (new AlerteService())->detecterEtEnregistrer();

        $this->assertTrue($this->alerteExiste($declenche->id, 'ipe_rouge'));
        $this->assertFalse($this->alerteExiste($normal->id, 'ipe_rouge'));
        $this->assertStringContainsString('L/100km', Alerte::where('camion_id', $declenche->id)->where('type', 'ipe_rouge')->value('message'));
    }

    // Un usage en L/h sans heures ne doit RIEN déclencher (statut « unité incompatible »)
    public function test_ipe_rouge_ne_compare_jamais_un_l100km_a_une_ser_en_lh(): void
    {
        $camion = Camion::factory()->create(['usage_norme_ref' => 'Engin — catégorie 3']);
        RegistreMesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'km' => 1000, 'litres' => null, 'heures' => null, 'pe_l100' => 70]);

        (new AlerteService())->detecterEtEnregistrer();

        $this->assertFalse($this->alerteExiste($camion->id, 'ipe_rouge'));
    }

    public function test_ecart_registre_source_a_se_declenche_au_dela_de_25_pourcent(): void
    {
        $declenche = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $declenche->id, 'date' => '2026-01-01', 'consommation_l100' => 60]);
        RegistreMesure::factory()->create(['camion_id' => $declenche->id, 'date' => '2026-01-01', 'km' => 1000, 'litres' => 300]); // consoSolu=30, écart 100%

        $normal = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $normal->id, 'date' => '2026-01-01', 'consommation_l100' => 40]);
        RegistreMesure::factory()->create(['camion_id' => $normal->id, 'date' => '2026-01-01', 'km' => 1000, 'litres' => 380]); // consoSolu=38, écart ~5.3%

        (new AlerteService())->detecterEtEnregistrer();

        $this->assertTrue($this->alerteExiste($declenche->id, 'ecart_registre_source_a'));
        $this->assertFalse($this->alerteExiste($normal->id, 'ecart_registre_source_a'));
    }

    public function test_donnees_manquantes_prolongees_se_declenche_sans_mesure_recente(): void
    {
        // Actif depuis largement plus de 3 mois, aucune mesure du tout => derniereDate null => déclenche
        $declenche = Camion::factory()->create(['source_a_actif_depuis' => now()->subYear()->toDateString()]);

        // Actif depuis longtemps mais mesure exploitable très récente => ne déclenche pas
        $normal = Camion::factory()->create(['source_a_actif_depuis' => now()->subYear()->toDateString()]);
        Mesure::factory()->create(['camion_id' => $normal->id, 'date' => now()->startOfMonth()->toDateString(), 'consommation_l100' => 40]);

        (new AlerteService())->detecterEtEnregistrer();

        $this->assertTrue($this->alerteExiste($declenche->id, 'donnees_manquantes_prolongees'));
        $this->assertFalse($this->alerteExiste($normal->id, 'donnees_manquantes_prolongees'));
    }

    public function test_usage_norme_ref_manquant_se_declenche_quand_vide(): void
    {
        $declenche = Camion::factory()->create(['usage_norme_ref' => null]);
        $normal = Camion::factory()->create(['usage_norme_ref' => 'Transport — catégorie 2']);

        (new AlerteService())->detecterEtEnregistrer();

        $this->assertTrue($this->alerteExiste($declenche->id, 'usage_norme_ref_manquant'));
        $this->assertFalse($this->alerteExiste($normal->id, 'usage_norme_ref_manquant'));
    }

    public function test_une_alerte_active_non_redeclenchee_devient_resolue(): void
    {
        $camion = Camion::factory()->create();
        $mesure = Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'consommation_l100' => 50]);

        (new AlerteService())->detecterEtEnregistrer();
        $alerte = Alerte::where('camion_id', $camion->id)->where('type', 'conso_elevee')->firstOrFail();
        $this->assertFalse($alerte->resolue);
        $this->assertNull($alerte->date_resolution);

        // La consommation repasse sous le seuil : la condition n'est plus vraie au tour suivant
        $mesure->update(['consommation_l100' => 30]);
        (new AlerteService())->detecterEtEnregistrer();

        $alerte->refresh();
        $this->assertTrue($alerte->resolue);
        $this->assertNotNull($alerte->date_resolution);
    }
}
