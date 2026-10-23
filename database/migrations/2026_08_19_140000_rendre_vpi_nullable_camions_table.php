<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * camions.vpi (identifiant véhicule Télématique A) devient nullable, en préparation de l'accueil de
 * véhicules suivis uniquement par Télématique B  — sans abonnement Télématique A, donc sans VPI. La
 * contrainte UNIQUE (camions_vpi_unique) reste en place : InnoDB autorise nativement plusieurs
 * lignes NULL sur un index UNIQUE (NULL n'est jamais égal à NULL), donc aucun risque de conflit
 * entre plusieurs futurs véhicules Télématique B-only. `camions` reste la table pivot unique du projet
 * plutôt qu'une nouvelle table maître, pour ne pas avoir à réécrire les FK camion_id déjà en
 * place sur une dizaine de tables. Pas de colonne source/has_source_a/has_source_b ajoutée ici — la
 * présence ou l'absence de vpi (et plus tard de lignes mesures_source_b) suffira à déduire la source
 * tant qu'aucune donnée Télématique B réelle n'existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        $colonne = DB::selectOne("SHOW COLUMNS FROM camions WHERE Field = 'vpi'");
        if ($colonne !== null && $colonne->Null === 'NO') {
            DB::statement('ALTER TABLE camions MODIFY vpi VARCHAR(191) NULL');
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE camions MODIFY vpi VARCHAR(191) NOT NULL');
    }
};
