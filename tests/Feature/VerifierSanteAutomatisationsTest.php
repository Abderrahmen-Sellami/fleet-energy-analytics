<?php

namespace Tests\Feature;

use App\Models\AutomatisationSante;
use App\Services\SanteAutomatisationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class VerifierSanteAutomatisationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_journalise_un_warning_par_tache_en_retard(): void
    {
        Log::spy();

        $this->artisan('automatisation:verifier-sante')->assertExitCode(0);

        // Aucune tâche n'a encore de succès enregistré dans cette base de test fraîche : les 3
        // tâches critiques doivent toutes déclencher un WARNING.
        Log::shouldHaveReceived('warning')->times(3);
    }

    public function test_aucun_warning_quand_tout_est_a_jour(): void
    {
        foreach (array_keys(SanteAutomatisationsService::FREQUENCES_MINUTES) as $tache) {
            AutomatisationSante::create(['tache' => $tache, 'derniere_execution_reussie' => now()]);
        }

        Log::spy();

        $this->artisan('automatisation:verifier-sante')->assertExitCode(0);

        Log::shouldNotHaveReceived('warning');
    }
}
