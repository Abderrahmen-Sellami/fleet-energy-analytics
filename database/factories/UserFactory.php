<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    // Même mot de passe en clair pour toutes les instances par défaut (haché une seule fois, mis en
    // cache statique) — comportement standard du scaffold Laravel, conservé pour la vitesse des tests ;
    // le mot de passe EN CLAIR reste 'mot-de-passe-test', à utiliser explicitement dans les tests qui
    // ont besoin de se connecter avec un utilisateur créé par cette factory.
    public const MOT_DE_PASSE_CLAIR = 'mot-de-passe-test';

    protected static ?string $motDePasseHache = null;

    public function definition(): array
    {
        return [
            'identifiant' => $this->faker->unique()->userName(),
            'mot_de_passe' => static::$motDePasseHache ??= Hash::make(self::MOT_DE_PASSE_CLAIR),
            'role' => User::ROLE_CONSULTANT,
            'actif' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_ADMIN]);
    }

    public function consultant(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_CONSULTANT]);
    }

    public function inactif(): static
    {
        return $this->state(fn () => ['actif' => false]);
    }
}
