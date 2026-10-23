<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Période du rapport d'audit ISO 50001 (AuditController::index) — validation calendaire stricte.
 */
class AuditPeriodeTest extends TestCase
{
    public static function datesImpossibles(): array
    {
        return [
            '13e mois' => ['2026-13-45'],
            '30 février' => ['2026-02-30'],
            '29 février hors année bissextile' => ['2026-02-29'],
            '31 avril' => ['2026-04-31'],
            'jour 00' => ['2026-09-00'],
            'mois 00' => ['2026-00-10'],
        ];
    }

    #[DataProvider('datesImpossibles')]
    public function test_refuse_en_422_une_date_impossible_sur_depuis_et_jusqu_a(string $date): void
    {
        $this->get("/audit-iso50001?confirmer=1&depuis={$date}&jusqu_a=2026-09-19")->assertStatus(422);
        $this->get("/audit-iso50001?confirmer=1&depuis=2026-01-01&jusqu_a={$date}")->assertStatus(422);
    }

    #[DataProvider('datesImpossibles')]
    public function test_refuse_aussi_une_date_impossible_sans_confirmation(string $date): void
    {
        // Sans confirmer=1 le formulaire est réaffiché, pré-rempli avec la valeur reçue : jamais une date
        // impossible dans un champ de saisie.
        $this->get("/audit-iso50001?depuis={$date}")->assertStatus(422);
    }

    public function test_formulaire_reaffiche_avec_des_dates_valides_sans_confirmation(): void
    {
        $this->get('/audit-iso50001?depuis=2026-01-01&jusqu_a=2026-09-19')
            ->assertOk()
            ->assertSee('name="jusqu_a"', false);
    }

    public function test_plage_inversee_reste_un_repli_sur_le_formulaire_avec_message(): void
    {
        $this->get('/audit-iso50001?confirmer=1&depuis=2026-09-10&jusqu_a=2026-01-01')
            ->assertOk()
            ->assertSee('antérieure ou égale');
    }

    public function test_format_non_date_reste_refuse_en_422(): void
    {
        $this->get('/audit-iso50001?confirmer=1&depuis=abc&jusqu_a=2026-09-19')->assertStatus(422);
    }

    public function test_le_29_fevrier_bissextile_est_accepte(): void
    {
        $this->get('/audit-iso50001?depuis=2024-02-29&jusqu_a=2024-03-31')
            ->assertOk()
            ->assertSee('name="depuis"', false);
    }
}
