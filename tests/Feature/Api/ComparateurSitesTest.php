<?php

namespace Tests\Feature\Api;

use App\Models\Camion;
use App\Models\Mesure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComparateurSitesTest extends TestCase
{
    use RefreshDatabase;

    public function test_retourne_200_avec_structure_de_base(): void
    {
        $camion = Camion::factory()->create(['groupe' => 'SITE A']);
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'consommation_l100' => 40]);

        $response = $this->getJson('/api/comparateur-sites');

        $response->assertOk()
            ->assertJsonStructure([
                '*' => ['mois', 'site', 'eco_score_moyen', 'conso_moyenne', 'nb_mesures'],
            ]);
    }

    // Ce test fige ce comportement pour de bon : une ligne est_partielle=true (mois en cours) ne
    // doit jamais apparaître dans la moyenne d'un site, ni même faire gonfler nb_mesures pour son
    // mois.
    public function test_exclut_les_mesures_est_partielle_de_la_moyenne_et_du_comptage(): void
    {
        $camion = Camion::factory()->create(['groupe' => 'SITE A']);
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'consommation_l100' => 40]); // mois clos
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-02-01', 'consommation_l100' => 999, 'est_partielle' => true]); // mois en cours, valeur volontairement aberrante

        $response = $this->getJson('/api/comparateur-sites');

        $lignes = collect($response->json());

        $response->assertOk();
        $this->assertNull($lignes->firstWhere('mois', '2026-02'), 'Le mois en cours (est_partielle=true) ne doit jamais apparaître dans le comparateur.');

        $ligneJanvier = $lignes->firstWhere('mois', '2026-01');
        $this->assertNotNull($ligneJanvier);
        $this->assertEquals(40.0, $ligneJanvier['conso_moyenne']);
        $this->assertSame(1, $ligneJanvier['nb_mesures']);
    }

    // Même principe que Mesure::estValeurExploitable() (0 = absence de donnée, > 100 = anomalie
    // capteur/API confirmée, cf. commentaire sur Mesure::CONSO_L100_MAX_PLAUSIBLE) : ces valeurs ne
    // doivent contribuer ni à eco_score_moyen ni à conso_moyenne, mais la ligne mois/site doit tout
    // de même exister si au moins une mesure exploitable est présente.
    public function test_exclut_les_valeurs_hors_bornes_de_la_moyenne_sans_faire_disparaitre_la_ligne(): void
    {
        // 3 camions distincts (contrainte unique camion_id+date) plutôt que 3 mesures pour un seul,
        // pour isoler l'effet du filtrage par valeur sans toucher à la contrainte d'unicité.
        $camionA = Camion::factory()->create(['groupe' => 'SITE B']);
        $camionB = Camion::factory()->create(['groupe' => 'SITE B']);
        $camionC = Camion::factory()->create(['groupe' => 'SITE B']);
        Mesure::factory()->create(['camion_id' => $camionA->id, 'date' => '2026-03-01', 'consommation_l100' => 30, 'ecoscore' => 6.0]);
        Mesure::factory()->create(['camion_id' => $camionB->id, 'date' => '2026-03-01', 'consommation_l100' => 0, 'ecoscore' => 0]); // absence de donnée
        Mesure::factory()->create(['camion_id' => $camionC->id, 'date' => '2026-03-01', 'consommation_l100' => 176.42, 'ecoscore' => 8.0]); // anomalie confirmée (> CONSO_L100_MAX_PLAUSIBLE)

        $response = $this->getJson('/api/comparateur-sites');

        $ligneMars = collect($response->json())->firstWhere('mois', '2026-03');

        $response->assertOk();
        $this->assertNotNull($ligneMars);
        $this->assertEquals(30.0, $ligneMars['conso_moyenne'], 'Les valeurs 0 et >100 (anomalie confirmée) doivent être exclues de la moyenne.');
        $this->assertEquals(7.0, $ligneMars['eco_score_moyen'], 'Moyenne de 6.0 et 8.0 uniquement, 0 exclu.');
        $this->assertSame(3, $ligneMars['nb_mesures'], 'nb_mesures compte toutes les lignes non-partielles, indépendamment de leur exploitabilité individuelle.');
    }
}
