<?php

namespace Tests\Feature\Api;

use App\Models\Camion;
use App\Models\Suggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SuggestionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // WeatherService (App\Services\WeatherService) appelle openweathermap.org à chaque
        // exécution de SuggestionService::genererSuggestions() (règle CANICULE_IPE_HAUSSE), y
        // compris via la commande suggestions:detecter — jamais de vrai appel réseau dans un test
        // Feature. Cache::flush() évite qu'un résultat mis en cache par un test précédent (store
        // 'array', persistant sur tout le process phpunit) court-circuite le Http::fake() de ce test.
        Cache::flush();
        Http::fake(['api.openweathermap.org/*' => Http::response(['cod' => 500], 500)]);
    }

    public function test_retourne_200_et_tableau_vide_sans_regle_declenchee(): void
    {
        Camion::factory()->create(['usage_norme_ref' => 'Transport — catégorie 2']);

        $this->artisan('suggestions:detecter');

        $response = $this->getJson('/api/suggestions');

        $response->assertOk()->assertJson([]);
    }

    public function test_retourne_une_suggestion_avec_la_structure_attendue(): void
    {
        $camion = Camion::factory()->create(['usage_norme_ref' => null]);

        $this->artisan('suggestions:detecter');

        $response = $this->getJson('/api/suggestions');

        $response->assertOk()->assertJsonStructure([
            '*' => ['id', 'camion', 'regle_declenchee', 'message', 'gravite', 'valeurs', 'date_detection', 'resolue', 'date_resolution'],
        ]);
        $this->assertSame($camion->immatriculation, $response->json('0.camion'));
        $this->assertSame('USAGE_NORME_REF_MANQUANT', $response->json('0.regle_declenchee'));
    }

    // Ici, une condition qui déclencherait normalement USAGE_NORME_REF_MANQUANT existe en base,
    // mais suggestions:detecter n'a jamais été exécutée : la réponse doit rester vide et aucune
    // ligne ne doit apparaître dans `suggestions`.
    public function test_ne_declenche_plus_la_detection_lui_meme(): void
    {
        Camion::factory()->create(['usage_norme_ref' => null]);

        $response = $this->getJson('/api/suggestions');

        $response->assertOk()->assertJson([]);
        $this->assertSame(0, Suggestion::count());
    }
}
