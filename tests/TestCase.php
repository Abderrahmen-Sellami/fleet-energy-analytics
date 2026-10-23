<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;

abstract class TestCase extends BaseTestCase
{
    /**
     * Système de rôles : tous les tests existants portent sur la logique métier des endpoints
     * /api/* et des pages protégées, pas sur l'authentification — ils démarrent donc déjà
     * connectés, en tant qu'Admin (accès complet, jamais bloqués par role.admin sur une route qui
     * l'exigerait)
     */
    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::updateOrCreate(
            ['identifiant' => 'test-admin-defaut'],
            ['mot_de_passe' => Hash::make('mot-de-passe-test-admin-defaut'), 'role' => User::ROLE_ADMIN, 'actif' => true]
        );

        $this->actingAs($admin);
    }
}
