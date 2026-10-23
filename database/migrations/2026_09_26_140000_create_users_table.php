<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// `identifiant` (pas `email`, jamais utilisé dans ce projet) et `mot_de_passe` (pas `password`)
// reprennent volontairement les noms de champ déjà utilisés par l'écran de connexion existant —
// continuité de l'écran, pas un renommage.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('identifiant')->unique();
            $table->string('mot_de_passe');
            $table->string('role');
            $table->boolean('actif')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
