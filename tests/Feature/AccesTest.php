<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * RefreshDatabase (contrairement à l'ancienne version de ce fichier) : ces tests créent des
 * utilisateurs réels avec des identifiants/mots de passe contrôlés, une base propre à chaque test
 * évite toute interférence entre eux (unicité d'identifiant, comptage d'admins...)
 */
class AccesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Repart d'un état déconnecté (TestCase::setUp() connecte un Admin par défaut).
        Auth::logout();
        $this->app['session']->flush();
    }

    private function creerAdmin(array $surcharge = []): User
    {
        return User::factory()->admin()->create($surcharge);
    }

    private function seConnecter(User $user, array $surcharge = [])
    {
        return $this->post('/connexion', array_merge([
            'identifiant' => $user->identifiant,
            'mot_de_passe' => UserFactory::MOT_DE_PASSE_CLAIR,
        ], $surcharge));
    }

    public function test_pages_protegees_redirigent_vers_connexion_sans_session(): void
    {
        foreach (['/dashboard', '/direction', '/audit-iso50001'] as $url) {
            $this->get($url)->assertRedirect('/connexion?retour='.urlencode($url));
        }
    }

    public function test_api_renvoie_401_json_sans_session(): void
    {
        foreach (['/api/camions', '/api/flotte/resume', '/api/alertes', '/api/sante-automatisations'] as $url) {
            $this->getJson($url)->assertStatus(401)->assertExactJson(['message' => 'Authentification requise.']);
        }
        // Sans en-tête Accept: application/json non plus (le frontend fait des fetch() nus).
        $this->get('/api/camions')->assertStatus(401);
    }

    public function test_ecran_de_connexion_accessible_sans_session(): void
    {
        $this->get('/connexion')->assertOk()->assertSee('Se connecter');
    }

    public function test_connexion_valide_ouvre_les_pages_et_l_api(): void
    {
        $admin = $this->creerAdmin();

        $this->seConnecter($admin)->assertRedirect('/dashboard');

        $this->get('/dashboard')->assertOk();
        $this->get('/direction')->assertOk();
        $this->getJson('/api/camions')->assertOk();
    }

    public function test_connexion_redirige_vers_la_page_demandee(): void
    {
        $admin = $this->creerAdmin();

        $this->seConnecter($admin, ['retour' => '/direction'])->assertRedirect('/direction');
    }

    public function test_retour_hors_whitelist_est_ignore(): void
    {
        $admin = $this->creerAdmin();

        foreach (['https://evil.example/', '//evil.example', '/api/camions', '/dashboard/../x'] as $retour) {
            Auth::logout();
            $this->app['session']->flush();
            $this->seConnecter($admin, ['retour' => $retour])->assertRedirect('/dashboard');
        }
    }

    public function test_mauvais_mot_de_passe_ou_identifiant_est_refuse(): void
    {
        $admin = $this->creerAdmin(['identifiant' => 'admin-connu']);

        $this->seConnecter($admin, ['mot_de_passe' => 'faux'])
            ->assertRedirect('/connexion')
            ->assertSessionHasErrors('connexion');
        $this->post('/connexion', ['identifiant' => 'identifiant-inconnu', 'mot_de_passe' => UserFactory::MOT_DE_PASSE_CLAIR])
            ->assertSessionHasErrors('connexion');

        $this->getJson('/api/camions')->assertStatus(401);
    }

    public function test_champs_vides_ou_absents_sont_refuses(): void
    {
        $this->post('/connexion', [])->assertSessionHasErrors('connexion');
        $this->post('/connexion', ['identifiant' => '', 'mot_de_passe' => ''])->assertSessionHasErrors('connexion');
    }

    public function test_compte_inactif_est_refuse_a_la_connexion(): void
    {
        $inactif = User::factory()->admin()->inactif()->create();

        $this->seConnecter($inactif)
            ->assertRedirect('/connexion')
            ->assertSessionHasErrors('connexion');

        $this->getJson('/api/camions')->assertStatus(401);
    }

    public function test_desactiver_un_compte_deja_connecte_coupe_l_acces_immediatement(): void
    {
        $admin = $this->creerAdmin();
        $this->seConnecter($admin);
        $this->getJson('/api/camions')->assertOk();

        // Compte désactivé pendant que la session reste ouverte : l'accès est coupé dès la requête suivante (Authentifie::handle()).
        $admin->update(['actif' => false]);
        // forgetGuards() : en production, chaque requête HTTP reboote l'application (le guard n'est
        // JAMAIS mémorisé entre deux requêtes séparées) — dans ce test, le conteneur reste le même
        // entre les deux appels this->getJson(), donc SessionGuard garderait sinon en mémoire
        // l'instance $admin déjà résolue (actif=true) au lieu de la relire en base. Simule ici la
        // vraie situation d'une requête HTTP suivante distincte.
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/camions')->assertStatus(401);
        $this->get('/dashboard')->assertRedirect();
    }

    public function test_limitation_des_tentatives_par_ip(): void
    {
        $admin = $this->creerAdmin();

        for ($i = 0; $i < 5; $i++) {
            $this->seConnecter($admin, ['mot_de_passe' => 'faux'.$i]);
        }

        // 6e tentative : même avec le BON mot de passe, bloquée dans la fenêtre.
        $this->seConnecter($admin)->assertSessionHasErrors('connexion');
        $this->getJson('/api/camions')->assertStatus(401);
    }

    public function test_deconnexion_ferme_l_acces(): void
    {
        $admin = $this->creerAdmin();
        $this->seConnecter($admin);
        $this->getJson('/api/camions')->assertOk();

        $this->post('/deconnexion')->assertRedirect('/connexion');

        $this->getJson('/api/camions')->assertStatus(401);
        $this->get('/dashboard')->assertRedirect();
    }

    public function test_connexion_deja_active_redirige_depuis_l_ecran_de_connexion(): void
    {
        $admin = $this->creerAdmin();
        $this->seConnecter($admin);
        $this->get('/connexion')->assertRedirect('/dashboard');
    }

    public function test_consultant_a_un_acces_en_lecture_identique_a_l_admin(): void
    {
        $consultant = User::factory()->consultant()->create();

        $this->seConnecter($consultant)->assertRedirect('/dashboard');

        $this->get('/dashboard')->assertOk();
        $this->get('/direction')->assertOk();
        $this->getJson('/api/camions')->assertOk();
        $this->getJson('/api/flotte/resume')->assertOk();
    }
}
