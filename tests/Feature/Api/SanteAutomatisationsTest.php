<?php

namespace Tests\Feature\Api;

use App\Models\AutomatisationSante;
use App\Services\SanteAutomatisationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// La marge de tolérance est ×2 la fréquence attendue — testée ici sur backup:run (quotidienne, donc en
// retard au-delà de 2 880 min).
class SanteAutomatisationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_aucune_tache_jamais_executee_sont_toutes_signalees_en_retard(): void
    {
        $response = $this->getJson('/api/sante-automatisations');

        $response->assertOk();
        $this->assertTrue($response->json('au_moins_une_en_retard'));
        $this->assertCount(3, $response->json('taches'));
        foreach ($response->json('taches') as $tache) {
            $this->assertTrue($tache['en_retard']);
            $this->assertNull($tache['derniere_execution_reussie']);
        }
    }

    public function test_une_tache_avec_un_succes_recent_nest_pas_en_retard(): void
    {
        foreach (array_keys(SanteAutomatisationsService::FREQUENCES_MINUTES) as $tache) {
            AutomatisationSante::create(['tache' => $tache, 'derniere_execution_reussie' => now()]);
        }

        $response = $this->getJson('/api/sante-automatisations');

        $response->assertOk();
        $this->assertFalse($response->json('au_moins_une_en_retard'));
        foreach ($response->json('taches') as $tache) {
            $this->assertFalse($tache['en_retard']);
        }
    }

    public function test_une_seule_tache_en_retard_est_isolee_des_autres(): void
    {
        foreach (array_keys(SanteAutomatisationsService::FREQUENCES_MINUTES) as $tache) {
            AutomatisationSante::create(['tache' => $tache, 'derniere_execution_reussie' => now()]);
        }
        // backup:run : seuil de retard à 2 880 min — 3 000 min dans le passé le dépasse.
        AutomatisationSante::where('tache', 'backup:run')
            ->update(['derniere_execution_reussie' => now()->subMinutes(3000)]);

        $response = $this->getJson('/api/sante-automatisations');

        $response->assertOk();
        $this->assertTrue($response->json('au_moins_une_en_retard'));
        $taches = collect($response->json('taches'))->keyBy('tache');
        $this->assertTrue($taches['backup:run']['en_retard']);
        foreach ($taches->except('backup:run') as $tache) {
            $this->assertFalse($tache['en_retard']);
        }
    }

    public function test_un_succes_juste_sous_le_seuil_de_tolerance_nest_pas_encore_en_retard(): void
    {
        // 2 879 min < seuil de 2 880 min (1 440 min × 2) : pas encore en retard.
        AutomatisationSante::create([
            'tache' => 'backup:run',
            'derniere_execution_reussie' => now()->subMinutes(2879),
        ]);

        $response = $this->getJson('/api/sante-automatisations');

        $taches = collect($response->json('taches'))->keyBy('tache');
        $this->assertFalse($taches['backup:run']['en_retard']);
    }

    public function test_service_enregistrer_succes_fait_un_upsert_par_tache(): void
    {
        $service = app(SanteAutomatisationsService::class);

        $service->enregistrerSucces('meteo:archiver-jour');
        $this->assertSame(1, AutomatisationSante::where('tache', 'meteo:archiver-jour')->count());

        $service->enregistrerSucces('meteo:archiver-jour');
        $this->assertSame(1, AutomatisationSante::where('tache', 'meteo:archiver-jour')->count());
    }
}
