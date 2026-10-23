<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * `identifiant`/`mot_de_passe` (pas le scaffold Laravel par défaut `name`/`email`/`password`,
 * jamais utilisé dans ce projet) : continuité des noms de champ déjà en place sur l'écran de
 * connexion.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_CONSULTANT = 'consultant';
    public const ROLES = [self::ROLE_ADMIN, self::ROLE_CONSULTANT];

    protected $fillable = ['identifiant', 'mot_de_passe', 'role', 'actif'];

    protected $hidden = ['mot_de_passe', 'remember_token'];

    protected $casts = ['actif' => 'boolean'];

    // Laravel s'attend à un champ "password" pour Auth::attempt()/le hasher — le nôtre s'appelle
    // mot_de_passe.
    public function getAuthPassword(): string
    {
        return $this->mot_de_passe;
    }

    public function estAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }
}
