<?php

namespace Tests\Feature\Api;

use App\Models\Camion;
use App\Models\Mesure;
use App\Models\RegistreMesure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use League\Csv\Reader;
use Tests\TestCase;

class ExportCsvTest extends TestCase
{
    use RefreshDatabase;

    private const BOM_UTF8 = "\xEF\xBB\xBF";

    public function test_export_fiche_camion_retourne_un_csv_bom_utf8_exploitable_par_league_csv(): void
    {
        $camion = Camion::factory()->create(['immatriculation' => 'DEMO-102']);
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'consommation_l100' => 40, 'km_parcourus' => 1000, 'heures_moteur' => 50, 'ecoscore' => 8]);
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-02-01', 'consommation_l100' => 42, 'km_parcourus' => 1200, 'heures_moteur' => 60, 'ecoscore' => 7.5]);
        // Mois en cours (est_partielle) : ne doit jamais apparaître dans un export de données brutes,
        // même règle que mesuresFiltrees() côté frontend (cf. ExportCsvController::ficheCamion).
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-03-01', 'consommation_l100' => 41, 'est_partielle' => true]);

        $response = $this->get("/api/export/fiche-camion/{$camion->id}");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename=', $response->headers->get('Content-Disposition'));

        $contenu = $response->getContent();
        $this->assertStringStartsWith(self::BOM_UTF8, $contenu);

        $csv = Reader::createFromString($contenu);
        $csv->setDelimiter(';');
        $csv->setHeaderOffset(0);
        $lignes = iterator_to_array($csv->getRecords());

        $this->assertCount(2, $lignes); // les 2 mesures exploitables, pas le mois en cours
        $this->assertSame(['Mois', 'Conso (L/100km)', 'L/Heure', 'Km parcourus', 'Heures moteur', 'Eco Score'], $csv->getHeader());
        $premiere = $lignes[array_key_first($lignes)];
        $this->assertSame('2026-01', $premiere['Mois']);
        $this->assertSame('40,0', $premiere['Conso (L/100km)']); // décimale française (virgule)
    }

    public function test_export_fiche_camion_id_non_numerique_renvoie_404_json_propre(): void
    {
        $response = $this->getJson('/api/export/fiche-camion/abc');

        $response->assertStatus(404)->assertJsonFragment(['message' => 'Route ou identifiant invalide.']);
    }

    public function test_export_courbes_exporte_les_metriques_selectionnees_telles_quelles(): void
    {
        $camion = Camion::factory()->create(['immatriculation' => 'DEMO-103']);
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'consommation_l100' => 40]);
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-02-01', 'consommation_l100' => 42]);
        RegistreMesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'pe_l100' => 41]);

        $response = $this->get("/api/export/courbes/{$camion->id}?metriques=consommation_l100,pe_l100&mode=mensuel");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = Reader::createFromString($response->getContent());
        $csv->setDelimiter(';');
        $csv->setHeaderOffset(0);

        // Depuis le renommage (plus de mention de source dans le libellé lui-même, cf.
        // CourbesController::LABELS), consommation_l100 (Télématique A) et pe_l100.
        $this->assertSame(
            ['Mois', 'Consommation (L/100km) — Télématique A', 'Consommation (L/100km) — Registre', 'Mois en cours (provisoire)'],
            $csv->getHeader()
        );

        $lignes = iterator_to_array($csv->getRecords());
        $this->assertCount(2, $lignes); // union des périodes janvier/février

        $janvier = $lignes[array_key_first($lignes)];
        $this->assertSame('2026-01', $janvier['Mois']);
        $this->assertSame('40,0', $janvier['Consommation (L/100km) — Télématique A']);
        $this->assertSame('41,0', $janvier['Consommation (L/100km) — Registre']);
    }

    public function test_export_courbes_metrique_inconnue_renvoie_le_meme_422_que_lendpoint_courbes(): void
    {
        $camion = Camion::factory()->create();

        $response = $this->getJson("/api/export/courbes/{$camion->id}?metriques=champ_bidon&mode=mensuel");

        $response->assertStatus(422)->assertJsonFragment(['message' => 'Métrique inconnue : champ_bidon']);
    }

    public function test_export_courbes_sans_parametre_metriques_renvoie_422(): void
    {
        $camion = Camion::factory()->create();

        $response = $this->getJson("/api/export/courbes/{$camion->id}");

        $response->assertStatus(422);
    }

    public function test_export_courbes_mode_annuel(): void
    {
        $camion = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'ecoscore' => 8]);
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-02-01', 'ecoscore' => 9]);

        $response = $this->get("/api/export/courbes/{$camion->id}?metriques=ecoscore&mode=annuel");

        $response->assertOk();
        $csv = Reader::createFromString($response->getContent());
        $csv->setDelimiter(';');
        $csv->setHeaderOffset(0);

        $this->assertSame(['Année', 'Eco Score (/10)'], $csv->getHeader());
        $lignes = iterator_to_array($csv->getRecords());
        $this->assertCount(1, $lignes);
        $this->assertSame('2026', $lignes[array_key_first($lignes)]['Année']);
        $this->assertSame('8,5', $lignes[array_key_first($lignes)]['Eco Score (/10)']); // (8+9)/2
    }
}
