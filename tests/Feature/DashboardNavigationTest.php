<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Garde-fous de balisage du dashboard : le comportement JS.
 */
class DashboardNavigationTest extends TestCase
{
    use RefreshDatabase;

    // L'écran principal (onglet data-tab="apercu") s'appelle « Accueil » : Vue d'ensemble ->
    // Accueil -> Flotte -> Accueil.
    public function test_l_ecran_principal_s_appelle_accueil(): void
    {
        $html = $this->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('data-tab="apercu"><span class="nav-ico">📊</span><span class="nav-label">Accueil</span>', $html);
        $this->assertStringContainsString('<h1>Accueil</h1>', $html);
        $this->assertStringNotContainsString('<span class="nav-label">Flotte</span>', $html);
        $this->assertStringNotContainsString('<h1>Flotte</h1>', $html);
        // L'identifiant technique de l'onglet est conservé.
        $this->assertStringContainsString('id="tab-apercu"', $html);
    }

    // L'écran de configuration norme interne (onglet data-tab="ser-standards") s'appelle « norme
    // interne » : Standards SER -> norme interne.
    public function test_l_ecran_ser_standards_s_appelle_norme_ref(): void
    {
        $html = $this->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('data-tab="ser-standards"><span class="nav-ico">📐</span><span class="nav-label">Norme de référence</span>', $html);
        $this->assertStringContainsString('<h1>Norme interne de référence</h1>', $html);
        $this->assertStringNotContainsString('<span class="nav-label">Standards SER</span>', $html);
        $this->assertStringNotContainsString('<h1>Standards SER (pondérations norme interne)</h1>', $html);
        // L'identifiant technique de l'onglet est conservé.
        $this->assertStringContainsString('id="tab-ser-standards"', $html);
    }

    // Standards SER : les 12 colonnes du tableau sont triables au clic sur l'en-tête
    // (th[data-sort-ser]), sans exception, avec une recherche par immatriculation et un filtre
    // Statut norme interne propres au tableau. 14 colonnes (pct_ser_usage/pe_lh_precis), 15 depuis
    // le même jour (litres_dernier_mois)
    public function test_standards_ser_a_quinze_en_tetes_triables_recherche_et_filtre_statut(): void
    {
        $html = $this->get('/dashboard')->assertOk()->getContent();

        $colonnes = ['immatriculation', 'groupe', 'usage_norme_ref', 'ser_norme_ref', 'incertitude_pct', 'ser_1x', 'cible', 'statut', 'pct_ser_usage', 'pe_lh_precis', 'litres_dernier_mois', 'ipe', 'km', 'heures', 'conso_l100'];
        foreach ($colonnes as $colonne) {
            $this->assertSame(1, substr_count($html, '<th ' . ($colonne === 'immatriculation' || $colonne === 'groupe' ? 'rowspan="2" ' : '') . 'data-sort-ser="' . $colonne . '"'), "en-tête {$colonne}");
        }
        $this->assertSame(15, preg_match_all('/<th [^>]*data-sort-ser="/', $html));
        $this->assertStringContainsString('id="rechercheSerStandards"', $html);
        $this->assertStringContainsString('id="filtreStatutSerStandards"', $html);
        $this->assertStringContainsString('id="btnResetTableSerStandards"', $html);
        // Le tri de l'Accueil (th[data-sort]) n'est pas câblé sur ces en-têtes.
        $this->assertSame(0, preg_match('/<th [^>]*data-sort-ser="[^"]*"[^>]*data-sort="/', $html));
        // Le groupe "Dernier mois exploité" couvre désormais 4 colonnes (Statut norme interne + les 3
        // nouvelles), pas 1 seule.
        $this->assertStringContainsString('<th class="grp grp-statut" colspan="4">Dernier mois exploité</th>', $html);
    }

    public function test_standards_ser_n_a_plus_de_graphique_l_h_et_un_graphique_l100_a_selection(): void
    {
        $html = $this->get('/dashboard')->assertOk()->getContent();

        $this->assertStringNotContainsString('chartSerStandardsLocal', $html);
        $this->assertStringNotContainsString('chartSerStandardsSiteB', $html);
        $this->assertStringNotContainsString('Camions suivis en L/h', $html);
        $this->assertStringNotContainsString('titreChartSerLh', $html);

        $this->assertStringContainsString('<h3>Camions suivis en L/100km</h3>', $html);
        $this->assertSame(1, substr_count($html, 'id="chartSerL100"'));
        $this->assertStringContainsString('id="chipsSerGraphique"', $html);
        $this->assertStringContainsString('id="btnSerGraphToutAfficher"', $html);
        $this->assertStringContainsString('id="btnSerGraphToutEffacer"', $html);
        // Vide par défaut : le conteneur du graphique est masqué, le message d'état visible.
        $this->assertMatchesRegularExpression('/id="chartSerL100Wrap" class="hidden"/', $html);
        $this->assertStringContainsString('Aucun véhicule sélectionné', $html);
    }

    // En-têtes de colonnes fixes : tous les tableaux du dashboard sont dans un .table-scroll à
    // hauteur bornée dont les <th> de thead sont sticky ; désactivé à l'impression.
    public function test_en_tetes_de_tableaux_fixes_et_comparateur_un_camion_par_ligne(): void
    {
        $html = $this->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('.table-scroll thead th{position:sticky;top:0;', $html);
        $this->assertMatchesRegularExpression('/\.table-scroll\{overflow:auto;[^}]*max-height:/', $html);
        // Impression : hauteur non bornée et en-têtes non collés.
        $this->assertStringContainsString('.table-scroll{max-height:none !important;}', $html);
        $this->assertStringContainsString('.table-scroll thead th{position:static !important;}', $html);
        // 2e ligne d'en-tête de Standards SER collée sous la 1re.
        $this->assertStringContainsString('.tbl-ser-std thead tr:nth-child(2) th{top:34px;}', $html);

        $this->assertStringContainsString('<table class="tbl-comparateur">', $html);
        // Rendu du Comparateur : la ligne de tableau porte le camion (data-id sur <tr>), plus les en-têtes de colonne.
        $this->assertStringContainsString("thead.innerHTML = '<th>Camion</th>' + colonnes.map", $html);
        $this->assertStringNotContainsString("'<th>Indicateur</th>'", $html);
    }

    public function test_rapport_d_audit_a_des_en_tetes_fixes_desactives_a_l_impression(): void
    {
        $html = $this->get('/audit-iso50001?confirmer=1&depuis=2026-01-01&jusqu_a=2026-09-21')->assertOk()->getContent();

        $this->assertStringContainsString('thead th{position:sticky;top:var(--toolbar-h,58px);', $html);
        $this->assertStringContainsString('thead th{position:static !important;}', $html);
    }

    // Impression du rapport d'audit : titre de section jamais coupé entre deux pages, et section 2
    // (10 colonnes dont « Consommation Télématique B », tronquée en portrait) en paysage via une
    // page nommée.
    public function test_rapport_d_audit_imprime_la_section_2_en_paysage_et_ne_coupe_pas_les_titres(): void
    {
        $html = $this->get('/audit-iso50001?confirmer=1&depuis=2026-01-01&jusqu_a=2026-09-21')->assertOk()->getContent();

        $this->assertStringContainsString('.section-title{break-after:avoid;break-inside:avoid;}', $html);
        $this->assertStringContainsString('@page paysage{size:A4 landscape;', $html);
        $this->assertStringContainsString('.section-paysage{page:paysage;}', $html);
        // La classe est posée sur la section 2 (statut norme interne par véhicule) et sur elle seule.
        $this->assertSame(1, substr_count($html, 'class="section section-paysage"'));
        $this->assertMatchesRegularExpression('/class="section section-paysage">\s*<div class="section-title"><span class="section-num">2<\/span>/', $html);
    }
}
