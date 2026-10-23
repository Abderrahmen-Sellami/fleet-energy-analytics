<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Gestion des utilisateurs — routes déjà gardées par le middleware `role.admin` (bootstrap/app.php
 * + routes/api.php) : un Consultant n'atteint jamais le corps de ces méthodes, jamais revérifié
 * ici (pas de garde dupliquée)
 */
class UtilisateurController extends Controller
{
    private const CHAMPS_PUBLICS = ['id', 'identifiant', 'role', 'actif', 'created_at'];

    public function index()
    {
        return response()->json(User::orderBy('identifiant')->get(self::CHAMPS_PUBLICS));
    }

    public function store(Request $request)
    {
        // Messages en français explicites : $request->validate() est ici le SEUL endroit de l'API qui
        // s'appuie sur la validation Laravel par tableau de règles (le reste du projet renvoie des
        // messages faits main) — sans ça, APP_LOCALE=en (jamais localisé côté validation dans ce
        // projet) laisserait passer le texte anglais par défaut de Laravel, seul endroit de l'app à
        // le faire.
        $data = $request->validate([
            'identifiant' => ['required', 'string', 'max:255', 'unique:users,identifiant'],
            'mot_de_passe' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(User::ROLES)],
        ], [
            'identifiant.required' => "L'identifiant est obligatoire.",
            'identifiant.unique' => 'Cet identifiant est déjà utilisé.',
            'mot_de_passe.required' => 'Le mot de passe est obligatoire.',
            'mot_de_passe.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'role.required' => 'Le rôle est obligatoire.',
            'role.in' => 'Rôle invalide.',
        ]);

        $user = User::create([
            'identifiant' => $data['identifiant'],
            'mot_de_passe' => Hash::make($data['mot_de_passe']),
            'role' => $data['role'],
            'actif' => true,
        ]);

        return response()->json($user->only(self::CHAMPS_PUBLICS), 201);
    }

    // role et/ou actif seulement — jamais l'identifiant ni le mot de passe ici.
    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'role' => ['sometimes', Rule::in(User::ROLES)],
            'actif' => ['sometimes', 'boolean'],
        ], [
            'role.in' => 'Rôle invalide.',
            'actif.boolean' => 'Valeur invalide pour actif.',
        ]);

        // Jamais 0 admin actif au final — sinon plus personne ne peut gérer les comptes, y compris pour
        // réparer cette même erreur (condition de clôture explicite : le système doit rester
        // administrable par construction, pas seulement "au moment où on le construit").
        $perdraitLeRoleAdmin = array_key_exists('role', $data) && $data['role'] !== User::ROLE_ADMIN;
        $seraitDesactive = array_key_exists('actif', $data) && $data['actif'] === false;

        if ($user->role === User::ROLE_ADMIN && $user->actif && ($perdraitLeRoleAdmin || $seraitDesactive)) {
            $autresAdminsActifs = User::where('role', User::ROLE_ADMIN)
                ->where('actif', true)
                ->where('id', '!=', $user->id)
                ->exists();

            if (!$autresAdminsActifs) {
                return response()->json([
                    'message' => "Impossible : {$user->identifiant} est le dernier administrateur actif.",
                ], 422);
            }
        }

        $user->update($data);

        return response()->json($user->only(self::CHAMPS_PUBLICS));
    }
}
