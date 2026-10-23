<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gestion des utilisateurs — la seule vraie surface d'action de l'app, réservée aux Admins.
 */
class UtilisateursTest extends TestCase
{
    use RefreshDatabase;

    // TestCase::setUp() connecte déjà un Admin par défaut ("test-admin-defaut") — utilisé tel quel pour
    // les tests "Admin peut...".

    public function test_page_utilisateurs_accessible_a_l_admin(): void
    {
        $this->get('/utilisateurs')->assertOk()->assertSee('Gestion des utilisateurs');
    }

    public function test_admin_peut_lister_les_utilisateurs(): void
    {
        User::factory()->consultant()->create(['identifiant' => 'un-consultant']);

        $response = $this->getJson('/api/utilisateurs');

        $response->assertOk()->assertJsonFragment(['identifiant' => 'un-consultant']);
        // Jamais le mot de passe (même haché) exposé par l'API.
        $this->assertStringNotContainsString('mot_de_passe', $response->getContent());
    }

    public function test_admin_peut_creer_un_utilisateur(): void
    {
        $response = $this->postJson('/api/utilisateurs', [
            'identifiant' => 'nouveau-consultant',
            'mot_de_passe' => 'un-mot-de-passe-solide',
            'role' => User::ROLE_CONSULTANT,
        ]);

        $response->assertStatus(201)->assertJsonFragment(['identifiant' => 'nouveau-consultant', 'role' => 'consultant', 'actif' => true]);

        $this->assertDatabaseHas('users', ['identifiant' => 'nouveau-consultant', 'role' => 'consultant']);
        $cree = User::where('identifiant', 'nouveau-consultant')->first();
        $this->assertNotSame('un-mot-de-passe-solide', $cree->mot_de_passe); // haché, jamais en clair
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('un-mot-de-passe-solide', $cree->mot_de_passe));
    }

    public function test_creation_refuse_un_identifiant_deja_pris(): void
    {
        User::factory()->create(['identifiant' => 'deja-pris']);

        $this->postJson('/api/utilisateurs', [
            'identifiant' => 'deja-pris',
            'mot_de_passe' => 'un-mot-de-passe-solide',
            'role' => User::ROLE_CONSULTANT,
        ])->assertStatus(422)->assertJsonValidationErrors('identifiant');
    }

    public function test_creation_refuse_un_role_inconnu(): void
    {
        $this->postJson('/api/utilisateurs', [
            'identifiant' => 'x',
            'mot_de_passe' => 'un-mot-de-passe-solide',
            'role' => 'super-admin',
        ])->assertStatus(422)->assertJsonValidationErrors('role');
    }

    public function test_admin_peut_changer_le_role_et_desactiver_un_autre_compte(): void
    {
        $cible = User::factory()->consultant()->create();

        $this->patchJson("/api/utilisateurs/{$cible->id}", ['role' => User::ROLE_ADMIN])
            ->assertOk()->assertJsonFragment(['role' => 'admin']);
        $this->assertDatabaseHas('users', ['id' => $cible->id, 'role' => 'admin']);

        $this->patchJson("/api/utilisateurs/{$cible->id}", ['actif' => false])
            ->assertOk()->assertJsonFragment(['actif' => false]);
        $this->assertDatabaseHas('users', ['id' => $cible->id, 'actif' => false]);
    }

    /**
     * Isole un scénario "un seul admin actif" — TestCase::setUp() ("test-admin-defaut") ET la
     * migration de seed créent chacun un admin réel : il en existe donc déjà 2 au moment où un
     * test démarre, jamais 1 seul par défaut.
     */
    private function isolerUnSeulAdminActif(): User
    {
        User::where('role', User::ROLE_ADMIN)->update(['role' => User::ROLE_CONSULTANT]);
        $seulAdmin = User::factory()->admin()->create();
        $this->actingAs($seulAdmin);

        return $seulAdmin;
    }

    public function test_impossible_de_desactiver_le_dernier_admin_actif(): void
    {
        $seulAdmin = $this->isolerUnSeulAdminActif();

        $response = $this->patchJson("/api/utilisateurs/{$seulAdmin->id}", ['actif' => false]);

        $response->assertStatus(422)->assertJsonFragment(['message' => "Impossible : {$seulAdmin->identifiant} est le dernier administrateur actif."]);
        $this->assertDatabaseHas('users', ['id' => $seulAdmin->id, 'actif' => true]);
    }

    public function test_impossible_de_retrograder_le_dernier_admin_actif(): void
    {
        $seulAdmin = $this->isolerUnSeulAdminActif();

        $this->patchJson("/api/utilisateurs/{$seulAdmin->id}", ['role' => User::ROLE_CONSULTANT])
            ->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $seulAdmin->id, 'role' => 'admin']);
    }

    public function test_desactiver_un_admin_reste_possible_si_un_autre_admin_actif_existe(): void
    {
        $premierAdmin = $this->isolerUnSeulAdminActif();
        $secondAdmin = User::factory()->admin()->create();

        $this->patchJson("/api/utilisateurs/{$premierAdmin->id}", ['actif' => false])->assertOk();
        $this->assertDatabaseHas('users', ['id' => $premierAdmin->id, 'actif' => false]);
        // Le second reste inchangé, toujours admin actif.
        $this->assertDatabaseHas('users', ['id' => $secondAdmin->id, 'role' => 'admin', 'actif' => true]);
    }

    // --- Consultant : aucun accès, ni page, ni API — forcé directement, pas juste masqué côté front ---

    public function test_consultant_recoit_403_sur_la_page_utilisateurs(): void
    {
        $this->actingAs(User::factory()->consultant()->create());

        $this->get('/utilisateurs')->assertStatus(403);
    }

    public function test_consultant_recoit_403_sur_get_api_utilisateurs(): void
    {
        $this->actingAs(User::factory()->consultant()->create());

        $this->getJson('/api/utilisateurs')
            ->assertStatus(403)
            ->assertExactJson(['message' => 'Accès réservé aux administrateurs.']);
    }

    public function test_consultant_recoit_403_sur_post_api_utilisateurs(): void
    {
        $this->actingAs(User::factory()->consultant()->create());

        $this->postJson('/api/utilisateurs', [
            'identifiant' => 'tentative-consultant',
            'mot_de_passe' => 'un-mot-de-passe-solide',
            'role' => User::ROLE_ADMIN,
        ])->assertStatus(403);

        $this->assertDatabaseMissing('users', ['identifiant' => 'tentative-consultant']);
    }

    public function test_consultant_recoit_403_sur_patch_api_utilisateurs_et_ne_modifie_rien(): void
    {
        $consultant = User::factory()->consultant()->create();
        $this->actingAs($consultant);

        // Tente de s'auto-promouvoir Admin directement via l'API — doit échouer sans effet.
        $this->patchJson("/api/utilisateurs/{$consultant->id}", ['role' => User::ROLE_ADMIN])
            ->assertStatus(403);

        $this->assertDatabaseHas('users', ['id' => $consultant->id, 'role' => 'consultant']);
    }

    public function test_consultant_ne_voit_pas_le_lien_utilisateurs_mais_admin_oui(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $this->get('/dashboard')->assertOk()->assertSee('/utilisateurs', false);

        $consultant = User::factory()->consultant()->create();
        $this->actingAs($consultant);
        $this->get('/dashboard')->assertOk()->assertDontSee('/utilisateurs', false);
    }

    public function test_anonyme_recoit_401_pas_403_sur_les_routes_utilisateurs(): void
    {
        \Illuminate\Support\Facades\Auth::logout();
        $this->app['session']->flush();

        $this->getJson('/api/utilisateurs')->assertStatus(401);
        $this->get('/utilisateurs')->assertRedirect('/connexion?retour='.urlencode('/utilisateurs'));
    }
}
