<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Crée un compte de connexion depuis la ligne de commande — indispensable pour le TOUT PREMIER
 * Admin d'une installation neuve (l'écran /utilisateurs exige déjà d'être Admin)
 */
class CreerUtilisateur extends Command
{
    protected $signature = 'utilisateur:creer
                            {identifiant : Identifiant de connexion}
                            {--role=admin : admin ou consultant}
                            {--mot-de-passe= : Mot de passe (sinon demandé de façon masquée)}';

    protected $description = 'Crée un compte de connexion (Admin par défaut) avec un mot de passe correctement haché.';

    public function handle(): int
    {
        $identifiant = trim((string) $this->argument('identifiant'));
        $role = (string) $this->option('role');

        if ($identifiant === '') {
            $this->error("L'identifiant est vide.");
            return self::FAILURE;
        }

        if (! in_array($role, User::ROLES, true)) {
            $this->error("Rôle inconnu « {$role} » : valeurs acceptées " . implode(', ', User::ROLES) . '.');
            return self::FAILURE;
        }

        if (User::where('identifiant', $identifiant)->exists()) {
            $this->error("Cet identifiant est déjà utilisé : {$identifiant}.");
            return self::FAILURE;
        }

        $motDePasse = (string) ($this->option('mot-de-passe') ?? $this->secret('Mot de passe (8 caractères minimum)'));

        if (mb_strlen($motDePasse) < 8) {
            $this->error('Le mot de passe doit contenir au moins 8 caractères.');
            return self::FAILURE;
        }

        User::create([
            'identifiant' => $identifiant,
            'mot_de_passe' => Hash::make($motDePasse),
            'role' => $role,
            'actif' => true,
        ]);

        $this->info("Compte « {$identifiant} » créé (rôle : {$role}).");

        return self::SUCCESS;
    }
}
