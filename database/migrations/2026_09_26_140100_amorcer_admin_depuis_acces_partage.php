<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;

// Migration de DONNÉES — amorce le premier compte Admin à partir de l'ANCIEN couple
// identifiant/mot de passe partagé (ACCES_IDENTIFIANT/ACCES_MOT_DE_PASSE, .env) : la personne qui
// connaît déjà ces identifiants garde exactement le même accès après la migration vers le système
// de rôles, sans aucune coupure.
return new class extends Migration
{
    public function up(): void
    {
        $identifiant = (string) config('acces.identifiant');
        $motDePasse = (string) config('acces.mot_de_passe');

        if ($identifiant === '' || $motDePasse === '') {
            return;
        }

        User::firstOrCreate(
            ['identifiant' => $identifiant],
            [
                'mot_de_passe' => Hash::make($motDePasse),
                'role' => User::ROLE_ADMIN,
                'actif' => true,
            ]
        );
    }

    public function down(): void
    {
        $identifiant = (string) config('acces.identifiant');
        if ($identifiant !== '') {
            User::where('identifiant', $identifiant)->delete();
        }
    }
};
