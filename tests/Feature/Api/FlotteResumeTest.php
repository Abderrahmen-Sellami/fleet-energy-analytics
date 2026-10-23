<?php

namespace Tests\Feature\Api;

use App\Console\Commands\Concerns\VehiculesSourceB;
use App\Models\Camion;
use App\Models\ConsommationSourceB;
use App\Models\Mesure;
use App\Models\MesureSourceB;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlotteResumeTest extends TestCase
{
    use RefreshDatabase;

    // Certains tests figent le temps (Carbon::setTestNow()) pour rendre déterministe le repli "6
    // derniers mois glissants" de par_groupe.conso_moyenne_6_derniers_mois — toujours remis à zéro
    // ici.
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // conducteur_source_b : saisie manuelle unique pour les 6 camions SITE A, jamais pour les 6
    // SITE B — null doit rester null dans la réponse, jamais une chaîne de repli du type "Inconnu"
    // qui laisserait croire à une vraie donnée absente.
    public function test_conducteur_source_b_present_seulement_quand_renseigne(): void
    {
        $avecConducteur = Camion::factory()->create(['immatriculation' => 'TEST001', 'groupe' => 'SITE A', 'conducteur_source_b' => 'DUPONT JEAN']);
        Mesure::factory()->create(['camion_id' => $avecConducteur->id, 'date' => '2026-01-01', 'consommation_l100' => 40]);

        $sansConducteur = Camion::factory()->create(['immatriculation' => 'TEST002', 'groupe' => 'SITE B', 'conducteur_source_b' => null]);
        Mesure::factory()->create(['camion_id' => $sansConducteur->id, 'date' => '2026-01-01', 'consommation_l100' => 40]);

        $response = $this->getJson('/api/flotte/resume');

        $parImmat = collect($response->json('par_camion'))->keyBy('immatriculation');
        $this->assertSame('DUPONT JEAN', $parImmat['TEST001']['conducteur_source_b']);
        $this->assertNull($parImmat['TEST002']['conducteur_source_b']);
    }

    public function test_groupe_sans_aucune_mesure_ne_plante_pas_et_propage_null(): void
    {
        Camion::factory()->create(['immatriculation' => 'TEST999VIDE', 'groupe' => 'GROUPE_VIDE_TEST']);

        $response = $this->getJson('/api/flotte/resume');

        $response->assertOk();
        $groupe = $response->json('par_groupe.GROUPE_VIDE_TEST');
        $this->assertNull($groupe['conso_moyenne_globale']);
        $this->assertNull($groupe['ipe_vs_objectif_pct']);
    }

    public function test_retourne_200_avec_structure_de_base_sans_parametre(): void
    {
        $camion = Camion::factory()->create(['groupe' => 'SITE A']);
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'consommation_l100' => 40]);

        $response = $this->getJson('/api/flotte/resume');

        $response->assertOk()
            ->assertJsonStructure([
                'nb_camions', 'nb_mesures_totales', 'nb_mesures_exploitables', 'nb_mesures_en_cours', 'nb_mesures_lacunaires',
                'eco_score_moyen', 'conso_moyenne', 'cout_carburant_moyen', 'cout_carburant_estime', 'par_groupe', 'par_camion',
            ]);
        $this->assertSame(1, $response->json('nb_camions'));
    }

    public function test_retourne_422_sur_format_de_date_invalide(): void
    {
        $response = $this->getJson('/api/flotte/resume?depuis=pas-une-date');

        $response->assertStatus(422)->assertJsonStructure(['message']);
    }

    public function test_retourne_422_quand_depuis_est_posterieur_a_jusqu_a(): void
    {
        $response = $this->getJson('/api/flotte/resume?depuis=2026-06-01&jusqu_a=2026-01-01');

        $response->assertStatus(422)->assertJsonStructure(['message']);
    }

    // Carbon::hasFormat() a été écarté après vérification empirique : il renvoie aussi `true` pour
    // "" et "" en année non bissextile — même défaut que la regex.
    public function test_retourne_422_sur_date_au_format_correct_mais_calendrier_invalide(): void
    {
        foreach (['2026-13-99', '2026-02-30', '2026-01-00', '2026-01-32'] as $dateImpossible) {
            $response = $this->getJson("/api/flotte/resume?depuis={$dateImpossible}");
            $response->assertStatus(422)->assertJsonStructure(['message']);

            $response = $this->getJson("/api/flotte/resume?jusqu_a={$dateImpossible}");
            $response->assertStatus(422)->assertJsonStructure(['message']);
        }
    }

    // Contre-exemple du test ci-dessus : une date bissextile réelle ou toute date calendaire
    // valide ne doit jamais être rejetée par le même correctif.
    public function test_accepte_une_date_bissextile_reelle(): void
    {
        $camion = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2024-02-01', 'consommation_l100' => 40]);

        $response = $this->getJson('/api/flotte/resume?depuis=2024-02-29&jusqu_a=2024-02-29');

        $response->assertOk();
    }

    public function test_accepte_une_periode_valide(): void
    {
        $camion = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-03-01', 'consommation_l100' => 40]);

        $response = $this->getJson('/api/flotte/resume?depuis=2026-01-01&jusqu_a=2026-12-31');

        $response->assertOk();
    }

    // Total volontairement différent de 324 ci-dessous, pour que ce test échoue net si quelqu'un
    // recode un total figé par erreur — cohérence attendue : nb_mesures_totales = Mesure::count(),
    // et les 3 compartiments (exploitables/en_cours/ lacunaires) s'équilibrent exactement sur ce
    // total.
    public function test_nb_mesures_totales_correspond_au_vrai_compte_en_base_et_pas_a_une_valeur_figee(): void
    {
        $camion = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'consommation_l100' => 40]); // exploitable
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-02-01', 'consommation_l100' => 50]); // exploitable
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-03-01', 'consommation_l100' => 0]); // lacunaire (0 = absence)
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-04-01', 'consommation_l100' => 45, 'est_partielle' => true]); // mois en cours

        $response = $this->getJson('/api/flotte/resume');

        $response->assertOk();
        $this->assertSame(4, Mesure::count());
        $this->assertSame(4, $response->json('nb_mesures_totales'));
        $this->assertSame(2, $response->json('nb_mesures_exploitables'));
        $this->assertSame(1, $response->json('nb_mesures_en_cours'));
        $this->assertSame(1, $response->json('nb_mesures_lacunaires'));

        $this->assertSame(
            $response->json('nb_mesures_totales'),
            $response->json('nb_mesures_exploitables') + $response->json('nb_mesures_en_cours') + $response->json('nb_mesures_lacunaires')
        );
    }

    // conso_horaire_moyenne : contrairement à conso_moyenne, conso_horaire n'est pas garanti
    // non-null sur toute mesure exploitable — ce test vérifie que la moyenne ignore bien les
    // mesures sans conso_horaire (au lieu de les compter comme 0.
    public function test_conso_horaire_moyenne_ignore_les_mesures_sans_cette_donnee(): void
    {
        $camion = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'consommation_l100' => 40, 'conso_horaire' => 10]);
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-02-01', 'consommation_l100' => 40, 'conso_horaire' => 20]);
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-03-01', 'consommation_l100' => 40, 'conso_horaire' => null]);

        $response = $this->getJson('/api/flotte/resume');

        $response->assertOk()->assertJsonStructure(['conso_horaire_moyenne', 'nb_mesures_conso_horaire']);
        $this->assertSame(3, $response->json('nb_mesures_periode'));
        $this->assertSame(2, $response->json('nb_mesures_conso_horaire'));
        $this->assertEquals(15.0, $response->json('conso_horaire_moyenne')); // (10+20)/2, pas /3
    }

    // Sans effet visible sur le dashboard (formatKpiPeriode() guarde déjà sur nb_mesures_periode/
    // nb_mesures_conso_horaire avant d'afficher quoi que ce soit), mais l'API elle-même doit dire
    // la vérité pour tout futur consommateur qui ne passerait pas par ce garde-fou frontend.
    public function test_kpi_flotte_propagent_null_quand_aucun_camion_selectionne(): void
    {
        Camion::factory()->create(['groupe' => 'SITE A']);

        $response = $this->getJson('/api/flotte/resume?site=__AUCUN__');

        $response->assertOk();
        $this->assertSame(0, $response->json('nb_camions'));
        $this->assertNull($response->json('eco_score_moyen'));
        $this->assertNull($response->json('conso_moyenne'));
        $this->assertNull($response->json('conso_horaire_moyenne'));
        $this->assertNull($response->json('litres_consommes'));
        $this->assertNull($response->json('montant_carburant'));
    }

    // Contre-exemple du test ci-dessus : dès qu'au moins une mesure exploitable existe, les 3 KPI
    // flotte doivent redevenir de vrais nombres (pas rester null par erreur de logique inversée).
    public function test_kpi_flotte_restent_des_nombres_quand_des_mesures_existent(): void
    {
        $camion = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'consommation_l100' => 40, 'ecoscore' => 8, 'conso_horaire' => 10]);

        $response = $this->getJson('/api/flotte/resume');

        $response->assertOk();
        $this->assertEquals(8.0, $response->json('eco_score_moyen'));
        $this->assertEquals(40.0, $response->json('conso_moyenne'));
        $this->assertEquals(10.0, $response->json('conso_horaire_moyenne'));
        $this->assertEquals(2000.0, $response->json('litres_consommes')); // 40 L/100km * 5000 km / 100
        $this->assertEquals(round(2000.0 * 1.985, 2), $response->json('montant_carburant'));
    }

    // Litres consommés/Montant carburant : somme RÉELLE par mesure (consommation_l100 *
    // km_parcourus / 100), pas une moyenne — 2 mesures distinctes pour vérifier que c'est bien une
    // somme.
    public function test_litres_consommes_et_montant_carburant_sont_la_somme_reelle_sur_la_periode(): void
    {
        $camion = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'consommation_l100' => 40, 'km_parcourus' => 1000]); // 400 L
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-02-01', 'consommation_l100' => 30, 'km_parcourus' => 500]); // 150 L

        $response = $this->getJson('/api/flotte/resume');

        $response->assertOk();
        $this->assertEquals(550.0, $response->json('litres_consommes'));
        $this->assertEquals(round(550.0 * 1.985, 2), $response->json('montant_carburant'));
        $this->assertEquals(1.985, $response->json('montant_carburant_prix_l'));
        $this->assertFalse($response->json('litres_consommes_inclut_source_b'));
    }

    // Cohérence avec la période depuis/jusqu_a — même fenêtre que conso_moyenne/eco_score_moyen
    // (cf. commentaire "fait maintenant autorité pour TOUS les KPI..." dans resumeFlotte()) : seule
    // la mesure de janvier doit compter ici, pas celle de mars.
    public function test_litres_consommes_suit_la_periode_depuis_jusqu_a(): void
    {
        $camion = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-15', 'consommation_l100' => 40, 'km_parcourus' => 1000]); // 400 L
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-03-15', 'consommation_l100' => 40, 'km_parcourus' => 1000]); // 400 L, hors période

        $response = $this->getJson('/api/flotte/resume?depuis=2026-01-01&jusqu_a=2026-01-31');

        $response->assertOk();
        $this->assertEquals(400.0, $response->json('litres_consommes'));
    }

    // Ici : un camion avec deux mesures très éloignées dans le temps (2024 vs 2026), pour vérifier
    // que la moyenne "récente" scopée sur une période explicite ne prend bien que la mesure dans
    // les bornes demandées, pas un repli sur la fenêtre glissante des 6 derniers mois.
    public function test_par_groupe_conso_recente_suit_la_periode_quand_elle_est_fournie(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00'); // 6 mois glissants -> depuis le 2026-03-25

        $camion = Camion::factory()->create(['groupe' => 'SITE A']);
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2024-01-01', 'consommation_l100' => 20]);
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-06-01', 'consommation_l100' => 40]);

        $reponseSansPeriode = $this->getJson('/api/flotte/resume');
        $reponseAvecPeriode = $this->getJson('/api/flotte/resume?depuis=2024-01-01&jusqu_a=2024-12-31');

        // Sans période : repli sur la fenêtre calendaire des 6 derniers mois — seule la mesure de
        // y tombe, celle de en est exclue (contrairement à l'ancien repli par nombre de lignes,
        // qui aurait inclus les 2 seules mesures disponibles et donné 30,0, cf. section suivante
        // pour le scénario complet où ça se produisait réellement)
        $this->assertEquals(40.0, $reponseSansPeriode->json('par_groupe.SITE A.conso_moyenne_6_derniers_mois'));

        // Avec une période scopée sur 2024 uniquement : seule la mesure à 20 est dans les bornes.
        $this->assertEquals(20.0, $reponseAvecPeriode->json('par_groupe.SITE A.conso_moyenne_6_derniers_mois'));
    }

    // Depuis l'élargissement Télématique B, un groupe peut compter des camions SANS AUCUNE mesure
    // Télématique A (véhicules Télématique B-purs) : `$groupe->count()` reste gonflé par ces
    // véhicules pendant que le pool de mesures disponibles ne vient que des camions Télématique A
    // du groupe.
    public function test_conso_recente_ignore_les_mesures_anciennes_meme_avec_des_camions_sans_donnee_dans_le_groupe(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00'); // 6 mois glissants -> depuis le 2026-03-25

        $camionAvecHistorique = Camion::factory()->create(['groupe' => 'SITE A', 'source_principale' => 'SOURCE_A']);
        foreach (['2024-01-01', '2024-06-01', '2025-01-01', '2026-01-01'] as $date) {
            Mesure::factory()->create(['camion_id' => $camionAvecHistorique->id, 'date' => $date, 'consommation_l100' => 10]); // hors fenêtre
        }
        foreach (['2026-04-01', '2026-05-01', '2026-06-01'] as $date) {
            Mesure::factory()->create(['camion_id' => $camionAvecHistorique->id, 'date' => $date, 'consommation_l100' => 50]); // dans la fenêtre
        }
        // 2 camions Télématique B-purs du même groupe, sans aucune mesure Télématique A — gonflent $groupe->count()
        // à 3 sans jamais contribuer une seule mesure au pool moyenné.
        Camion::factory()->create(['groupe' => 'SITE A', 'source_principale' => 'SOURCE_B']);
        Camion::factory()->create(['groupe' => 'SITE A', 'source_principale' => 'SOURCE_B']);

        $response = $this->getJson('/api/flotte/resume?source_principale=SOURCE_A,SOURCE_B');

        $response->assertOk();
        $this->assertSame(3, $response->json('par_groupe.SITE A.nb_camions'));
        // Moyenne des 3 seules mesures dans la fenêtre calendaire (50+50+50)/3 = 50 — jamais
        // mélangée aux 4 mesures de 2024-, qui auraient donné (10*4+50*3)/7 ≈ 27,14 avec l'ancien
        // repli.
        $this->assertEquals(50.0, $response->json('par_groupe.SITE A.conso_moyenne_6_derniers_mois'));
    }

    // Carte « Qualité des données » : qualite_mesures ventile nb_mesures_totales par RAISON
    // d'exclusion.
    public function test_qualite_mesures_ventile_les_mesures_source_a_par_raison_et_somme_au_total(): void
    {
        $camion = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'consommation_l100' => 40]);   // exploitable
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-02-01', 'consommation_l100' => null]); // sans donnée
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-03-01', 'consommation_l100' => 0]);    // à zéro
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-04-01', 'consommation_l100' => 150]);  // hors bornes (haute)
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-05-01', 'consommation_l100' => -3]);   // hors bornes (négative)
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-06-01', 'consommation_l100' => 45, 'est_partielle' => true]);
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-07-01', 'consommation_l100' => null, 'est_partielle' => true]);

        $response = $this->getJson('/api/flotte/resume');

        $response->assertOk();
        $o = $response->json('qualite_mesures.source_a');
        $this->assertSame(7, $o['total']);
        $this->assertSame(1, $o['exploitables']);
        $this->assertSame(2, $o['en_cours']);
        $this->assertSame(['2026-06', '2026-07'], $o['en_cours_mois']);
        $this->assertSame(1, $o['exclues_sans_donnee']);
        $this->assertSame(1, $o['exclues_a_zero']);
        $this->assertSame(2, $o['exclues_hors_bornes']);
        $this->assertEquals(Mesure::CONSO_L100_MAX_PLAUSIBLE, $o['bornes_max_l100']);

        // Les parties retombent exactement sur les chiffres de tête, sans double-comptage.
        $this->assertSame($response->json('nb_mesures_totales'), $o['total']);
        $this->assertSame($response->json('nb_mesures_exploitables'), $o['exploitables']);
        $this->assertSame(
            $o['total'],
            $o['exploitables'] + $o['en_cours'] + $o['exclues_sans_donnee'] + $o['exclues_a_zero'] + $o['exclues_hors_bornes']
        );
        $this->assertSame(
            $response->json('nb_mesures_lacunaires'),
            $o['exclues_sans_donnee'] + $o['exclues_a_zero'] + $o['exclues_hors_bornes']
        );
        $this->assertSame(1, $response->json('qualite_mesures.camions.source_a'));
    }

    // La période (depuis/jusqu_a) doit borner la ventilation Télématique A comme elle borne le total.
    public function test_qualite_mesures_source_a_suit_la_periode(): void
    {
        $camion = Camion::factory()->create();
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'consommation_l100' => null]);
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-03-01', 'consommation_l100' => 40]);

        $o = $this->getJson('/api/flotte/resume?depuis=2026-03-01&jusqu_a=2026-03-31')->json('qualite_mesures.source_a');

        $this->assertSame(1, $o['total']);
        $this->assertSame(1, $o['exploitables']);
        $this->assertSame(0, $o['exclues_sans_donnee']);
    }

    // Côté Télématique B, une « mesure » est un intervalle plein-à-plein (consommations_source_b), pas un mois. « Exploitable » =
    // consommation calculée ET dans les bornes [5, 80] L/100km (INCLUSIVES, mêmes que la plausibilité d'une moyenne
    // Télématique B — ConsommationSourceB::scopeExploitable()) ; deux raisons d'exclusion distinctes : distance introuvable
    // (NULL) et hors bornes (en dessous de 5 ou au-dessus de 80). On ne compte que les véhicules Télématique B dont la
    // consommation est jugée fiable (PLAQUES_CONSOMMATION_FIABLE) ; un véhicule Télématique B hors liste est « non compté ».
    public function test_qualite_mesures_source_b_compte_les_intervalles_et_les_vehicules_non_comptes(): void
    {
        $fiable = Camion::factory()->create(['immatriculation' => 'DEMO-013', 'source_principale' => 'SOURCE_B']);
        Camion::factory()->create(['immatriculation' => 'DEMO-015', 'source_principale' => 'SOURCE_B']); // hors liste fiable
        // 8.5 / 9.1 : plausibles ; 5.0 et 80.0 : les bornes elles-mêmes (incluses) ; 4.99 et 80.01 : juste dehors ;
        // null : distance introuvable.
        foreach ([8.5, 9.1, 5.0, 80.0, 4.99, 80.01, null] as $i => $conso) {
            ConsommationSourceB::create([
                'device_id' => 1, 'camion_id' => $fiable->id,
                'plein_precedent_date' => now()->subDays(60 - $i * 5), 'plein_suivant_date' => now()->subDays(55 - $i * 5),
                'volume_l' => 100, 'distance_km' => $conso === null ? null : 1000, 'consommation_l100km' => $conso,
            ]);
        }

        $response = $this->getJson('/api/flotte/resume?source_principale=SOURCE_A,SOURCE_B');

        $response->assertOk();
        $t = $response->json('qualite_mesures.source_b');
        $this->assertSame(7, $t['total']);
        $this->assertSame(4, $t['exploitables']);
        $this->assertSame(1, $t['exclues_sans_distance']);
        $this->assertSame(2, $t['exclues_hors_bornes']);
        $this->assertEquals([5, 80], $t['bornes_l100']);
        // Les parties retombent sur le total, sans double-comptage.
        $this->assertSame($t['total'], $t['exploitables'] + $t['exclues_sans_distance'] + $t['exclues_hors_bornes']);
        $this->assertSame(0, $response->json('qualite_mesures.camions.source_a'));
        $this->assertSame(1, $response->json('qualite_mesures.camions.source_b'));
        $this->assertSame(1, $response->json('qualite_mesures.camions.non_comptes'));
        // Les chiffres de tête = Télématique A (0 ici) + Télématique B.
        $this->assertSame(7, $response->json('nb_mesures_totales'));
        $this->assertSame(4, $response->json('nb_mesures_exploitables'));
        $this->assertSame(3, $response->json('nb_mesures_lacunaires')); // 1 sans distance + 2 hors bornes
    }

    // Le rapport d'audit ISO 50001 désactive le repli Télématique B (desactiver_repli_source_b=1) : la ventilation doit
    // suivre — aucun intervalle Télématique B ne doit apparaître dans un décompte censé être 100% Télématique A.
    public function test_qualite_mesures_source_b_est_vide_quand_le_repli_source_b_est_desactive(): void
    {
        $fiable = Camion::factory()->create(['immatriculation' => 'DEMO-014', 'source_principale' => 'SOURCE_B']);
        ConsommationSourceB::create([
            'device_id' => 2, 'camion_id' => $fiable->id, 'plein_precedent_date' => now()->subDays(10),
            'plein_suivant_date' => now()->subDays(5), 'volume_l' => 100, 'distance_km' => 1000, 'consommation_l100km' => 10,
        ]);

        $response = $this->getJson('/api/flotte/resume?source_principale=SOURCE_A,SOURCE_B&desactiver_repli_source_b=1');

        $response->assertOk();
        $this->assertSame(0, $response->json('qualite_mesures.source_b.total'));
        $this->assertSame(0, $response->json('qualite_mesures.source_b.exploitables'));
        $this->assertSame(0, $response->json('nb_mesures_totales'));
    }

    // Un camion Télématique B-fiable sans mesure Télématique A, avec un couple (jour de mileage +
    // intervalle plein-à-plein) DANS la période demandée et un autre couple HORS période.
    public function test_distance_litres_et_mesures_exploitables_source_b_suivent_la_periode(): void
    {
        $camion = Camion::factory()->create(['immatriculation' => 'DEMO-013', 'source_principale' => 'SOURCE_B']);
        $device = VehiculesSourceB::DEVICE_PAR_PLAQUE['DEMO-013'];

        MesureSourceB::create([
            'device_id' => $device, 'camion_id' => $camion->id, 'source' => 'mileage',
            'horodatage_record' => '2026-03-15 00:00:00', 'distance_km' => 100,
        ]);
        MesureSourceB::create([ // hors période
            'device_id' => $device, 'camion_id' => $camion->id, 'source' => 'mileage',
            'horodatage_record' => '2026-07-15 00:00:00', 'distance_km' => 200,
        ]);

        ConsommationSourceB::create([
            'device_id' => $device, 'camion_id' => $camion->id,
            'plein_precedent_date' => '2026-03-10 08:00:00', 'plein_suivant_date' => '2026-03-15 08:00:00',
            'volume_l' => 40, 'distance_km' => 100, 'consommation_l100km' => 40,
        ]);
        ConsommationSourceB::create([ // hors période — même convention que CourbesController/KilometrageSourceBSource :
            // un intervalle "appartient" au mois/à la date de plein_suivant_date, pas de plein_precedent_date.
            'device_id' => $device, 'camion_id' => $camion->id,
            'plein_precedent_date' => '2026-07-10 08:00:00', 'plein_suivant_date' => '2026-07-15 08:00:00',
            'volume_l' => 60, 'distance_km' => 200, 'consommation_l100km' => 30,
        ]);

        $sansPeriode = $this->getJson('/api/flotte/resume?source_principale=SOURCE_A,SOURCE_B');
        $avecPeriode = $this->getJson('/api/flotte/resume?source_principale=SOURCE_A,SOURCE_B&depuis=2026-03-01&jusqu_a=2026-03-31');

        // Tout l'historique : les 2 couples contribuent.
        $this->assertEquals(300.0, $sansPeriode->json('distance_totale'));
        $this->assertTrue($sansPeriode->json('distance_totale_inclut_source_b'));
        $this->assertEquals(100.0, $sansPeriode->json('litres_consommes'));
        $this->assertTrue($sansPeriode->json('litres_consommes_inclut_source_b'));
        $this->assertSame(2, $sansPeriode->json('nb_mesures_totales'));
        $this->assertSame(2, $sansPeriode->json('nb_mesures_exploitables'));

        // Mars 2026 seul : uniquement le couple de mars, jamais celui de juillet.
        $this->assertEquals(100.0, $avecPeriode->json('distance_totale'));
        $this->assertEquals(40.0, $avecPeriode->json('litres_consommes'));
        $this->assertSame(1, $avecPeriode->json('nb_mesures_totales'));
        $this->assertSame(1, $avecPeriode->json('nb_mesures_exploitables'));

        // qualite_mesures.camions.source_b doit rester cohérent avec
        // qualite_mesures.source_b.total : le camion n'a aucun intervalle dans une fenêtre qui ne
        // le couvre pas du tout.
        $vide = $this->getJson('/api/flotte/resume?source_principale=SOURCE_A,SOURCE_B&depuis=2024-01-01&jusqu_a=2024-01-31');
        $this->assertSame(0, $vide->json('qualite_mesures.source_b.total'));
        $this->assertSame(0, $vide->json('qualite_mesures.camions.source_b'));
    }

    // Mêmes bornes réutilisées ici : > 1000 km ou < -0,5 km (tolérance d'arrondi)
    public function test_distance_totale_source_b_exclut_les_jours_de_kilometrage_corrompu(): void
    {
        $camion = Camion::factory()->create(['immatriculation' => 'DEMO-013', 'source_principale' => 'SOURCE_B']);
        $device = VehiculesSourceB::DEVICE_PAR_PLAQUE['DEMO-013'];

        MesureSourceB::create([ // jour sain
            'device_id' => $device, 'camion_id' => $camion->id, 'source' => 'mileage',
            'horodatage_record' => '2026-03-01 00:00:00', 'distance_km' => 100,
        ]);
        MesureSourceB::create([ // corrompu : compteur du boîtier faussé
            'device_id' => $device, 'camion_id' => $camion->id, 'source' => 'mileage',
            'horodatage_record' => '2026-03-02 00:00:00', 'distance_km' => 154321.4,
        ]);
        MesureSourceB::create([ // corrompu : distance négative
            'device_id' => $device, 'camion_id' => $camion->id, 'source' => 'mileage',
            'horodatage_record' => '2026-03-03 00:00:00', 'distance_km' => -50.0,
        ]);
        MesureSourceB::create([ // sain à la borne exacte (1000 km, inclus — cf. KilometrageSourceBSource)
            'device_id' => $device, 'camion_id' => $camion->id, 'source' => 'mileage',
            'horodatage_record' => '2026-03-04 00:00:00', 'distance_km' => 1000.0,
        ]);

        $response = $this->getJson('/api/flotte/resume?source_principale=SOURCE_A,SOURCE_B');

        $response->assertOk();
        $this->assertEquals(1100.0, $response->json('distance_totale')); // 100 + 1000, jamais les 2 jours aberrants
        $this->assertTrue($response->json('distance_totale_inclut_source_b'));
    }

    // Deux camions de même groupe, cibles norme interne différentes (44,1 et 34,3) : chacun doit
    // être jugé contre SA propre cible, jamais un objectif partagé par le groupe.
    public function test_objectif_conso_et_objectif_atteint_utilisent_la_cible_norme_ref_du_camion(): void
    {
        $camionCibleHaute = Camion::factory()->create([
            'groupe' => 'SITE A', 'usage_norme_ref' => 'Transport — catégorie 1',
            'ser_norme_ref' => 45, 'ser_norme_ref_incertitude' => 0.25, 'ser_norme_ref_cible' => 44.1,
        ]);
        Mesure::factory()->create(['camion_id' => $camionCibleHaute->id, 'date' => '2026-01-01', 'consommation_l100' => 40]); // <= 44,1 mais > 35

        $camionCibleBasse = Camion::factory()->create([
            'groupe' => 'SITE A', 'usage_norme_ref' => 'Transport — catégorie 2',
            'ser_norme_ref' => 35, 'ser_norme_ref_incertitude' => 0.15, 'ser_norme_ref_cible' => 34.3,
        ]);
        Mesure::factory()->create(['camion_id' => $camionCibleBasse->id, 'date' => '2026-01-01', 'consommation_l100' => 40]); // > 34,3 (et > 35)

        $response = $this->getJson('/api/flotte/resume');
        $parId = collect($response->json('par_camion'))->keyBy('id');

        // 40 <= 44,1 (sa cible) : Atteint — aurait été "Non atteint" contre l'ancien objectif fixe à 35.
        $this->assertEquals(44.1, $parId[$camionCibleHaute->id]['objectif_conso']);
        $this->assertTrue($parId[$camionCibleHaute->id]['objectif_atteint']);

        // 40 > 34,3 (sa cible) : Non atteint — même valeur affichée (40), verdict opposé, sans
        // qu'aucun objectif de groupe commun n'intervienne (les deux camions sont dans SITE A).
        $this->assertEquals(34.3, $parId[$camionCibleBasse->id]['objectif_conso']);
        $this->assertFalse($parId[$camionCibleBasse->id]['objectif_atteint']);
    }

    // Garde-fou d'unité : un usage hors Transport a sa cible norme interne en L/h
    // (NormeRefStatutService::uniteSer), jamais comparable à conso_moyenne (Télématique A,
    // toujours en L/100km) — objectif_conso/objectif_atteint doivent rester null, jamais une
    // comparaison fabriquée entre deux unités incompatibles.
    public function test_objectif_conso_reste_null_pour_un_usage_non_transport(): void
    {
        $camion = Camion::factory()->create([
            'groupe' => 'SITE A', 'usage_norme_ref' => 'Toupie',
            'ser_norme_ref' => 8, 'ser_norme_ref_incertitude' => 0.2, 'ser_norme_ref_cible' => 7.8,
        ]);
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'consommation_l100' => 40]);

        $response = $this->getJson('/api/flotte/resume');
        $parId = collect($response->json('par_camion'))->keyBy('id');

        $this->assertNull($parId[$camion->id]['objectif_conso']);
        $this->assertNull($parId[$camion->id]['objectif_atteint']);
    }

    // Aucune cible norme interne définie (ser_norme_ref_cible null) : même garde, jamais un repli sur 35 ou sur
    // un objectif de groupe.
    public function test_objectif_conso_reste_null_sans_cible_norme_ref_definie(): void
    {
        $camion = Camion::factory()->create([
            'groupe' => 'SITE A', 'usage_norme_ref' => 'Transport — catégorie 2',
            'ser_norme_ref' => null, 'ser_norme_ref_incertitude' => null, 'ser_norme_ref_cible' => null,
        ]);
        Mesure::factory()->create(['camion_id' => $camion->id, 'date' => '2026-01-01', 'consommation_l100' => 40]);

        $response = $this->getJson('/api/flotte/resume');
        $parId = collect($response->json('par_camion'))->keyBy('id');

        $this->assertNull($parId[$camion->id]['objectif_conso']);
        $this->assertNull($parId[$camion->id]['objectif_atteint']);
    }
}
