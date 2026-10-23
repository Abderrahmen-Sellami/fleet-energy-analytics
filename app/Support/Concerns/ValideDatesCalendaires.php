<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use Illuminate\Support\Carbon;

/**
 * Validation calendaire stricte d'une date AAAA-MM-JJ, partagée par tous les points d'entrée qui
 * en acceptent une : les 5 contrôleurs (CamionController, SerStandardsController,
 * PositionHistoriqueController, PositionHistoriqueSourceBController, AuditController) et la
 * commande carburant:prix (GererPrixCarburant)
 */
trait ValideDatesCalendaires
{
    // Valide qu'une chaîne AAAA-MM-JJ correspond à une vraie date calendaire, pas seulement à la
    // forme attendue par une regex — un simple `/^\d{4}-\d{2}-\d{2}$/` laisse passer "" ou "".
    private function estDateCalendaireValide(string $valeur): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur)) {
            return false;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $valeur)->format('Y-m-d') === $valeur;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
