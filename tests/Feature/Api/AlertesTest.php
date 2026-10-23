<?php

namespace Tests\Feature\Api;

use App\Models\AnalyseCorrective;
use App\Models\Alerte;
use App\Models\Camion;
use App\Models\Mesure;
use App\Services\AlerteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertesTest extends TestCase
{
    use RefreshDatabase;

    public function test_retourne_200_et_tableau_vide_sans_anomalie(): void
    {
        $camion = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'consommation_l100' => 30, 'ecoscore' => 8]);

        $this->artisan('alertes:detecter');

        $response = $this->getJson('/api/alertes');

        $response->assertOk()->assertJson([]);
    }

    public function test_retourne_une_alerte_avec_la_structure_attendue_sur_conso_elevee(): void
    {
        $camion = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'consommation_l100' => 60]);

        $this->artisan('alertes:detecter');

        $response = $this->getJson('/api/alertes');

        $response->assertOk()->assertJsonStructure([
            '*' => ['id', 'camion', 'groupe', 'type', 'severite', 'message', 'valeur', 'seuil', 'resolue', 'date_detection', 'date_resolution', 'jours_actifs', 'analyses'],
        ]);
        $this->assertSame($camion->immatriculation, $response->json('0.camion'));
        $this->assertSame('conso_elevee', $response->json('0.type'));
    }

    // Abandonné : le rattachement se fait maintenant uniquement par camion_id, indépendamment du
    // mois de l'alerte ou de son état.
    public function test_toutes_les_analyses_du_camion_apparaissent_quel_que_soit_leur_mois(): void
    {
        $camion = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'consommation_l100' => 60]); // déclenche conso_elevee, alerte détectée en janvier 2026

        // Analyse d'un mois totalement différent de celui de l'alerte — ne doit plus être ignorée.
        AnalyseCorrective::create([
            'camion_id' => $camion->id,
            'mois' => '2025-09-01',
            'cause' => 'Cause de test',
            'action' => 'Action de test',
            'statut_suivi' => 'RAS',
        ]);

        $this->artisan('alertes:detecter');

        $response = $this->getJson('/api/alertes');

        $response->assertOk();
        $this->assertCount(1, $response->json('0.analyses'));
        $this->assertSame('2025-09', $response->json('0.analyses.0.mois'));
        $this->assertSame('Cause de test', $response->json('0.analyses.0.cause'));
    }

    // Ici, une condition qui déclencherait normalement conso_elevee existe en base, mais
    // alertes:detecter n'a jamais été exécutée : la réponse doit rester vide et aucune ligne ne
    // doit apparaître dans `alertes`.
    public function test_ne_declenche_plus_la_detection_lui_meme(): void
    {
        $camion = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'consommation_l100' => 60]);

        $response = $this->getJson('/api/alertes');

        $response->assertOk()->assertJson([]);
        $this->assertSame(0, Alerte::count());
    }

    // Filtre « Type d'alerte » du tableau de l'Accueil : la liste vient du backend, source unique
    // AlerteService::TYPES — jamais codée côté écran.
    public function test_types_d_alertes_possibles_viennent_du_backend(): void
    {
        $response = $this->getJson('/api/alertes/types')->assertOk();

        $response->assertJsonStructure(['*' => ['type', 'libelle']]);
        $this->assertSame(array_keys(AlerteService::TYPES), array_column($response->json(), 'type'));
        $this->assertSame(array_values(AlerteService::TYPES), array_column($response->json(), 'libelle'));
        $this->assertCount(6, $response->json());
        // Liste des types POSSIBLES : renvoyée même quand aucune alerte n'existe en base.
        $this->assertSame(0, Alerte::count());
    }

    // Toute alerte réellement produite par la détection a un type de la liste (sinon le filtre ne pourrait pas la
    // retrouver) : conso élevée + mesure ancienne (données manquantes prolongées) sur un camion actif depuis longtemps.
    public function test_les_types_produits_par_la_detection_sont_dans_la_liste(): void
    {
        $camion = Camion::factory()->create(['source_a_actif_depuis' => '2025-01-01']);
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'consommation_l100' => 60]);

        $this->artisan('alertes:detecter');

        $types = Alerte::pluck('type')->unique()->all();
        $this->assertContains('conso_elevee', $types);
        $this->assertContains('donnees_manquantes_prolongees', $types);
        foreach ($types as $type) {
            $this->assertArrayHasKey($type, AlerteService::TYPES);
        }
    }

    // Garde-fou : un nouveau type ajouté à la détection sans passer par AlerteService::TYPES échoue bruyamment.
    public function test_un_type_absent_de_la_liste_est_refuse_par_la_detection(): void
    {
        $camion = Camion::factory()->create();
        $verifier = new \ReflectionMethod(AlerteService::class, 'verifier');
        $verifier->setAccessible(true);
        $actives = [];

        $this->expectException(\LogicException::class);
        $verifier->invokeArgs(new AlerteService(), [$camion->id, 'type_inconnu', true, 1.0, 1.0, 'warning', 'x', &$actives]);
    }
}
