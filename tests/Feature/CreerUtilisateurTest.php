<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class CreerUtilisateurTest extends TestCase
{
    use RefreshDatabase;

    public function test_cree_un_admin_dont_le_mot_de_passe_permet_de_se_connecter(): void
    {
        $this->artisan('utilisateur:creer', ['identifiant' => 'premier-admin', '--mot-de-passe' => 'mdp-assez-long'])
            ->assertExitCode(0);

        $user = User::where('identifiant', 'premier-admin')->sole();
        $this->assertSame(User::ROLE_ADMIN, $user->role);
        $this->assertTrue($user->actif);
        $this->assertNotSame('mdp-assez-long', $user->mot_de_passe);
        $this->assertTrue(Auth::validate(['identifiant' => 'premier-admin', 'password' => 'mdp-assez-long', 'actif' => true]));
    }

    public function test_refuse_un_role_inconnu_un_identifiant_pris_et_un_mot_de_passe_court(): void
    {
        $avant = User::count();

        $this->artisan('utilisateur:creer', ['identifiant' => 'x', '--role' => 'superadmin', '--mot-de-passe' => 'mdp-assez-long'])
            ->assertExitCode(1);
        $this->artisan('utilisateur:creer', ['identifiant' => 'court', '--mot-de-passe' => '1234567'])
            ->assertExitCode(1);

        $this->artisan('utilisateur:creer', ['identifiant' => 'double', '--mot-de-passe' => 'mdp-assez-long'])->assertExitCode(0);
        $this->artisan('utilisateur:creer', ['identifiant' => 'double', '--mot-de-passe' => 'autre-mdp-long'])->assertExitCode(1);

        $this->assertSame($avant + 1, User::count());
    }

    public function test_cree_un_consultant(): void
    {
        $this->artisan('utilisateur:creer', ['identifiant' => 'lecteur', '--role' => 'consultant', '--mot-de-passe' => 'mdp-assez-long'])
            ->assertExitCode(0);

        $this->assertSame(User::ROLE_CONSULTANT, User::where('identifiant', 'lecteur')->value('role'));
    }
}
