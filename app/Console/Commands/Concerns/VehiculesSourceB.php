<?php

namespace App\Console\Commands\Concerns;

/**
 * Boîtiers de la source télématique B (plaque => identifiant de boîtier) et listes de véhicules
 * utilisées pour l'affichage et la fiabilité de ses données.
 */
class VehiculesSourceB
{
    public const DEVICE_PAR_PLAQUE = [
        // Camions du SITE A suivis aussi par la source A (donnée complémentaire)
        'DEMO-002' => 990001,
        'DEMO-001' => 990002,
        'DEMO-006' => 990003,
        'DEMO-005' => 990004,
        'DEMO-004' => 990005,
        'DEMO-003' => 990006,
        // Véhicules suivis uniquement par la source B
        'DEMO-013' => 990007, // CAMION PLATEAU
        'DEMO-014' => 990008, // BENNE
        'DEMO-015' => 990009,  // BENNE
        'DEMO-016' => 990010, // BENNE
        'DEMO-017' => 990011, // BENNE
        'DEMO-018' => 990012, // BENNE
        'DEMO-019' => 990013, // BENNE
        'DEMO-020' => 990014, // BENNE
        'DEMO-021' => 990015, // BENNE
        'DEMO-022' => 990016, // BENNE
        'DEMO-023' => 990017, // BENNE
        'DEMO-024' => 990018, // BENNE — matricule nettoyé (source: "==>DEMO-024")
        'DEMO-025' => 990019, // SEMI
        'DEMO-026' => 990020, // SEMI
        'DEMO-027' => 990021, // SEMI
    ];


    /**
     * Plaques mappées sans ligne `camions` : leurs données restent rattachées au boîtier
     * (camion_id=null) plutôt qu'ignorées.
     */
    public const PLAQUES_SANS_CAMION = [];

    /**
     * Une seule source de vérité pour ce texte, pour ne jamais le laisser diverger entre l'écran
     * "Comparaison des sources" et le rapport d'audit.
     */
    public const AVERTISSEMENT_DIVERGENCE_SOURCE_B = "Écart connu, pas une anomalie ponctuelle : il existe un écart important et systématique entre Télématique B et les deux autres sources (Télématique A, registre) — Télématique B n'est pas considérée comme une source de vérité pour le calcul de la norme interne (cf. rapport d'audit, section 5).";

    /**
     * Véhicules mappés Télématique B mais exclus du calcul de consommation — distinct d'un véhicule
     * sans aucun plein détecté.
     * Sert à choisir le bon motif d'absence dans « Comparaison des sources ».
     */
    public const PLAQUES_CONSOMMATION_EXCLUES = ['DEMO-002'];

    /** Véhicules dont la consommation plein-à-plein est jugée fiable (repli des KPI et rapport d'audit). */
    public const PLAQUES_CONSOMMATION_FIABLE = [
        'DEMO-003',
        'DEMO-004',
        'DEMO-013',
        'DEMO-014',
        'DEMO-016',
        'DEMO-019',
        'DEMO-023',
        'DEMO-026',
    ];
}
